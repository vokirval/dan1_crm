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
        'payment_date',  // Новое поле
        'tracking_number', // Добавлено
        'is_paid',         // Добавлено
        'paid_amount',     // Добавлено
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

    public function getMacros(): array
{
    // Генерируем HTML-таблицу с товарами
    $productTable = $this->generateProductTable();

    return [
        '{order_id}' => $this->id,
        '{customer_name}' => $this->delivery_fullname,
        '{customer_email}' => $this->email,
        '{customer_phone}' => $this->phone,
        '{order_total}' => $this->items->sum(fn($item) => $item->quantity * $item->price), // Общая сумма заказа
        '{order_date}' => $this->created_at->format('d.m.Y'),
        '{delivery_address}' => $this->delivery_address,
        '{delivery_city}' => $this->delivery_city,
        '{delivery_postcode}' => $this->delivery_postcode,
        '{product_table}' => $productTable, // Вставляем таблицу с товарами
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
    $tableHeader .= '<th>Количество</th>';
    $tableHeader .= '<th>Цена</th>';
    $tableHeader .= '<th>Сумма</th>';
    $tableHeader .= '</tr>';

    $tableRows = $this->items->map(function ($item) {
        return sprintf(
            '<tr>
                <td>%s</td>
                <td>%d</td>
                <td>%0.2f</td>
                <td>%0.2f</td>
            </tr>',
            e($item->product->name ?? 'Не найдено'), // Название товара
            $item->quantity,
            $item->price,
            $item->quantity * $item->price
        );
    })->join('');

    $tableFooter = '</table>';

    return $tableHeader . $tableRows . $tableFooter;
}

}
