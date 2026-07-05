<?php

namespace Tests\Feature;

use App\Enums\HoldStatus;
use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class SlotBookingTest extends TestCase
{
    use RefreshDatabase;

    private Slot $slot1;

    private Slot $slot2;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->slot1 = Slot::create(['capacity' => 10, 'remaining' => 10]);
        $this->slot2 = Slot::create(['capacity' => 5, 'remaining' => 5]);
    }

    private function uuid(): string
    {
        return (string) Str::uuid();
    }

    public function test_availability_returns_all_slots(): void
    {
        $this->getJson('/api/slots/availability')
            ->assertOk()
            ->assertExactJson([
                ['slot_id' => $this->slot1->id, 'capacity' => 10, 'remaining' => 10],
                ['slot_id' => $this->slot2->id, 'capacity' => 5, 'remaining' => 5],
            ]);
    }

    public function test_create_hold(): void
    {
        $key = $this->uuid();

        $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $key])
            ->assertCreated()
            ->assertJsonPath('slot_id', $this->slot1->id)
            ->assertJsonPath('status', 'held')
            ->assertJsonPath('idempotency_key', $key);

        $this->assertDatabaseHas('holds', [
            'slot_id' => $this->slot1->id,
            'status' => HoldStatus::Held->value,
            'idempotency_key' => $key,
        ]);
    }

    public function test_create_hold_is_idempotent(): void
    {
        $key = $this->uuid();

        $first = $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $key]);
        $second = $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $key]);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertDatabaseCount('holds', 1);
    }

    public function test_create_hold_requires_idempotency_key(): void
    {
        $this->postJson("/api/slots/{$this->slot1->id}/hold")
            ->assertUnprocessable()
            ->assertJson(['error' => 'Idempotency-Key required']);
    }

    public function test_create_hold_returns_409_when_slot_is_full(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/slots/{$this->slot2->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
                ->assertCreated();
        }

        $this->postJson("/api/slots/{$this->slot2->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
            ->assertConflict();
    }

    public function test_availability_subtracts_active_holds(): void
    {
        $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
            ->assertCreated();

        Cache::flush();

        $response = $this->getJson('/api/slots/availability')->assertOk();
        $slots = collect($response->json())->keyBy('slot_id');

        $this->assertSame(9, $slots[$this->slot1->id]['remaining']);
    }

    public function test_confirm_hold(): void
    {
        $holdId = $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
            ->json('id');

        $this->postJson("/api/holds/{$holdId}/confirm")
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $this->assertDatabaseHas('holds', ['id' => $holdId, 'status' => HoldStatus::Confirmed->value]);
        $this->assertDatabaseHas('slots', ['id' => $this->slot1->id, 'remaining' => 9]);
    }

    public function test_confirm_invalidates_availability_cache(): void
    {
        $this->getJson('/api/slots/availability')->assertOk();

        $holdId = $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
            ->json('id');

        $this->postJson("/api/holds/{$holdId}/confirm")->assertOk();

        $response = $this->getJson('/api/slots/availability')->assertOk();
        $slots = collect($response->json())->keyBy('slot_id');

        $this->assertSame(9, $slots[$this->slot1->id]['remaining']);
    }

    public function test_confirm_returns_409_when_already_confirmed(): void
    {
        $holdId = $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
            ->json('id');

        $this->postJson("/api/holds/{$holdId}/confirm")->assertOk();
        $this->postJson("/api/holds/{$holdId}/confirm")->assertConflict();
    }

    public function test_confirm_returns_409_when_hold_expired(): void
    {
        $hold = Hold::create([
            'slot_id' => $this->slot1->id,
            'status' => HoldStatus::Held,
            'idempotency_key' => $this->uuid(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/holds/{$hold->id}/confirm")->assertConflict();
    }

    public function test_confirm_returns_409_on_oversell(): void
    {
        $this->slot2->update(['remaining' => 0]);

        $hold = Hold::create([
            'slot_id' => $this->slot2->id,
            'status' => HoldStatus::Held,
            'idempotency_key' => $this->uuid(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson("/api/holds/{$hold->id}/confirm")->assertConflict();
    }

    public function test_cancel_held_hold(): void
    {
        $holdId = $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
            ->json('id');

        $this->deleteJson("/api/holds/{$holdId}")->assertNoContent();

        $this->assertDatabaseHas('holds', ['id' => $holdId, 'status' => HoldStatus::Cancelled->value]);
        $this->assertDatabaseHas('slots', ['id' => $this->slot1->id, 'remaining' => 10]);
    }

    public function test_cancel_confirmed_hold_returns_remaining(): void
    {
        $holdId = $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
            ->json('id');

        $this->postJson("/api/holds/{$holdId}/confirm")->assertOk();
        $this->deleteJson("/api/holds/{$holdId}")->assertNoContent();

        $this->assertDatabaseHas('holds', ['id' => $holdId, 'status' => HoldStatus::Cancelled->value]);
        $this->assertDatabaseHas('slots', ['id' => $this->slot1->id, 'remaining' => 10]);
    }

    public function test_cancel_is_idempotent(): void
    {
        $holdId = $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
            ->json('id');

        $this->deleteJson("/api/holds/{$holdId}")->assertNoContent();
        $this->deleteJson("/api/holds/{$holdId}")->assertNoContent();
    }

    public function test_cancel_invalidates_availability_cache(): void
    {
        $holdId = $this->postJson("/api/slots/{$this->slot1->id}/hold", [], ['Idempotency-Key' => $this->uuid()])
            ->json('id');

        $this->postJson("/api/holds/{$holdId}/confirm")->assertOk();

        $response = $this->getJson('/api/slots/availability')->assertOk();
        $this->assertSame(9, collect($response->json())->keyBy('slot_id')[$this->slot1->id]['remaining']);

        $this->deleteJson("/api/holds/{$holdId}")->assertNoContent();

        $response = $this->getJson('/api/slots/availability')->assertOk();
        $this->assertSame(10, collect($response->json())->keyBy('slot_id')[$this->slot1->id]['remaining']);
    }
}
