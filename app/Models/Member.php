<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    protected $table = 'members';

    protected $guarded = ['id'];

    public function partner(): mixed
    {
        return $this->belongsTo(Partner::class);
    }
}
