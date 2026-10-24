<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function actor(): mixed
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
