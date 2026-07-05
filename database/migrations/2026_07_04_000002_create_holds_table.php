<?php

use App\Enums\HoldStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->enum('status', array_column(HoldStatus::cases(), 'value'))
                ->default(HoldStatus::Held->value);
            $table->uuid('idempotency_key')->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['slot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};
