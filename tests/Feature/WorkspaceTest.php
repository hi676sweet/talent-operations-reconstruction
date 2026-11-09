<?php

namespace Tests\Feature;

use App\Livewire\People;
use App\Models\ActivityEvent;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@crewbase.test')->firstOrFail();
    }

    public function test_guests_require_login_and_password_failure_does_not_authenticate(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->post('/login', ['email' => 'admin@crewbase.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'admin@crewbase.test', 'password' => 'DemoPass!2026'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->admin());
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_directory_filters_and_counts_only_current_organization(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(People::class)->assertSee('Mira Solis')->assertSee('Theo Park')->assertDontSee('Rowan Test')
            ->set('search', 'Design')->assertSee('Mira Solis')->assertDontSee('Theo Park')
            ->set('search', '')->set('status', 'active')->assertSee('Theo Park')->assertDontSee('Mira Solis');
    }

    public function test_pages_render_org_scoped_records_and_equipment(): void
    {
        $this->actingAs($this->admin())->get('/workers/1')->assertOk()->assertSee('Verify profile')->assertSee('Record version 2');
        $this->get('/equipment')->assertOk()->assertSee('CB-101')->assertSee('Theo Park')->assertDontSee('CB-X01');
        $this->get('/activity')->assertOk()->assertSee('Mira Solis')->assertDontSee('Rowan Test');
    }

    public function test_foreign_worker_tasks_and_equipment_are_not_accessible(): void
    {
        $this->actingAs($this->admin());
        $this->get('/workers/4')->assertNotFound();
        $this->post('/workers/4/transition', ['version' => 1, 'status' => 'onboarding'])->assertNotFound();
        $this->post('/workers/1/tasks/13/complete', ['version' => 2])->assertNotFound();
        $this->post('/workers/1/equipment', ['version' => 2, 'asset_id' => 4])->assertNotFound();
        $this->assertSame(2, Worker::find(1)->version);
    }

    public function test_viewer_cannot_mutate_any_workflow_even_with_forged_posts(): void
    {
        $viewer = User::where('email', 'viewer@crewbase.test')->firstOrFail();
        $before = ActivityEvent::count();
        $this->actingAs($viewer)->get('/workers/1')->assertOk()->assertSee('Viewer access')->assertDontSee('aria-label="Complete Verify profile"', false);
        $this->get('/workers/new')->assertForbidden();
        $routes = [
            '/workers' => ['name' => 'Forged', 'title' => 'Test', 'team' => 'Test', 'location' => 'Test'],
            '/workers/1/transition' => ['version' => 2, 'status' => 'active'],
            '/workers/1/tasks/1/complete' => ['version' => 2],
            '/workers/1/equipment' => ['version' => 2, 'asset_id' => 1],
            '/workers/2/equipment/2/return' => ['version' => 6],
        ];
        foreach ($routes as $route => $data) {
            $this->post($route, $data)->assertForbidden();
        }
        $this->assertSame($before, ActivityEvent::count());
    }

    public function test_creation_validates_fields_and_ignores_injected_org_status_and_version(): void
    {
        $this->actingAs($this->admin())->post('/workers', ['name' => ''])->assertSessionHasErrors(['name', 'title', 'team', 'location']);
        $this->post('/workers', ['name' => 'Safe Demo', 'title' => 'Tester', 'team' => 'Quality', 'location' => 'Remote', 'organization_id' => 2, 'status' => 'active', 'version' => 99])->assertRedirect('/workers/5');
        $this->assertDatabaseHas('workers', ['id' => 5, 'organization_id' => 1, 'status' => 'draft', 'version' => 1]);
    }

    public function test_json_invalid_transition_and_stale_edit_are_validation_errors(): void
    {
        $this->actingAs($this->admin())->postJson('/workers/1/transition', ['version' => 2, 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson('/workers/1/tasks/1/complete', ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->postJson('/workers/1/tasks/1/complete', ['version' => 2])->assertRedirect('/workers/1');
        $this->assertSame(3, Worker::find(1)->version);
    }

    public function test_worker_names_are_escaped_in_server_rendered_views(): void
    {
        Worker::find(1)->update(['name' => '<script>alert(1)</script>']);
        $this->actingAs($this->admin())->get('/workers/1')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
