<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdoptionItem extends Model
{
    protected $table = 'adoption_items';

    protected $guarded = ['id'];

    public function adoption(): mixed
    {
        return $this->belongsTo(Adoption::class);
    }

    public function offer(): mixed
    {
        return $this->belongsTo(Offer::class);
    }

    public function trees(): mixed
    {
        return $this->hasMany(Tree::class);
    }

    public function workOrder(): mixed
    {
        return $this->hasOne(WorkOrder::class);
    }
}
