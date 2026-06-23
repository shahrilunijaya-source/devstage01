<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@devstage01.local',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create system settings table
        DB::statement('CREATE TABLE IF NOT EXISTS system_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(255) NOT NULL UNIQUE,
            `value` TEXT,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )');

        // Insert API keys placeholders
        DB::table('system_settings')->insert([
            ['key' => 'anthropic_api_key', 'value' => env('ANTHROPIC_API_KEY', ''), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'voyage_api_key', 'value' => env('VOYAGE_API_KEY', ''), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
