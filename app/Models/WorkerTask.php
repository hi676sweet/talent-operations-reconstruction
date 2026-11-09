<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerTask extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'completed_at' => 'datetime'];
    }
}
