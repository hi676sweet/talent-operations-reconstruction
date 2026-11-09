<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\Equipment;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeopleOperations
{
    public function find(User $actor, int $id): Worker
    {
        return Worker::where('organization_id', $actor->organization_id)->findOrFail($id);
    }

    public function create(User $actor, array $data): Worker
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $data) {
            $worker = Worker::create([...$data, 'organization_id' => $actor->organization_id, 'status' => 'draft', 'version' => 1]);
            foreach (['onboarding' => ['Verify profile', 'Arrange orientation'], 'offboarding' => ['Hand over work', 'Revoke workspace access']] as $phase => $names) {
                foreach ($names as $name) {
                    $worker->tasks()->create(['phase' => $phase, 'name' => $name, 'required' => true]);
                }
            }
            $this->event($actor, $worker, 'worker_created', 'Created draft worker record.');

            return $worker;
        });
    }

    public function transition(User $actor, int $id, int $version, string $status): Worker
    {
        return $this->mutate($actor, $id, $version, function (Worker $worker) use ($actor, $status) {
            $next = ['draft' => 'onboarding', 'onboarding' => 'active', 'active' => 'offboarding', 'offboarding' => 'closed'];
            if (($next[$worker->status] ?? null) !== $status) {
                $this->fail('status', 'This lifecycle transition is not allowed.');
            }
            if (in_array($status, ['active', 'closed'], true)) {
                $phase = $status === 'active' ? 'onboarding' : 'offboarding';
                if ($worker->tasks()->where('phase', $phase)->where('required', true)->whereNull('completed_at')->exists()) {
                    $this->fail('status', 'Complete all required '.$phase.' tasks first.');
                }
            }
            if ($status === 'closed' && $worker->equipment()->exists()) {
                $this->fail('status', 'Return all assigned equipment before closing this worker.');
            }
            $old = $worker->status;
            $worker->update(['status' => $status]);
            $this->event($actor, $worker, 'status_changed', ucfirst($old).' → '.ucfirst($status));
        });
    }

    public function completeTask(User $actor, int $id, int $version, int $taskId): Worker
    {
        return $this->mutate($actor, $id, $version, function (Worker $worker) use ($actor, $taskId) {
            $task = $worker->tasks()->findOrFail($taskId);
            if ($task->phase !== $worker->status) {
                $this->fail('task', 'Tasks can only be completed in their matching lifecycle phase.');
            }
            if ($task->completed_at !== null) {
                $this->fail('task', 'This task is already complete.');
            }
            $task->update(['completed_at' => now(), 'completed_by' => $actor->id]);
            $this->event($actor, $worker, 'task_completed', $task->name);
        });
    }

    public function assign(User $actor, int $id, int $version, int $assetId): Worker
    {
        return $this->mutate($actor, $id, $version, function (Worker $worker) use ($actor, $assetId) {
            if (! in_array($worker->status, ['onboarding', 'active'], true)) {
                $this->fail('equipment', 'Equipment can only be assigned during onboarding or active work.');
            }
            $asset = Equipment::where('organization_id', $actor->organization_id)->findOrFail($assetId);
            // Conditional update is the allocation gate, even across two different workers.
            $changed = Equipment::whereKey($asset->id)->whereNull('worker_id')->update(['worker_id' => $worker->id, 'assigned_at' => now(), 'updated_at' => now()]);
            if ($changed !== 1) {
                $this->fail('equipment', 'This equipment was just assigned. Refresh and choose another item.');
            }
            $this->event($actor, $worker, 'equipment_assigned', $asset->code.' · '.$asset->name);
        });
    }

    public function returnEquipment(User $actor, int $id, int $version, int $assetId): Worker
    {
        return $this->mutate($actor, $id, $version, function (Worker $worker) use ($actor, $assetId) {
            $asset = $worker->equipment()->where('organization_id', $actor->organization_id)->findOrFail($assetId);
            $asset->update(['worker_id' => null, 'assigned_at' => null]);
            $this->event($actor, $worker, 'equipment_returned', $asset->code.' · '.$asset->name);
        });
    }

    private function mutate(User $actor, int $id, int $version, callable $operation): Worker
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $id, $version, $operation) {
            $worker = $this->find($actor, $id);
            // Compare-and-swap version guards stale tabs and serializes every worker change.
            $changed = Worker::whereKey($worker->id)->where('version', $version)->update(['version' => DB::raw('version + 1'), 'updated_at' => now()]);
            if ($changed !== 1) {
                $this->fail('version', 'This worker changed in another tab. Refresh before trying again.');
            }
            $worker->refresh();
            $operation($worker);

            return $worker->refresh();
        }, 3);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->canManage(), 403, 'Your viewer account cannot change records.');
    }

    private function event(User $actor, Worker $worker, string $action, string $detail): void
    {
        ActivityEvent::create(['organization_id' => $worker->organization_id, 'worker_id' => $worker->id, 'actor_id' => $actor->id, 'action' => $action, 'detail' => $detail, 'created_at' => now()]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
