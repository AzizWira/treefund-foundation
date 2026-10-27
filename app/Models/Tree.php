<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tree extends Model
{
    protected $table = 'trees';

    protected $guarded = ['id'];

    public function item(): mixed
    {
        return $this->belongsTo(AdoptionItem::class, 'adoption_item_id');
    }
}
