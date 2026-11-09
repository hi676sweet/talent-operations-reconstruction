<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $guarded = [];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
