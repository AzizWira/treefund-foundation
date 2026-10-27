<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    protected $table = 'certificates';

    protected $guarded = ['id'];

    public function adoption(): mixed
    {
        return $this->belongsTo(Adoption::class);
    }
}
