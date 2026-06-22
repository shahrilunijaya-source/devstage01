<?php

namespace Database\Seeders;

use App\Services\Knowledge\KnowledgeSeeder;
use Illuminate\Database\Seeder;

/** Publishes the shared KRISA Knowledge Book v2026.1 (PRD §7.1). */
class KnowledgeBookSeeder extends Seeder
{
    public function run(): void
    {
        app(KnowledgeSeeder::class)->seed();
    }
}
