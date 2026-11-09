<?php

namespace App\Http\Controllers;

use App\Models\ActivityEvent;
use App\Models\Equipment;
use App\Services\PeopleOperations;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function create(Request $request)
    {
        abort_unless($request->user()->canManage(), 403);

        return view('workers.create');
    }

    public function store(Request $request, PeopleOperations $operations)
    {
        abort_unless($request->user()->canManage(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'title' => ['required', 'string', 'max:100'],
            'team' => ['required', 'string', 'max:60'], 'location' => ['required', 'string', 'max:80'],
        ]);
        $worker = $operations->create($request->user(), $data);

        return to_route('workers.show', $worker->id)->with('success', 'Worker created. Start onboarding when you are ready.');
    }

    public function show(Request $request, PeopleOperations $operations, int $id)
    {
        $worker = $operations->find($request->user(), $id)->load(['tasks', 'equipment', 'events.actor']);
        $available = Equipment::where('organization_id', $request->user()->organization_id)->whereNull('worker_id')->orderBy('code')->get();

        return view('workers.show', compact('worker', 'available'));
    }

    public function equipment(Request $request)
    {
        $equipment = Equipment::where('organization_id', $request->user()->organization_id)->with('worker')->orderBy('code')->get();

        return view('equipment', compact('equipment'));
    }

    public function activity(Request $request)
    {
        $events = ActivityEvent::where('organization_id', $request->user()->organization_id)->with(['worker', 'actor'])->latest('id')->paginate(30);

        return view('activity', compact('events'));
    }
}
