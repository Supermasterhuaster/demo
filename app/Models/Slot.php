<?php

namespace App\Models;

use App\Enums\HoldStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Slot extends Model
{
    protected $fillable = ['capacity', 'remaining'];

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function activeHoldsCount(): int
    {
        return $this->holds()->where('status', HoldStatus::Held)
            ->where('expires_at', '>', now())
            ->count();
    }
}
