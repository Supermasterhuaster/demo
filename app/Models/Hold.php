<?php

namespace App\Models;

use App\Enums\HoldStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hold extends Model
{
    protected $fillable = ['slot_id', 'status', 'idempotency_key', 'expires_at'];

    protected function casts(): array
    {
        return [
            'status' => HoldStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }
}
