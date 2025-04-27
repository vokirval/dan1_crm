<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoRule extends Model
{
    use HasFactory;

    protected $fillable = ['order_status_id', 'name', 'is_active'];

    public function orderStatus()
    {
        return $this->belongsTo(OrderStatus::class);
    }

    public function conditions()
    {
        return $this->hasMany(AutoRuleCondition::class);
    }

    public function actions()
    {
        return $this->hasMany(AutoRuleAction::class);
    }

    public function logs()
    {
        return $this->hasMany(Logs::class);
    }
}