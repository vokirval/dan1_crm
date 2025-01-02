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
}
