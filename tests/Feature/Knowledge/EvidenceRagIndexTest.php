<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\RagChunk;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Knowledge\EvidenceIndexer;
use App\Services\Rag\RagRetriever;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class EvidenceRagIndexTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Session,1:Project,2:User} */
    private function makeSession(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'Acme ERP', 'code' => 'ERP', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$session, $project, $pm];
    }

    private function fakeVoyage(): void
    {
        SystemSetting::set('voyage_api_key', 'pa-test');
        Http::fake(['api.voyageai.com/*' => function ($request) {
            $data = [];
            foreach ($request['input'] as $i => $_) {
                $data[] = ['index' => $i, 'embedding' => [0.1, 0.2, 0.3, 0.4]];
            }

            return Http::response(['data' => $data]);
        }]);
    }

    public function test_indexing_is_skipped_when_no_voyage_key(): void
    {
        [$session, $project] = $this->makeSession();
        $evidence = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Notes',
            ['session_id' => $session->id, 'body' => 'Payroll runs monthly on the 25th.'],
        );

        $this->assertSame(0, app(EvidenceIndexer::class)->index($evidence));
        $this->assertSame(0, RagChunk::where('source_type', 'evidence')->count());
    }

    public function test_pasted_evidence_is_indexed_and_retrievable(): void
    {
        $this->fakeVoyage();
        [$session, $project, $pm] = $this->makeSession();

        $this->actingAs($pm)->post(route('sessions.evidence.store', $session), [
            'label' => 'Interview notes', 'source_type' => 'paste',
            'text' => 'Payroll is disbursed monthly by the 25th per tender clause 4.2.',
        ])->assertRedirect();

        $evidence = EngObject::where('session_id', $session->id)->where('type', 'evidence')->firstOrFail();
        $chunk = RagChunk::where('source_type', 'evidence')->where('source_id', $evidence->id)->first();

        $this->assertNotNull($chunk);
        $this->assertSame('project', $chunk->scope);
        $this->assertSame((int) $project->tenant_id, (int) $chunk->tenant_id);
        $this->assertStringContainsString($evidence->ref, $chunk->source_label);

        // Retrieval substrate surfaces the evidence chunk for the owning project.
        $found = app(RagRetriever::class)->scopedChunks([$project->id], includeGlobal: false);
        $this->assertTrue($found->contains(fn (RagChunk $c) => $c->source_type === 'evidence'));
    }

    public function test_pdf_evidence_text_is_extracted_and_indexed(): void
    {
        $this->fakeVoyage();
        Storage::fake('local');
        [$session, $project] = $this->makeSession();

        // Render a real PDF carrying known text, store it as the evidence file.
        $pdf = Pdf::loadHTML('<p>Payroll is disbursed monthly by the 25th.</p>')->output();
        Storage::put('evidence/spec.pdf', $pdf);

        $evidence = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Spec PDF',
            ['session_id' => $session->id, 'attributes' => ['mime' => 'application/pdf', 'path' => 'evidence/spec.pdf']],
        );

        $chunks = app(EvidenceIndexer::class)->index($evidence);

        $this->assertGreaterThan(0, $chunks);
        $chunk = RagChunk::where('source_type', 'evidence')->where('source_id', $evidence->id)->first();
        $this->assertNotNull($chunk);
        $this->assertStringContainsString('Payroll', $chunk->chunk_text);
    }

    public function test_docx_evidence_text_is_extracted_and_indexed(): void
    {
        $this->fakeVoyage();
        Storage::fake('local');
        [$session, $project] = $this->makeSession();

        // Build a real .docx with known text via PhpWord.
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('Vendor onboarding may slip the go-live date.');
        $tmp = tempnam(sys_get_temp_dir(), 'doc').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($tmp);
        Storage::put('evidence/spec.docx', file_get_contents($tmp));
        @unlink($tmp);

        $evidence = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Spec DOCX',
            ['session_id' => $session->id, 'attributes' => [
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'path' => 'evidence/spec.docx',
            ]],
        );

        $chunks = app(EvidenceIndexer::class)->index($evidence);

        $this->assertGreaterThan(0, $chunks);
        $chunk = RagChunk::where('source_type', 'evidence')->where('source_id', $evidence->id)->first();
        $this->assertNotNull($chunk);
        $this->assertStringContainsString('Vendor onboarding', $chunk->chunk_text);
    }

    public function test_reindex_replaces_prior_chunks_idempotently(): void
    {
        $this->fakeVoyage();
        [$session, $project] = $this->makeSession();
        $evidence = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Notes',
            ['session_id' => $session->id, 'body' => 'Some captured evidence text for indexing.'],
        );

        $indexer = app(EvidenceIndexer::class);
        $first = $indexer->index($evidence);
        $second = $indexer->index($evidence);

        $this->assertSame($first, $second);
        $this->assertSame($first, RagChunk::where('source_id', $evidence->id)->where('source_type', 'evidence')->count());
    }
}
