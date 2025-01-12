<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\EmailTemplate;
use App\Models\EmailHistory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    /**
     * Замена макросов в шаблоне письма.
     */
    private function replaceMacros(string $templateBody, Order $order): string
    {
        $macros = $this->getMacros($order); // Генерируем макросы для письма
        return str_replace(array_keys($macros), array_values($macros), $templateBody);
    }

    /**
     * Генерация списка шорткодов для письма.
     */
    private function getMacros(Order $order): array
    {
        // Генерация HTML-таблицы с товарами
        $productTable = $this->generateProductTable($order);

        return [
            '{order_id}' => $order->id,
            '{customer_name}' => $order->delivery_fullname,
            '{customer_email}' => $order->email,
            '{customer_phone}' => $order->phone,
            '{order_total}' => number_format($order->items->sum(fn($item) => $item->quantity * $item->price), 2),
            '{order_date}' => $order->created_at->format('d.m.Y'),
            '{delivery_address}' => $order->delivery_address,
            '{delivery_city}' => $order->delivery_city,
            '{delivery_postcode}' => $order->delivery_postcode,
            '{delivery_state}' => $order->delivery_state,
            '{delivery_country_code}' => $order->delivery_country_code,
            '{tracking_number}' => $order->tracking_number ?? '-',
            '{website_referrer}' => $order->website_referrer ?? '-',
            '{is_paid}' => $order->is_paid ? 'Opłacone' : 'Nieopłacone',
            '{paid_amount}' => number_format($order->paid_amount, 2),
            '{delivery_date}' => optional($order->delivery_date)->format('d.m.Y H:i:s') ?? '-',
            '{payment_date}' => optional($order->payment_date)->format('d.m.Y H:i:s') ?? '-',
            '{payment_method}' => $order->paymentMethod->name ?? '-',
            '{delivery_method}' => $order->deliveryMethod->name ?? '-',
            '{responsible_user}' => $order->responsibleUser->name ?? '-',
            '{group_name}' => $order->group->name ?? '-',
            '{order_status}' => $order->status->name ?? '-',
            '{utm_source}' => $order->utm_source ?? '-',
            '{utm_medium}' => $order->utm_medium ?? '-',
            '{utm_term}' => $order->utm_term ?? '-',
            '{utm_content}' => $order->utm_content ?? '-',
            '{utm_campaign}' => $order->utm_campaign ?? '-',
            '{product_table}' => $productTable,
            '{comment}' => $order->comment ?? '-',
        ];
    }

    /**
     * Генерация HTML-таблицы с товарами.
     */
    private function generateProductTable(Order $order): string
    {
        $tableHeader = '<table border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
        $tableHeader .= '<tr>';
        $tableHeader .= '<th>Nazwa</th>'; // Название
        $tableHeader .= '<th>Wariant</th>'; // Вариант
        $tableHeader .= '<th>Ilość</th>'; // Количество
        $tableHeader .= '<th>Cena</th>'; // Цена
        $tableHeader .= '<th>Suma</th>'; // Сумма
        $tableHeader .= '</tr>';

        $tableRows = $order->items->map(function ($item) {
            $productName = $item->product->name ?? ($item->productVariation->product->name ?? '-');
            $variationAttributes = $item->productVariation && $item->productVariation->relationLoaded('attributes')
                ? $item->productVariation->attributes->map(function ($attr) {
                    return "{$attr->attribute_name}: {$attr->attribute_value}";
                })->join(', ')
                : '-';

            return sprintf(
                '<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%d</td>
                    <td>%0.2f</td>
                    <td>%0.2f</td>
                </tr>',
                e($productName),
                e($variationAttributes),
                $item->quantity,
                $item->price,
                $item->quantity * $item->price
            );
        })->join('');

        $totalAmount = $order->items->sum(fn($item) => $item->quantity * $item->price);

        $tableFooter = sprintf(
            '<tr>
                <td colspan="4" style="text-align: right; font-weight: bold;">Razem:</td>
                <td>%0.2f</td>
            </tr>',
            $totalAmount
        );

        return $tableHeader . $tableRows . $tableFooter . '</table>';
    }

    /**
     * Отправка письма по шаблону.
     */
    public function sendEmail(Request $request, $orderId)
    {
        // Загружаем заказ с нужными зависимостями
        $order = Order::with([
            'items.product',
            'items.productVariation.product',
            'items.productVariation.attributes',
            'paymentMethod',
            'deliveryMethod',
            'responsibleUser',
            'status'
        ])->findOrFail($orderId);

        // Убедимся, что получен объект Order
        if (!($order instanceof Order)) {
            throw new \InvalidArgumentException('Invalid order object passed');
        }

        $validated = $request->validate([
            'template_id' => 'nullable|exists:email_templates,id',
            'custom_subject' => 'nullable|string|required_without:template_id',
            'custom_body' => 'nullable|string|required_without:template_id',
        ]);
        // Получение шаблона
        $template = $validated['template_id']
            ? EmailTemplate::findOrFail($validated['template_id'])
            : null;

        // Замена макросов в теме и теле письма
        $subject = $template
            ? $this->replaceMacros($template->subject, $order)
            : $this->replaceMacros($validated['custom_subject'], $order);

        $body = $template
            ? $this->replaceMacros($template->body, $order)
            : $this->replaceMacros($validated['custom_body'], $order);

        try {
            // Отправка письма
            Mail::send([], [], function ($message) use ($order, $subject, $body) {
                $message->to($order->email)
                    ->subject($subject)
                    ->html($body); // Указываем HTML-тело письма
            });

            // Сохранение истории успешной отправки
            EmailHistory::create([
                'order_id' => $order->id,
                'template_id' => $template->id ?? null,
                'to_email' => $order->email,
                'subject' => $subject,
                'body' => $body,
                'status' => 'success',
                'sent_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Email успешно отправлено!']);
        } catch (\Exception $e) {
            // Сохранение истории ошибки
            EmailHistory::create([
                'order_id' => $order->id,
                'template_id' => $template->id ?? null,
                'to_email' => $order->email,
                'subject' => $subject,
                'body' => $body,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Ошибка отправки письма.', 'error' => $e->getMessage()]);
        }
    }

    public function previewTemplate(Request $request, $orderId)
    {
        $request->validate([
            'template_id' => 'required|exists:email_templates,id',
        ]);

        $order = Order::with([
            'items.product',
            'items.productVariation.product',
            'items.productVariation.attributes',
            'paymentMethod',
            'deliveryMethod',
            'responsibleUser',
            'status'
        ])->findOrFail($orderId);

        // Убедимся, что получен объект Order
        if (!($order instanceof Order)) {
            throw new \InvalidArgumentException('Invalid order object passed');
        }

        $template = EmailTemplate::findOrFail($request->input('template_id'));

        // Замена шорткодов в шаблоне
        $body = $this->replaceMacros($template->body, $order);

        return response()->json([
            'success' => true,
            'preview' => $body,
        ]);
    }

    public function getMacrosList()
    {
        // Пример описаний макросов
        $macrosDescriptions = [
            '{order_id}' => 'ID замовлення',
            '{customer_name}' => "Ім'я клієнта",
            '{customer_email}' => 'Email клієнта',
            '{customer_phone}' => 'Телефон клієнта',
            '{order_total}' => 'Загальна сума замовлення',
            '{order_date}' => 'Дата замовлення',
            '{delivery_address}' => 'Адреса доставки',
            '{delivery_city}' => 'Місто доставки',
            '{delivery_postcode}' => 'Поштовий індекс доставки',
            '{delivery_state}' => 'Штат/Область доставки',
            '{delivery_country_code}' => 'Код країни доставки',
            '{tracking_number}' => 'Трек-номер замовлення',
            '{website_referrer}' => 'Сайт на якому було створено замовлення',
            '{is_paid}' => 'Статус оплати замовлення',
            '{paid_amount}' => 'Сплачена сума',
            '{delivery_date}' => 'Дата доставки',
            '{payment_date}' => 'Дата оплати',
            '{payment_method}' => 'Метод оплати',
            '{delivery_method}' => 'Метод доставки',
            '{responsible_user}' => 'Відповідальний користувач',
            '{group_name}' => 'Назва групи замовлення',
            '{order_status}' => 'Статус замовлення',
            '{utm_source}' => 'UTM Source',
            '{utm_medium}' => 'UTM Medium',
            '{utm_term}' => 'UTM Term',
            '{utm_content}' => 'UTM Content',
            '{utm_campaign}' => 'UTM Campaign',
            '{product_table}' => 'Таблиця товарів замовлення',
            '{comment}' => 'Коментар до замовлення',
        ];
        
        

        return response()->json($macrosDescriptions);
    }

}
