<?php

namespace Database\Seeders;

use App\Models\CheckoutRecord;
use App\Models\Part;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CheckoutRecordSeeder extends Seeder
{
    public function run(): void
    {
        // Make sure parts and users exist
        $user1 = User::where('email', 'admin@example.com')->first();  // Furniture manager
        $user2 = User::where('email', 'alice@example.com')->first();  // Devices manager

        if (!$user1 || !$user2) {
            $this->command->warn('⚠️ Users not found. Run UserSeeder first.');
            return;
        }

        // Seed sample checkout records for parts (using cursor for large sets)
        foreach (Part::cursor() as $part) {
            // Choose user based on part type (furniture or device)
            $user = ($part->type === 'furniture') ? $user1 : $user2;

            // Create a checkout record
            CheckoutRecord::create([
                'part_id'            => $part->id,
                'part_name'          => $part->name, // snapshot
                'user_id'            => $user->id,
                'user_name'          => $user->name, // snapshot
                'custody_assigned_to'=> 'John Doe', // Assigned user for checkout
                'usage_type'         => 'internal', // Can also be 'external'
                'checked_out_at'     => now()->subDays(rand(1, 14)),
                'returned_at'        => null, // No return yet
                'checkout_photos'    => null,
                'notes'              => 'Sample checkout record for testing',
            ]);
        }

        $this->command->info('Checkout records seeded successfully.');
    }
}
