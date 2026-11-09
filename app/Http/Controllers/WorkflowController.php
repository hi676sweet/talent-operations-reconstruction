<?php

namespace App\Http\Controllers;

use App\Services\PeopleOperations;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    private function version(Request $request): int
    {
        abort_unless($request->user()->canManage(), 403);

        return (int) $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
    }

    public function transition(Request $request, PeopleOperations $operations, int $id)
    {
        $version = $this->version($request);
        $data = $request->validate(['status' => ['required', 'string']]);
        $operations->transition($request->user(), $id, $version, $data['status']);

        return to_route('workers.show', $id)->with('success', 'Lifecycle updated.');
    }

    public function complete(Request $request, PeopleOperations $operations, int $id, int $task)
    {
        $operations->completeTask($request->user(), $id, $this->version($request), $task);

        return to_route('workers.show', $id)->with('success', 'Task completed.');
    }

    public function assign(Request $request, PeopleOperations $operations, int $id)
    {
        $version = $this->version($request);
        $data = $request->validate(['asset_id' => ['required', 'integer', 'min:1']]);
        $operations->assign($request->user(), $id, $version, (int) $data['asset_id']);

        return to_route('workers.show', $id)->with('success', 'Equipment assigned.');
    }

    public function returnEquipment(Request $request, PeopleOperations $operations, int $id, int $asset)
    {
        $operations->returnEquipment($request->user(), $id, $this->version($request), $asset);

        return to_route('workers.show', $id)->with('success', 'Equipment returned to inventory.');
    }
}
