<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Monitoring extends Model
{
    protected $table = 'monitorings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['observed_at' => 'date'];
    }

    public function tree(): mixed
    {
        return $this->belongsTo(Tree::class);
    }
}
