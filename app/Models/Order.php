<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_status_id',
        'payment_method_id',
        'delivery_method_id',
        'group_id',
        'responsible_user_id',
        'delivery_price',
        'delivery_fullname',
        'delivery_address',
        'delivery_second_address',
        'delivery_postcode',
        'delivery_city',
        'delivery_state',
        'delivery_country_code',
        'email',
        'phone',
        'ip',
        'comment',
        'website_referrer',
        'utm_source',
        'utm_medium',
        'utm_term',
        'utm_content',
        'utm_campaign',
        'sub_id1',
        'sub_id2',
        'sub_id3',
        'sub_id4',
        'sub_id5',
        'sub_id6',
        'sub_id7',
        'sub_id8',
        'sub_id9',
        'sub_id10',
        'delivery_date', // Новое поле
        'sent_at',
        'payment_date',  // Новое поле
        'tracking_number', // Добавлено
        'is_paid',         // Добавлено
        'paid_amount',     // Добавлено
        'inpost_id', 
        'inpost_status', 
        'return_tracking_number',
        'delivery_address_number'
    ];

    public function status()
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function deliveryMethod()
    {
        return $this->belongsTo(DeliveryMethod::class, 'delivery_method_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function emailHistory()
    {
        return $this->hasMany(EmailHistory::class);
    }

    public function fullfullHistory()
    {
        return $this->hasMany(OrderFulfillment::class);
    }

    public function getMacros(): array
{
    // Генерируем HTML-таблицу с товарами
    $productTable = $this->generateProductTable();

    return [
        '{order_id}' => $this->id,
        '{customer_name}' => $this->delivery_fullname,
        '{customer_email}' => $this->email,
        '{customer_phone}' => $this->phone,
        '{order_total}' => number_format($this->items->sum(fn($item) => $item->quantity * $item->price), 2),
        '{order_date}' => $this->created_at->format('d.m.Y'),
        '{delivery_address}' => $this->delivery_address,
        '{delivery_city}' => $this->delivery_city,
        '{delivery_postcode}' => $this->delivery_postcode,
        '{delivery_state}' => $this->delivery_state,
        '{delivery_country_code}' => $this->delivery_country_code,
        '{tracking_number}' => $this->tracking_number ?? '-',
        '{is_paid}' => $this->is_paid ? 'Оплачено' : 'Не оплачено',
        '{paid_amount}' => number_format($this->paid_amount, 2),
        '{delivery_date}' => optional($this->delivery_date)->format('d.m.Y H:i:s') ?? '-',
        '{payment_date}' => optional($this->payment_date)->format('d.m.Y H:i:s') ?? '-',
        '{payment_method}' => $this->paymentMethod->name ?? '-',
        '{delivery_method}' => $this->deliveryMethod->name ?? '-',
        '{responsible_user}' => $this->responsibleUser->name ?? '-',
        '{group_name}' => $this->group->name ?? '-',
        '{order_status}' => $this->status->name ?? '-',
        '{utm_source}' => $this->utm_source ?? '-',
        '{utm_medium}' => $this->utm_medium ?? '-',
        '{utm_term}' => $this->utm_term ?? '-',
        '{utm_content}' => $this->utm_content ?? '-',
        '{utm_campaign}' => $this->utm_campaign ?? '-',
        '{product_table}' => $productTable,
        '{comment}' => $this->comment ?? 'Нет комментариев',
    ];
}

/**
 * Генерация HTML-таблицы с товарами.
 */
private function generateProductTable(): string
    {
        $tableHeader = '<table border="1" cellspacing="0" cellpadding="5" style="border-collapse: collapse; width: 100%;">';
        $tableHeader .= '<tr>';
        $tableHeader .= '<th>Название</th>';
        $tableHeader .= '<th>Вариант</th>';
        $tableHeader .= '<th>Количество</th>';
        $tableHeader .= '<th>Цена</th>';
        $tableHeader .= '<th>Сумма</th>';
        $tableHeader .= '</tr>';

        $tableRows = $this->items->map(function ($item) {
            $productName = $item->product->name ?? ($item->productVariation->product->name ?? 'Не найдено');
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

        $totalAmount = $this->items->sum(fn($item) => $item->quantity * $item->price);

        $tableFooter = sprintf(
            '<tr>
                <td colspan="4" style="text-align: right; font-weight: bold;">Итого:</td>
                <td>%0.2f</td>
            </tr>',
            $totalAmount
        );

        return $tableHeader . $tableRows . $tableFooter . '</table>';
    }






}
