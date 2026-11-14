<?php

namespace App\Services;

use App\Models\Disbursement;
use App\Models\TerminRule;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisbursementService
{
    public function eligible(WorkOrder $work, TerminRule $rule): bool
    {
        $trees = $work->item->trees;
        if ($trees->isEmpty()) {
            return false;
        }

        return match ($rule->milestone) {
            'paid' => $work->item->adoption->status === 'paid',
            'planted' => $trees->every(fn ($t) => $t->planting?->status === 'approved'),
            'monitored' => $trees->every(fn ($t) => $t->monitorings()->where('status', 'approved')->exists()),
            default => false,
        };
    }

    public function submit(WorkOrder $work, TerminRule $rule): Disbursement
    {
        return DB::transaction(function () use ($work, $rule) {
            $work = WorkOrder::lockForUpdate()->findOrFail($work->id);
            if (! $this->eligible($work, $rule)) {
                throw ValidationException::withMessages(['termin_rule_id' => 'Milestone termin belum terpenuhi.']);
            }
            $amount = intdiv($work->allocated_amount * $rule->percentage, 100);
            $current = Disbursement::where('work_order_id', $work->id)->where('termin_rule_id', $rule->id)->first();
            if ($current && $current->status !== 'revision') {
                throw ValidationException::withMessages(['termin_rule_id' => 'Termin ini sudah pernah diajukan.']);
            }
            $used = $work->disbursements()->when($current, fn ($q) => $q->where('id', '!=', $current->id))->whereIn('status', ['pending', 'approved', 'paid'])->sum('amount');
            if ($amount < 1 || $used + $amount > $work->allocated_amount) {
                throw ValidationException::withMessages(['termin_rule_id' => 'Total pencairan melebihi alokasi dana.']);
            }
            $row = Disbursement::updateOrCreate(['work_order_id' => $work->id, 'termin_rule_id' => $rule->id], ['amount' => $amount, 'status' => 'pending', 'note' => null]);
            app(ActivityService::class)->audit('disbursement.submitted', $row);
            app(ActivityService::class)->notifyAdmins('Pengajuan pencairan baru', 'Mitra mengajukan '.$rule->name.'.', '/admin/disbursements');

            return $row;
        });
    }
}
