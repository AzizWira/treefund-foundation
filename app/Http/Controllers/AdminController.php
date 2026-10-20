<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function queue(Request $request, string $type): mixed
    {
        abort_unless($type === 'partners', 404);
        $work = null;
        $request->validate(['review' => 'nullable|integer|min:1']);
        $records = Partner::with('user')
            ->when($request->filled('review'), fn ($query) => $query->whereKey($request->integer('review')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->input('q').'%';
                $query->where(fn ($query) => $query->where('group_name', 'like', $term)->orWhere('address', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.queue', compact('type', 'records', 'work'));
    }

    public function review(Request $request, string $type, int $id, ActivityService $activity): mixed
    {
        abort_unless($type === 'partners', 404);
        $data = $request->validate(['decision' => 'required|in:approved,revision', 'review_note' => 'required_if:decision,revision|nullable|string|max:2000']);
        DB::transaction(function () use ($type, $id, $data, $activity, $request): void {
            $record = Partner::lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'pending', 409, 'Pengajuan sudah diperiksa.');
            $record->update(['status' => $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id]);
            $activity->audit($type.'.'.$data['decision'], $record, ['note' => $data['review_note'] ?? null]);
            $activity->notify($record->user_id, 'Hasil pemeriksaan pengajuan', ($data['decision'] === 'approved' ? 'Pengajuan Anda telah disetujui. ' : 'Pengajuan Anda memerlukan perbaikan. ').($data['review_note'] ?? ''), '/partner/partnership', 'partnership', ['decision' => $data['decision'], 'note' => $data['review_note'] ?? 'Pengajuan telah disetujui. Lihat rincian dan langkah berikutnya melalui akun Anda.']);
        });

        return back()->with('success', 'Keputusan verifikasi disimpan.');
    }
}
