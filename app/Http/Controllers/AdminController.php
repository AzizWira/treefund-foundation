<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\Partner;
use App\Models\Plot;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

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

    public function catalog(Request $request): mixed
    {
        return view('admin.catalog', [
            'offers' => $this->filteredRecords($request, Offer::with('plot.partner')->latest()->get(), fn ($offer) => $offer->species.' '.$offer->plot->name.' '.$offer->plot->partner->group_name, 15, fn ($offer) => $offer->active ? 'active' : 'inactive'),
            'plots' => Plot::where('status', 'approved')->whereHas('partner', fn ($query) => $query->where('status', 'approved'))->get(),
        ]);
    }

    public function saveOffer(Request $request, ActivityService $activity): mixed
    {
        $data = $request->validate(['offer_id' => 'nullable|integer', 'plot_id' => 'required|exists:plots,id', 'species' => 'required|string|max:120', 'description' => 'required|string|max:3000', 'price' => 'required|integer|min:1000|max:100000000', 'active' => 'nullable|boolean', 'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $newPhotoPath = null;
        try {
            DB::transaction(function () use ($request, $data, $activity, &$newPhotoPath): void {
                $plot = Plot::lockForUpdate()->findOrFail($data['plot_id']);
                $partner = Partner::whereKey($plot->partner_id)->lockForUpdate()->firstOrFail();
                if ($plot->status !== 'approved' || $partner->status !== 'approved') {
                    throw ValidationException::withMessages(['plot_id' => 'Lahan dan Mitra harus terverifikasi.']);
                }
                $offer = isset($data['offer_id']) ? Offer::lockForUpdate()->findOrFail($data['offer_id']) : new Offer;
                unset($data['offer_id'], $data['photo']);
                $data['active'] = $request->boolean('active');
                if ($request->hasFile('photo')) {
                    $newPhotoPath = $request->file('photo')->store('offers', 'public');
                    $data['photo'] = $newPhotoPath;
                }
                $offer->fill($data)->save();
                $activity->audit('catalog.saved', $offer);
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                try {
                    if (! Offer::where('photo', $newPhotoPath)->exists()) {
                        Storage::disk('public')->delete($newPhotoPath);
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            throw $exception;
        }

        return back()->with('success', 'Katalog disimpan. Riwayat transaksi tetap utuh.');
    }
}
