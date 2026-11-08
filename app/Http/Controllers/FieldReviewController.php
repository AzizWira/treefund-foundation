<?php

namespace App\Http\Controllers;

use App\Models\Monitoring;
use App\Models\Planting;
use App\Models\Tree;
use App\Models\WorkOrder;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldReviewController extends Controller
{
    public function plantings(Request $request): mixed
    {
        $filters = $request->validate(['review' => 'nullable|integer|min:1', 'work_order_id' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:190', 'status' => 'nullable|in:pending,approved,revision']);
        $work = isset($filters['work_order_id'])
            ? WorkOrder::with('partner', 'item.offer.plot')->findOrFail($filters['work_order_id'])
            : null;
        $records = Planting::with('tree.item.offer.plot.partner')
            ->when($filters['work_order_id'] ?? null, fn ($query, $workId) => $query->whereHas('tree.item.workOrder', fn ($work) => $work->whereKey($workId)))
            ->when($filters['review'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->whereHas('tree', fn ($tree) => $tree->where(fn ($match) => $match
                ->where('code', 'like', '%'.$search.'%')
                ->orWhereHas('item.offer', fn ($offer) => $offer->where('species', 'like', '%'.$search.'%')
                    ->orWhereHas('plot', fn ($plot) => $plot->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('partner', fn ($partner) => $partner->where('group_name', 'like', '%'.$search.'%')))))))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.queue', ['records' => $records, 'type' => 'plantings', 'work' => $work]);
    }

    public function reviewPlanting(Request $request, Planting $planting, ActivityService $activity): mixed
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,revision',
            'review_note' => 'required_if:decision,revision|nullable|string|max:2000',
            'submission_version' => 'required|integer|min:1',
        ]);
        DB::transaction(function () use ($request, $planting, $activity, $data): void {
            $work = WorkOrder::lockForUpdate()->findOrFail($planting->tree->item->workOrder->id);
            Tree::lockForUpdate()->findOrFail($planting->tree_id);
            $row = Planting::lockForUpdate()->findOrFail($planting->id);
            abort_unless($row->status === 'pending' && $row->submission_version === (int) $data['submission_version'], 409, 'Bukti sudah berubah. Muat ulang sebelum memeriksa.');
            $row->update(['status' => $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id]);
            $work->refreshPlantingStatus();
            $activity->audit('plantings.'.$data['decision'], $row);
            $activity->notify($work->partner->user_id, 'Hasil verifikasi bukti tanam', $data['review_note'] ?? 'Bukti tanam telah disetujui.', '/partner/work-orders/'.$work->id);
            if ($data['decision'] === 'approved') {
                $activity->notify($row->tree->item->adoption->user_id, 'Pohon Anda sudah ditanam', 'Bukti tanam telah disetujui.', '/trees?tree='.$row->tree_id, 'field_report', ['tree_code' => $row->tree->code]);
            }
        }, 3);

        return back()->with('success', 'Keputusan bukti tanam disimpan.');
    }

    public function monitorings(Request $request): mixed
    {
        $filters = $request->validate(['review' => 'nullable|integer|min:1', 'work_order_id' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:190', 'status' => 'nullable|in:pending,approved,revision']);
        $work = isset($filters['work_order_id'])
            ? WorkOrder::with('partner', 'item.offer.plot')->findOrFail($filters['work_order_id'])
            : null;
        $records = Monitoring::with('tree.item.offer.plot.partner')
            ->when($filters['work_order_id'] ?? null, fn ($query, $workId) => $query->whereHas('tree.item.workOrder', fn ($work) => $work->whereKey($workId)))
            ->when($filters['review'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->whereHas('tree', fn ($tree) => $tree->where(fn ($match) => $match
                ->where('code', 'like', '%'.$search.'%')
                ->orWhereHas('item.offer', fn ($offer) => $offer->where('species', 'like', '%'.$search.'%')
                    ->orWhereHas('plot', fn ($plot) => $plot->where('name', 'like', '%'.$search.'%')
                        ->orWhereHas('partner', fn ($partner) => $partner->where('group_name', 'like', '%'.$search.'%')))))))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.queue', ['records' => $records, 'type' => 'monitorings', 'work' => $work]);
    }

    public function reviewMonitoring(Request $request, Monitoring $monitoring, ActivityService $activity): mixed
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,revision',
            'review_note' => 'required_if:decision,revision|nullable|string|max:2000',
            'submission_version' => 'required|integer|min:1',
        ]);
        DB::transaction(function () use ($request, $monitoring, $activity, $data): void {
            $tree = Tree::lockForUpdate()->findOrFail($monitoring->tree_id);
            abort_unless($tree->planting?->status === 'approved', 409);
            $row = Monitoring::lockForUpdate()->findOrFail($monitoring->id);
            abort_unless($row->status === 'pending' && $row->submission_version === (int) $data['submission_version'], 409, 'Laporan sudah berubah. Muat ulang sebelum memeriksa.');
            $row->update(['status' => $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id]);
            $activity->audit('monitorings.'.$data['decision'], $row);
            $activity->notify($tree->item->workOrder->partner->user_id, 'Hasil verifikasi monitoring', $data['review_note'] ?? 'Laporan monitoring telah disetujui.', '/partner/monitoring?q='.rawurlencode($tree->code));
            if ($data['decision'] === 'approved') {
                $activity->notify($row->tree->item->adoption->user_id, 'Pembaruan pohon adopsi', 'Laporan lapangan baru telah disetujui.', '/trees?tree='.$row->tree_id, 'field_report', ['tree' => $row->tree->code]);
            }
        }, 3);

        return back()->with('success', 'Keputusan monitoring disimpan.');
    }
}
