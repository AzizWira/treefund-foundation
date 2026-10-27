<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Adoption extends Model
{
    protected $table = 'adoptions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'paid_at' => 'datetime', 'is_demo' => 'boolean'];
    }

    protected $hidden = ['snap_token', 'transaction_id'];

    public function user(): mixed
    {
        return $this->belongsTo(User::class);
    }

    public function items(): mixed
    {
        return $this->hasMany(AdoptionItem::class);
    }

    public function certificate(): mixed
    {
        return $this->hasOne(Certificate::class);
    }
}
