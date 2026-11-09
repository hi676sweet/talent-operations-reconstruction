<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\Equipment;
use App\Models\User;
use App\Models\Worker;
use App\Services\PeopleOperations;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PeopleOperationsTest extends TestCase
{
    use RefreshDatabase;

    private PeopleOperations $ops;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->ops = app(PeopleOperations::class);
        $this->admin = User::where('email', 'admin@crewbase.test')->firstOrFail();
    }

    private function reject(callable $operation, string $fragment): void
    {
        try {
            $operation();
            $this->fail('Expected validation rejection.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($fragment, $e->getMessage());
        }
    }

    public function test_new_worker_has_required_tasks_and_atomic_creation_event(): void
    {
        $worker = $this->ops->create($this->admin, ['name' => 'Avery Demo', 'title' => 'Designer', 'team' => 'Design', 'location' => 'Remote']);
        $this->assertSame('draft', $worker->status);
        $this->assertSame(4, $worker->tasks()->where('required', true)->count());
        $this->assertDatabaseHas('activity_events', ['worker_id' => $worker->id, 'action' => 'worker_created', 'actor_id' => $this->admin->id]);
    }

    public function test_required_onboarding_tasks_gate_activation_without_version_or_audit_change(): void
    {
        $worker = Worker::findOrFail(1);
        $before = ActivityEvent::count();
        $this->reject(fn () => $this->ops->transition($this->admin, 1, $worker->version, 'active'), 'required onboarding');
        $this->assertSame($worker->version, $worker->fresh()->version);
        $this->assertSame('onboarding', $worker->fresh()->status);
        $this->assertSame($before, ActivityEvent::count());
    }

    public function test_exact_lifecycle_after_tasks_and_equipment_return(): void
    {
        $w = Worker::findOrFail(1);
        $w = $this->ops->assign($this->admin, $w->id, $w->version, 1);
        foreach ($w->tasks()->where('phase', 'onboarding')->get() as $task) {
            $w = $this->ops->completeTask($this->admin, $w->id, $w->version, $task->id);
        }
        $w = $this->ops->transition($this->admin, $w->id, $w->version, 'active');
        $this->assertSame('active', $w->status);
        $w = $this->ops->transition($this->admin, $w->id, $w->version, 'offboarding');
        $this->reject(fn () => $this->ops->transition($this->admin, $w->id, $w->version, 'closed'), 'required offboarding');
        foreach ($w->tasks()->where('phase', 'offboarding')->get() as $task) {
            $w = $this->ops->completeTask($this->admin, $w->id, $w->version, $task->id);
        }
        $this->reject(fn () => $this->ops->transition($this->admin, $w->id, $w->version, 'closed'), 'Return all assigned');
        $w = $this->ops->returnEquipment($this->admin, $w->id, $w->version, 1);
        $w = $this->ops->transition($this->admin, $w->id, $w->version, 'closed');
        $this->assertSame('closed', $w->status);
        $this->assertNull(Equipment::find(1)->worker_id);
        $this->assertDatabaseHas('activity_events', ['worker_id' => $w->id, 'action' => 'status_changed', 'detail' => 'Offboarding → Closed']);
        $this->reject(fn () => $this->ops->transition($this->admin, $w->id, $w->version, 'draft'), 'not allowed');
    }

    public function test_cannot_skip_reverse_or_repeat_lifecycle_stages(): void
    {
        $w = Worker::findOrFail(3);
        foreach (['active', 'draft', 'closed'] as $status) {
            $this->reject(fn () => $this->ops->transition($this->admin, 3, $w->version, $status), 'not allowed');
        }
        $this->assertSame(1, $w->fresh()->version);
    }

    public function test_stale_tab_cannot_complete_another_task_or_create_extra_event(): void
    {
        $w = Worker::findOrFail(1);
        $tasks = $w->tasks()->where('phase', 'onboarding')->get();
        $this->ops->completeTask($this->admin, 1, $w->version, $tasks[0]->id);
        $before = ActivityEvent::count();
        $this->reject(fn () => $this->ops->completeTask($this->admin, 1, $w->version, $tasks[1]->id), 'another tab');
        $this->assertNull($tasks[1]->fresh()->completed_at);
        $this->assertSame($before, ActivityEvent::count());
        $this->assertSame($w->version + 1, $w->fresh()->version);
    }

    public function test_competing_workers_cannot_allocate_the_same_equipment_and_loser_rolls_back(): void
    {
        // Two independent worker snapshots both saw CB-101 available.
        $first = Worker::findOrFail(1);
        $second = Worker::findOrFail(2);
        $this->assertNull(Equipment::find(1)->worker_id);
        $this->ops->assign($this->admin, $first->id, $first->version, 1);
        $before = ActivityEvent::count();
        $this->reject(fn () => $this->ops->assign($this->admin, $second->id, $second->version, 1), 'just assigned');
        $this->assertSame($first->id, Equipment::find(1)->worker_id);
        $this->assertSame($second->version, $second->fresh()->version);
        $this->assertSame($before, ActivityEvent::count());
    }

    public function test_failed_audit_write_rolls_back_worker_version_and_asset_assignment(): void
    {
        DB::unprepared("CREATE TRIGGER reject_test_event BEFORE INSERT ON activity_events BEGIN SELECT RAISE(ABORT, 'Forced event failure'); END");
        $w = Worker::findOrFail(1);
        $before = ActivityEvent::count();
        try {
            $this->ops->assign($this->admin, 1, $w->version, 1);
            $this->fail('Audit failure should abort the transaction.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Forced event failure', $e->getMessage());
        }
        $this->assertNull(Equipment::find(1)->worker_id);
        $this->assertSame($w->version, $w->fresh()->version);
        $this->assertSame($before, ActivityEvent::count());
    }

    public function test_tasks_only_complete_in_matching_phase_and_once(): void
    {
        $w = Worker::findOrFail(1);
        $later = $w->tasks()->where('phase', 'offboarding')->first();
        $this->reject(fn () => $this->ops->completeTask($this->admin, 1, $w->version, $later->id), 'matching lifecycle');
        $task = $w->tasks()->where('phase', 'onboarding')->first();
        $w = $this->ops->completeTask($this->admin, 1, $w->version, $task->id);
        $this->reject(fn () => $this->ops->completeTask($this->admin, 1, $w->version, $task->id), 'already complete');
        $this->assertSame($this->admin->id, $task->fresh()->completed_by);
    }

    public function test_draft_worker_cannot_receive_equipment_and_other_workers_cannot_return_it(): void
    {
        $this->reject(fn () => $this->ops->assign($this->admin, 3, 1, 1), 'only be assigned');
        $w = Worker::findOrFail(1);
        $this->actingAs($this->admin)->post('/workers/1/equipment/2/return', ['version' => $w->version])->assertNotFound();
        $this->assertSame(2, Equipment::find(2)->worker_id);
        $this->assertSame($w->version, $w->fresh()->version);
    }

    public function test_database_rejects_activity_updates_and_deletes(): void
    {
        foreach (['update' => fn () => DB::table('activity_events')->where('id', 1)->update(['detail' => 'edited']), 'delete' => fn () => DB::table('activity_events')->where('id', 1)->delete()] as $operation) {
            try {
                $operation();
                $this->fail('Append-only database trigger should reject writes.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage());
            }
        }
        $this->assertDatabaseHas('activity_events', ['id' => 1, 'detail' => 'Created draft worker record.']);
    }

    public function test_database_rejects_equipment_with_cross_organization_owner(): void
    {
        try {
            DB::table('equipment')->where('id', 1)->update(['worker_id' => 4]);
            $this->fail('Database should protect equipment organization.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('organization mismatch', $e->getMessage());
        }
        $this->assertNull(Equipment::find(1)->worker_id);
    }
}
