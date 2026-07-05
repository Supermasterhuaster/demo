<?php

namespace App\Services;

use App\Enums\HoldStatus;
use App\Exceptions\InvalidHoldStateException;
use App\Exceptions\SlotFullException;
use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SlotService
{
    private const CACHE_KEY = 'slots:availability';

    private const CACHE_TTL = 10;

    public function getAvailability(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Cache::lock(self::CACHE_KEY.':lock', 5)->block(3, function () {
                return Slot::all()->map(fn (Slot $slot) => [
                    'slot_id' => $slot->id,
                    'capacity' => $slot->capacity,
                    'remaining' => $slot->remaining - $slot->activeHoldsCount(),
                ])->values()->all();
            });
        });
    }

    public function createHold(int $slotId, string $idempotencyKey): Hold
    {
        if ($existing = Hold::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($slotId, $idempotencyKey) {
            $slot = Slot::lockForUpdate()->findOrFail($slotId);

            if ($slot->remaining - $slot->activeHoldsCount() <= 0) {
                throw new SlotFullException();
            }

            return Hold::create([
                'slot_id' => $slotId,
                'status' => HoldStatus::Held,
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes(5),
            ]);
        });
    }

    public function confirmHold(int $holdId): Hold
    {
        $hold = DB::transaction(function () use ($holdId) {
            $hold = Hold::lockForUpdate()->findOrFail($holdId);

            if ($hold->status !== HoldStatus::Held) {
                throw new InvalidHoldStateException();
            }

            if ($hold->expires_at->isPast()) {
                throw new InvalidHoldStateException();
            }

            $affected = Slot::where('id', $hold->slot_id)
                ->where('remaining', '>', 0)
                ->decrement('remaining');

            if ($affected === 0) {
                throw new SlotFullException();
            }

            $hold->update(['status' => HoldStatus::Confirmed]);

            return $hold->fresh();
        });

        $this->invalidateAvailabilityCache();

        return $hold;
    }

    public function cancelHold(int $holdId): void
    {
        DB::transaction(function () use ($holdId) {
            $hold = Hold::lockForUpdate()->findOrFail($holdId);

            if ($hold->status === HoldStatus::Cancelled) {
                return;
            }

            if ($hold->status === HoldStatus::Confirmed) {
                Slot::where('id', $hold->slot_id)->increment('remaining');
            } elseif ($hold->status !== HoldStatus::Held) {
                throw new InvalidHoldStateException();
            }

            $hold->update(['status' => HoldStatus::Cancelled]);
        });

        $this->invalidateAvailabilityCache();
    }

    private function invalidateAvailabilityCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
