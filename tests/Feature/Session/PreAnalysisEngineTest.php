<?php

declare(strict_types=1);

namespace Tests\Feature\Session;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Services\Graph\ObjectGraphService;
use App\Services\Session\Analysis\AnalysisResult;
use App\Services\Session\Analysis\DraftedObject;
use App\Services\Session\Analysis\EvidenceAnalyst;
use App\Services\Session\Analysis\Exceptions\AnalysisException;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreAnalysisEngineTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Session,1:EngObject} pre-analysis session with one evidence */
    private function sessionWithEvidence(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        $evidence = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $tenant->id, $project->id, 'Interview',
            ['module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'b'],
        );

        return [$session, $evidence];
    }

    private function bindAnalyst(EvidenceAnalyst $analyst): void
    {
        $this->app->bind(EvidenceAnalyst::class, fn () => $analyst);
    }

    public function test_engine_materializes_llm_drafts_with_citations(): void
    {
        [$session, $evidence] = $this->sessionWithEvidence();

        // Stub analyst returns a finding + two requirements citing the evidence.
        $this->bindAnalyst(new class($evidence->ref) implements EvidenceAnalyst
        {
            public function __construct(private string $ref) {}

            public function analyze(EngObject $evidence): AnalysisResult
            {
                return new AnalysisResult(
                    new DraftedObject(ObjectType::FINDING, 'AI finding', 'body', ConfidenceLevel::HIGH, 'medium'),
                    [
                        new DraftedObject(ObjectType::FUNCTIONAL_REQUIREMENT, 'FR one', 'b', ConfidenceLevel::HIGH, 'high'),
                        new DraftedObject(ObjectType::NON_FUNCTIONAL_REQUIREMENT, 'NFR one', 'b', ConfidenceLevel::MEDIUM, 'low'),
                    ],
                    [$this->ref],
                );
            }
        });

        $drafted = app(SessionEngineService::class)->preAnalyze($session);

        $this->assertSame(3, $drafted); // 1 finding + 2 requirements
        $this->assertSame('firewall_review', $session->fresh()->phase);

        $finding = EngObject::where('session_id', $session->id)->where('type', 'finding')->firstOrFail();
        $this->assertSame('AI finding', $finding->title);
        $this->assertSame([$evidence->ref], $finding->attributes['cites']);

        $this->assertSame(1, EngObject::where('session_id', $session->id)->where('type', 'functional_requirement')->count());
        $this->assertSame(1, EngObject::where('session_id', $session->id)->where('type', 'non_functional_requirement')->count());
    }

    public function test_engine_falls_back_to_deterministic_when_analyst_throws(): void
    {
        [$session, $evidence] = $this->sessionWithEvidence();

        $this->bindAnalyst(new class implements EvidenceAnalyst
        {
            public function analyze(EngObject $evidence): AnalysisResult
            {
                throw new AnalysisException('LLM down');
            }
        });

        $drafted = app(SessionEngineService::class)->preAnalyze($session);

        // Deterministic fallback: 1 finding + 1 business requirement.
        $this->assertSame(2, $drafted);
        $this->assertSame(1, EngObject::where('session_id', $session->id)->where('type', 'business_requirement')->count());
    }
}
