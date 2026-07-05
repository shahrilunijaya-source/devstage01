<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Rag\RagService;
use App\Services\Rag\Tools\ObjectSearchTool;
use App\Services\Rag\Tools\ProjectStatusTool;
use App\Services\Rag\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression net for the chat stack. The original Track extraction never bound
 * ToolRegistry, so app(RagService::class) threw BindingResolutionException and
 * every /portfolio/chat ask() returned 502 — invisible to the suite because no
 * test resolved the service. These tests keep the wiring honest.
 */
class PortfolioChatTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RagService::flush();
        parent::tearDown();
    }

    /** @return array{0:Project,1:User} project + PM bound to it */
    private function makeProjectWithPm(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'Acme ERP', 'code' => 'ERP', 'status' => 'active']);
        Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY']);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id,
            'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$project, $pm];
    }

    private function enableRag(): void
    {
        SystemSetting::set('rag_enabled', '1');
        SystemSetting::set('anthropic_api_key', 'sk-test');
        SystemSetting::set('voyage_api_key', 'pa-test');
        RagService::flush();
    }

    public function test_rag_service_resolves_from_the_container(): void
    {
        // The F-01 regression: ToolRegistry's array ctor param must be bound.
        $service = app(RagService::class);

        $this->assertInstanceOf(RagService::class, $service);
        $this->assertNotEmpty(app(ToolRegistry::class)->definitions());
    }

    public function test_chat_page_is_404_when_rag_disabled(): void
    {
        RagService::flush();
        [, $pm] = $this->makeProjectWithPm();

        $this->actingAs($pm)->get(route('portfolio.chat'))->assertNotFound();
    }

    public function test_ask_returns_a_grounded_answer_end_to_end(): void
    {
        [, $pm] = $this->makeProjectWithPm();
        $this->enableRag();

        Http::fake([
            'api.voyageai.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => [0.1, 0.2, 0.3]]]]),
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'The BRS stage is in progress. [Evidence EVD-0001]']],
                'stop_reason' => 'end_turn',
            ]),
        ]);

        $response = $this->actingAs($pm)->postJson(route('portfolio.chat.ask'), [
            'question' => 'What is the status of the BRS stage?',
        ]);

        $response->assertOk()->assertJsonPath('answer', 'The BRS stage is in progress. [Evidence EVD-0001]');
    }

    public function test_ask_executes_a_tool_call_round_trip(): void
    {
        [$project, $pm] = $this->makeProjectWithPm();
        $this->enableRag();

        Http::fake([
            'api.voyageai.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => [0.1, 0.2, 0.3]]]]),
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [[
                        'type' => 'tool_use', 'id' => 'tu_1', 'name' => 'project_status',
                        'input' => ['project_id' => $project->id],
                    ]],
                    'stop_reason' => 'tool_use',
                ])
                ->push([
                    'content' => [['type' => 'text', 'text' => 'Acme ERP has 9 stages, none baselined yet.']],
                    'stop_reason' => 'end_turn',
                ]),
        ]);

        $response = $this->actingAs($pm)->postJson(route('portfolio.chat.ask'), [
            'question' => 'How far along is Acme ERP?',
        ]);

        $response->assertOk();
        $this->assertSame('project_status', $response->json('tool_calls.0.name'));
        $this->assertStringContainsString('none baselined', $response->json('answer'));
    }

    public function test_project_status_tool_refuses_a_project_outside_the_allowed_set(): void
    {
        [$project] = $this->makeProjectWithPm();

        $result = app(ProjectStatusTool::class)->handle(['project_id' => $project->id], [$project->id + 999]);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_object_search_tool_only_returns_objects_from_allowed_projects(): void
    {
        [$project, $pm] = $this->makeProjectWithPm();
        $tenant = Tenant::default();
        $other = Project::create(['tenant_id' => $tenant->id, 'name' => 'Other', 'code' => 'OTH', 'status' => 'active']);

        $graph = app(ObjectGraphService::class);
        $graph->create(ObjectType::RISK, $project->tenant_id, $project->id, 'Shared keyword payroll risk');
        $graph->create(ObjectType::RISK, $other->tenant_id, $other->id, 'Shared keyword secret risk');

        $result = app(ObjectSearchTool::class)->handle(['query' => 'Shared keyword'], [$project->id]);

        $titles = array_column($result['results'], 'title');
        $this->assertContains('Shared keyword payroll risk', $titles);
        $this->assertNotContains('Shared keyword secret risk', $titles);
    }
}
