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
        $filters = $request->validate(['review' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:190', 'status' => 'nullable|in:pending,approved,revision']);
        $records = Planting::with('tree.item.offer.plot')
            ->when($filters['review'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->whereHas('tree', fn ($tree) => $tree->where('code', 'like', '%'.$search.'%')))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.field-review', ['records' => $records, 'kind' => 'planting', 'heading' => 'Verifikasi Bukti Tanam']);
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
            $states = $work->item->trees()->with('planting')->get()->map(fn (Tree $tree) => $tree->planting?->status);
            $status = match (true) {
                $states->isNotEmpty() && $states->every(fn ($state) => $state === 'approved') => 'planted',
                $states->contains('revision') => 'revision',
                $states->contains('pending') => 'pending',
                default => 'waiting',
            };
            $work->update(['status' => $status]);
            $activity->audit('plantings.'.$data['decision'], $row);
        }, 3);

        return back()->with('success', 'Keputusan bukti tanam disimpan.');
    }

    public function monitorings(Request $request): mixed
    {
        $filters = $request->validate(['review' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:190', 'status' => 'nullable|in:pending,approved,revision']);
        $records = Monitoring::with('tree.item.offer.plot')
            ->when($filters['review'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->whereHas('tree', fn ($tree) => $tree->where('code', 'like', '%'.$search.'%')))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.field-review', ['records' => $records, 'kind' => 'monitoring', 'heading' => 'Monitoring Berkala']);
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
        }, 3);

        return back()->with('success', 'Keputusan monitoring disimpan.');
    }
}
