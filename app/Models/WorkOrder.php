<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    protected $table = 'work_orders';

    protected $guarded = ['id'];

    public function getProgressStatusAttribute(): string
    {
        $trees = $this->item->trees;
        if ($trees->isNotEmpty() && $trees->every(fn ($tree): bool => $tree->planting?->status === 'approved')) {
            return 'planted';
        }
        if ($trees->contains(fn ($tree): bool => $tree->planting?->status === 'revision')) {
            return 'revision';
        }
        if ($trees->contains(fn ($tree): bool => $tree->planting?->status === 'pending')) {
            return 'pending';
        }

        return 'waiting';
    }

    public function item(): mixed
    {
        return $this->belongsTo(AdoptionItem::class, 'adoption_item_id');
    }

    public function partner(): mixed
    {
        return $this->belongsTo(Partner::class);
    }

    public function disbursements(): mixed
    {
        return $this->hasMany(Disbursement::class);
    }
}
