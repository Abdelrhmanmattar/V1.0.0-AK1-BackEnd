<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CheckupRecord;
use App\Models\Part;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class CheckupRecordSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure users exist (created by UserSeeder)
        $admin = User::where('email', 'admin@example.com')->first();
        $alice = User::where('email', 'alice@example.com')->first();

        if (!$admin || !$alice) {
            $this->command->warn('⚠️ Users not found. Run UserSeeder first.');
            return;
        }

        // Seed one checkup per part (you can loop to create history)
        foreach (Part::cursor() as $part) {
            // map user by part type
            $owner = $part->type === 'furniture' ? $admin : $alice;

            CheckupRecord::create([
                'part_id'           => $part->id,
                'part_name'         => $part->name,
                'user_id'           => $owner->id,
                'user_name'         => $owner->name,
                'checkup_date'      => now()->subDays(rand(1, 14)),
                'status'            => collect(['good','needs-attention','needs-repair'])->random(),
                'notes'             => rand(0,1) ? 'Auto-seeded checkup record' : null,
                'next_checkup_date' => now()->addDays(rand(7, 45)),
                'checkup_photos'    => [
                    // make sure you ran `php artisan storage:link`
                    Storage::disk('public')->url("checkups/{$part->id}/sample1.jpg"),
                    Storage::disk('public')->url("checkups/{$part->id}/sample2.jpg"),
                ],
            ]);
        }
    }
}
