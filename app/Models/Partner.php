<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $table = 'partners';

    protected $guarded = ['id'];

    protected $hidden = ['document', 'bank_account'];

    public function user(): mixed
    {
        return $this->belongsTo(User::class);
    }

    public function plots(): mixed
    {
        return $this->hasMany(Plot::class);
    }
}
