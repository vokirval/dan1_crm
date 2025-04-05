<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'main_filter',
        'date_filter',
        'user_id'
    ];

    protected $casts = [
        'main_filter' => 'array',
        'date_filter' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}