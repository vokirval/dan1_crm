<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoRuleCondition extends Model
{
    use HasFactory;

    protected $fillable = ['auto_rule_id', 'field', 'operator', 'value'];
}