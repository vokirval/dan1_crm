<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Logs extends Model
{
    use HasFactory;

    protected $fillable = ['auto_rule_id', 'order_id', 'message', 'completed', 'completed_by'];

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}