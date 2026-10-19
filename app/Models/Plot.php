<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plot extends Model
{
    protected $table = 'plots';

    protected $guarded = ['id'];

    public function partner(): mixed
    {
        return $this->belongsTo(Partner::class);
    }

    public function offers(): mixed
    {
        return $this->hasMany(Offer::class);
    }

    public function getAvailableAttribute(): int
    {
        return max(0, $this->capacity - $this->reserved - $this->confirmed);
    }
}
