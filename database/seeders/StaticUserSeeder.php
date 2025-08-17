<?php

// database/seeders/StaticUserSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;

class StaticUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'Abdelaziz@pureminds'],
            [
                'name'      => 'Abdelaziz',
                'email'     => 'admin@example.com',
                'password'  => 'SuperSecret#123', // auto-hashed
                'is_static' => true,
            ]
        );
    }
}
