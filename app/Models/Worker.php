<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    public const STATUSES = ['draft', 'onboarding', 'active', 'offboarding', 'closed'];

    protected $guarded = [];

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkerTask::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ActivityEvent::class);
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join('');
    }
}
