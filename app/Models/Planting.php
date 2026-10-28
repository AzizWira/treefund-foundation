<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Planting extends Model
{
    protected $table = 'plantings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['planted_at' => 'date'];
    }

    public function tree(): mixed
    {
        return $this->belongsTo(Tree::class);
    }
}
