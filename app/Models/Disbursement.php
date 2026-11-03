<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disbursement extends Model
{
    protected $table = 'disbursements';

    protected $guarded = ['id'];

    public function workOrder(): mixed
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function rule(): mixed
    {
        return $this->belongsTo(TerminRule::class, 'termin_rule_id');
    }
}
