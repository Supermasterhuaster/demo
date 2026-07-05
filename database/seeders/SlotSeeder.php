<?php

namespace Database\Seeders;

use App\Models\Slot;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Slot::insert([
            ['capacity' => 10, 'remaining' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['capacity' => 5, 'remaining' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
