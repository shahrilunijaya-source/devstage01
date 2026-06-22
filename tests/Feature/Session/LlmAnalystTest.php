<?php

declare(strict_types=1);

namespace Tests\Feature\Session;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Services\Graph\ObjectGraphService;
use App\Services\Rag\AnthropicClient;
use App\Services\Session\Analysis\DeterministicEvidenceAnalyst;
use App\Services\Session\Analysis\EvidenceAnalyst;
use App\Services\Session\Analysis\Exceptions\AnalysisException;
use App\Services\Session\Analysis\LlmEvidenceAnalyst;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class LlmAnalystTest extends TestCase
{
    use RefreshDatabase;

    private function evidence(): EngObject
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);

        return app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Interview', ['body' => 'Payroll runs monthly on the 25th.'],
        );
    }

    /** A stubbed Anthropic client that returns a fixed completion. */
    private function client(string $reply): AnthropicClient
    {
        return new class($reply) extends AnthropicClient
        {
            public function __construct(private string $reply) {}

            public function answer(string $model, string $system, string $userContent, int $maxTokens = 1024): string
            {
                return $this->reply;
            }
        };
    }

    public function test_valid_json_maps_to_finding_and_requirements_with_citation(): void
    {
        $json = json_encode([
            'finding' => ['title' => 'Monthly payroll cycle', 'body' => 'Evidence states monthly run.', 'confidence' => 'high', 'impact' => 'medium'],
            'requirements' => [
                ['type' => 'functional_requirement', 'title' => 'Run payroll monthly', 'body' => 'System shall run payroll on the 25th.', 'confidence' => 'high', 'impact' => 'high'],
                ['type' => 'business_requirement', 'title' => 'Timely salary', 'body' => 'Staff paid on time.', 'confidence' => 'medium', 'impact' => 'high'],
            ],
        ]);

        $evidence = $this->evidence();
        $result = (new LlmEvidenceAnalyst($this->client($json)))->analyze($evidence);

        $this->assertSame(ObjectType::FINDING, $result->finding->type);
        $this->assertSame('Monthly payroll cycle', $result->finding->title);
        $this->assertSame(ConfidenceLevel::HIGH, $result->finding->confidence);
        $this->assertCount(2, $result->requirements);
        $this->assertSame(ObjectType::FUNCTIONAL_REQUIREMENT, $result->requirements[0]->type);
        $this->assertSame([$evidence->ref], $result->citations);
    }

    public function test_json_wrapped_in_code_fences_is_parsed(): void
    {
        $json = "```json\n".json_encode([
            'finding' => ['title' => 'F', 'body' => 'b', 'confidence' => 'low', 'impact' => 'low'],
            'requirements' => [['type' => 'user_requirement', 'title' => 'R', 'body' => 'b', 'confidence' => 'low', 'impact' => 'low']],
        ])."\n```";

        $result = (new LlmEvidenceAnalyst($this->client($json)))->analyze($this->evidence());

        $this->assertSame(ObjectType::USER_REQUIREMENT, $result->requirements[0]->type);
    }

    public function test_unknown_requirement_type_defaults_to_business_requirement(): void
    {
        $json = json_encode([
            'finding' => ['title' => 'F', 'confidence' => 'medium', 'impact' => 'medium'],
            'requirements' => [['type' => 'nonsense', 'title' => 'R', 'confidence' => 'wat', 'impact' => 'wat']],
        ]);

        $result = (new LlmEvidenceAnalyst($this->client($json)))->analyze($this->evidence());

        $this->assertSame(ObjectType::BUSINESS_REQUIREMENT, $result->requirements[0]->type);
        $this->assertSame(ConfidenceLevel::MEDIUM, $result->requirements[0]->confidence); // invalid → medium
        $this->assertSame('medium', $result->requirements[0]->impact);
    }

    public function test_malformed_json_raises_analysis_exception(): void
    {
        $this->expectException(AnalysisException::class);
        (new LlmEvidenceAnalyst($this->client('not json at all')))->analyze($this->evidence());
    }

    public function test_client_failure_raises_analysis_exception(): void
    {
        $throwing = new class extends AnthropicClient
        {
            public function __construct() {}

            public function answer(string $model, string $system, string $userContent, int $maxTokens = 1024): string
            {
                throw new RuntimeException('boom');
            }
        };

        $this->expectException(AnalysisException::class);
        (new LlmEvidenceAnalyst($throwing))->analyze($this->evidence());
    }

    public function test_default_binding_is_deterministic_without_keys(): void
    {
        // No RAG settings store in this scaffold → binding must resolve deterministic.
        $this->assertInstanceOf(DeterministicEvidenceAnalyst::class, app(EvidenceAnalyst::class));
    }
}
