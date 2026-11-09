<?php

namespace App\Livewire;

use App\Models\Worker;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class People extends Component
{
    public string $search = '';

    public string $status = '';

    public function render()
    {
        $base = Worker::where('organization_id', Auth::user()->organization_id);
        $counts = (clone $base)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $query = (clone $base)->withCount(['tasks as required_tasks_count' => fn ($q) => $q->where('required', true), 'tasks as completed_tasks_count' => fn ($q) => $q->where('required', true)->whereNotNull('completed_at')]);
        if ($this->search !== '') {
            $search = mb_substr($this->search, 0, 100);
            $query->where(fn ($q) => $q->whereLike('name', '%'.$search.'%')->orWhereLike('team', '%'.$search.'%')->orWhereLike('title', '%'.$search.'%'));
        }
        if (in_array($this->status, Worker::STATUSES, true)) {
            $query->where('status', $this->status);
        }

        return view('livewire.people', ['workers' => $query->orderBy('name')->get(), 'counts' => $counts]);
    }
}
