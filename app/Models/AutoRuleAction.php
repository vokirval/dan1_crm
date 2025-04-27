<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoRuleAction extends Model
{
    use HasFactory;

    protected $fillable = ['auto_rule_id', 'type', 'parameters'];

    protected $casts = [
        'parameters' => 'array',
    ];
}