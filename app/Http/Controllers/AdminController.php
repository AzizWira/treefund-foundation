<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\Plot;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function queue(Request $request, string $type): mixed
    {
        $map = ['partners' => Partner::class, 'plots' => Plot::class];
        abort_unless(isset($map[$type]), 404);
        $work = null;
        $request->validate(['review' => 'nullable|integer|min:1']);
        $records = $map[$type]::with($type === 'partners' ? ['user'] : ['partner'])
            ->when($request->filled('review'), fn ($query) => $query->whereKey($request->integer('review')))
            ->when($request->filled('q'), function ($query) use ($request, $type): void {
                $term = '%'.$request->input('q').'%';
                $query->where(function ($query) use ($term, $type): void {
                    if ($type === 'partners') {
                        $query->where('group_name', 'like', $term)->orWhere('address', 'like', $term);
                    } else {
                        $query->where('name', 'like', $term)->orWhere('location', 'like', $term)
                            ->orWhereHas('partner', fn ($partners) => $partners->where('group_name', 'like', $term));
                    }
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.queue', compact('type', 'records', 'work'));
    }

    public function review(Request $request, string $type, int $id, ActivityService $activity): mixed
    {
        $map = ['partners' => Partner::class, 'plots' => Plot::class];
        abort_unless(isset($map[$type]), 404);
        $data = $request->validate(['decision' => 'required|in:approved,revision', 'review_note' => 'required_if:decision,revision|nullable|string|max:2000']);
        DB::transaction(function () use ($map, $type, $id, $data, $activity, $request): void {
            $record = $map[$type]::lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'pending', 409, 'Pengajuan sudah diperiksa.');
            if ($type === 'plots') {
                abort_unless($record->partner->status === 'approved', 409, 'Profil Mitra belum disetujui.');
            }
            $record->update(['status' => $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id]);
            $activity->audit($type.'.'.$data['decision'], $record, ['note' => $data['review_note'] ?? null]);
            $activity->notify($type === 'partners' ? $record->user_id : $record->partner->user_id, 'Hasil pemeriksaan pengajuan', ($data['decision'] === 'approved' ? 'Pengajuan Anda telah disetujui. ' : 'Pengajuan Anda memerlukan perbaikan. ').($data['review_note'] ?? ''), $type === 'partners' ? '/partner/partnership' : '/partner/plots', 'partnership', ['decision' => $data['decision'], 'note' => $data['review_note'] ?? 'Pengajuan telah disetujui. Lihat rincian dan langkah berikutnya melalui akun Anda.']);
        });

        return back()->with('success', 'Keputusan verifikasi disimpan.');
    }
}
