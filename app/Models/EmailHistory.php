<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailHistory extends Model
{
    use HasFactory;

    protected $table = 'email_history';

    protected $fillable = [
        'order_id', 'template_id', 'to_email', 'subject', 'body', 'status', 'error_message', 'sent_at'
    ];
}