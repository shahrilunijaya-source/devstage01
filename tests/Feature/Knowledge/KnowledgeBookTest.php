<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\LifecycleStage;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Services\Knowledge\KnowledgeResolver;
use App\Services\Knowledge\KnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBookTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        $tenant = Tenant::default();

        return Project::create(['tenant_id' => $tenant->id, 'name' => 'Acme ERP', 'code' => 'ERP', 'status' => 'active']);
    }

    public function test_seeder_publishes_krisa_book_with_deliverables(): void
    {
        $book = app(KnowledgeSeeder::class)->seed();

        $this->assertSame('v2026.1', $book->version);
        $this->assertSame('published', $book->status);
        $this->assertSame(19, $book->items()->where('item_type', 'deliverable')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $seeder = app(KnowledgeSeeder::class);
        $seeder->seed();
        $book = $seeder->seed();

        $this->assertSame(19, $book->items()->where('item_type', 'deliverable')->count());
    }

    public function test_pin_and_question_bank_resolution(): void
    {
        app(KnowledgeSeeder::class)->seed();
        $project = $this->project();
        $resolver = app(KnowledgeResolver::class);

        $resolver->pin($project, 'v2026.1');

        $this->assertSame('v2026.1', $resolver->pinnedBook($project)->version);

        $brsQuestions = $resolver->questionBank($project, LifecycleStage::BRS);
        $this->assertSame(3, $brsQuestions->count());
        $this->assertTrue($brsQuestions->every(fn ($i) => str_starts_with($i->code, 'BRS-Q-')));
    }

    public function test_unpinned_project_resolves_no_items(): void
    {
        app(KnowledgeSeeder::class)->seed();
        $resolver = app(KnowledgeResolver::class);

        $this->assertTrue($resolver->effectiveItems($this->project(), LifecycleStage::BRS)->isEmpty());
    }
}
