<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'intent', 'query', 'response', 'resolved', 'resolution_time_seconds'
    ];

    protected $casts = [
        'resolved' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
