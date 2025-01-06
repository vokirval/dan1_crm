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
     * Метод для замены макросов в шаблоне письма.
     */
    private function replaceMacros(string $templateBody, Order $order): string
    {
        $macros = $order->getMacros(); // Получаем макросы из модели Order
        return str_replace(array_keys($macros), array_values($macros), $templateBody);
    }

    /**
     * Отправка письма по шаблону.
     */
    public function sendEmail(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        if (!($order instanceof Order)) {
            throw new \InvalidArgumentException('Invalid order object passed');
        }

        $validated = $request->validate([
            'template_id' => 'nullable|exists:email_templates,id',
            'custom_subject' => 'nullable|string|required_without:template_id',
            'custom_body' => 'nullable|string|required_without:template_id',
        ]);

        $template = $validated['template_id']
            ? EmailTemplate::findOrFail($validated['template_id'])
            : null;

        $subject = $template
            ? $this->replaceMacros($template->subject, $order)
            : $this->replaceMacros($validated['custom_subject'], $order);

        $body = $template
            ? $this->replaceMacros($template->body, $order)
            : $this->replaceMacros($validated['custom_body'], $order);

        try {
            Mail::send([], [], function ($message) use ($order, $subject, $body) {
                $message->to($order->email)
                    ->subject($subject)
                    ->html($body); // Указываем HTML-тело письма
            });

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


}
