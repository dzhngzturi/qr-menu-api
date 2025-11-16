<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TelemetryEvent extends Model
{
    use HasFactory;

    protected $table = 'telemetry_events';

    protected $fillable = [
        'restaurant_id',
        'type',
        'occurred_at',
        'session_id',
        'ip',
        'user_agent',
        'search_term',
        'payload',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload'     => 'array',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
