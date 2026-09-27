<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_INITIATED = 'initiated';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_VERIFIED  = 'verified';

    protected $fillable = [
        'order_id',
        'gateway',
        'authority',
        'amount',
        'status',
        'reference',
        'verified_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
