<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $table = 'offers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('active', true)->whereHas('plot', fn (Builder $plots) => $plots
            ->where('status', 'approved')
            ->whereHas('partner', fn (Builder $partners) => $partners->where('status', 'approved')));
    }

    public function plot(): mixed
    {
        return $this->belongsTo(Plot::class);
    }
}
