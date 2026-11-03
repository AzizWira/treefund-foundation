<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrder extends Model
{
    protected $table = 'work_orders';

    protected $guarded = ['id'];

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
