<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Partner;
use App\Models\Plot;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PartnerController extends Controller
{
    public function partnership(Request $request): mixed
    {
        $partner = $request->user()->partner()->first();
        $documentAvailable = $partner && $partner->document && Storage::disk('local')->exists($partner->document);

        return view('partner.partnership', compact('partner', 'documentAvailable'));
    }

    public function savePartnership(Request $request, ActivityService $activity): mixed
    {
        $partner = $request->user()->partner()->first();
        abort_if($partner && $partner->status === 'approved', 409, 'Profil telah disetujui. Hubungi Admin untuk perubahan legalitas.');
        $documentRequired = ! $partner || ! $partner->document || ! Storage::disk('local')->exists($partner->document);
        $data = $request->validate(['group_name' => 'required|string|max:150', 'address' => 'required|string|max:1000', 'bank_name' => 'required|string|max:80', 'bank_account' => 'required|regex:/^[0-9]{5,30}$/', 'account_holder' => 'required|string|max:150', 'document' => ($documentRequired ? 'required' : 'nullable').'|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $newDocumentPath = null;
        if ($request->hasFile('document')) {
            $newDocumentPath = $request->file('document')->store('legal', 'local');
            $data['document'] = $newDocumentPath;
        } else {
            unset($data['document']);
        }

        try {
            $partner = DB::transaction(function () use ($request, $data, $activity): Partner {
                $currentPartner = Partner::where('user_id', $request->user()->id)->lockForUpdate()->first();
                abort_if($currentPartner?->status === 'approved', 409, 'Profil telah disetujui. Hubungi Admin untuk perubahan legalitas.');
                $partner = Partner::updateOrCreate(['user_id' => $request->user()->id], array_merge($data, [
                    'status' => 'pending', 'review_note' => null, 'reviewed_by' => null,
                ]));
                $activity->audit('partnership.submitted', $partner);
                $activity->notifyAdmins('Pengajuan mitra baru', $partner->group_name, '/admin/partners');

                return $partner;
            });
        } catch (Throwable $exception) {
            if ($newDocumentPath !== null) {
                try {
                    $documentRetained = Partner::where('user_id', $request->user()->id)->where('document', $newDocumentPath)->exists();
                    if (! $documentRetained) {
                        Storage::disk('local')->delete($newDocumentPath);
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
        $request->user()->setRelation('partner', $partner);

        return back()->with('success', 'Profil dan legalitas dikirim untuk diverifikasi.');
    }

    public function plots(Request $request): mixed
    {
        $partner = $request->user()->partner()->first();

        return view('partner.plots', ['partner' => $partner, 'plots' => $this->filteredRecords($request, Plot::where('partner_id', $partner?->id ?? 0)->latest()->get(), fn ($plot) => $plot->name.' '.$plot->location.' '.$plot->proposed_species, 8, fn ($plot) => $plot->status)]);
    }

    public function savePlot(Request $request, ActivityService $activity): mixed
    {
        $partner = $request->user()->partner()->first();
        abort_unless($partner, 403, 'Lengkapi pengajuan profil kemitraan terlebih dahulu.');
        abort_unless($partner->status === 'approved', 403, 'Kemitraan harus disetujui terlebih dahulu.');
        $data = $request->validate(['plot_id' => 'nullable|integer', 'name' => 'required|string|max:150', 'location' => 'required|string|max:190', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'capacity' => 'required|integer|min:1|max:100000', 'proposed_species' => 'required|string|max:150']);
        DB::transaction(function () use ($request, $partner, $data, $activity): void {
            $partner = Partner::whereKey($partner->id)->lockForUpdate()->firstOrFail();
            abort_unless($partner->status === 'approved', 403, 'Kemitraan harus disetujui terlebih dahulu.');
            $plot = isset($data['plot_id'])
                ? Plot::where('partner_id', $partner->id)->lockForUpdate()->findOrFail($data['plot_id'])
                : new Plot(['partner_id' => $partner->id]);
            abort_if($plot->exists && $plot->status !== 'revision', 409, 'Hanya pengajuan yang dikembalikan dapat diperbaiki.');
            if ($plot->exists && (int) $data['capacity'] < $plot->reserved + $plot->confirmed) {
                throw ValidationException::withMessages(['capacity' => 'Kapasitas tidak boleh lebih kecil dari total pohon yang sudah dicadangkan dan dikonfirmasi.']);
            }
            unset($data['plot_id']);
            $plot->fill(array_merge($data, ['status' => 'pending', 'review_note' => null, 'reviewed_by' => null]))->save();
            $activity->audit('plot.submitted', $plot);
            $activity->notifyAdmins('Pengajuan lahan baru', $plot->name, '/admin/plots');
        });

        return back()->with('success', 'Lahan dikirim untuk verifikasi.');
    }


    public function members(Request $request): mixed
    {
        $partner = $request->user()->partner()->first();
        $request->validate(['q' => 'nullable|string|max:190']);
        $members = $this->filteredRecords($request, Member::where('partner_id', $partner?->id ?? 0)->orderBy('id')->get(), fn ($member) => $member->name.' '.$member->position.' '.$member->phone, 8);

        return view('partner.members', compact('partner', 'members'));
    }

    public function saveMember(Request $request): mixed
    {
        $partner = $request->user()->partner()->first();
        abort_unless($partner, 403, 'Lengkapi pengajuan profil kemitraan terlebih dahulu.');
        $data = $request->validate(['name' => 'required|string|max:120', 'position' => 'required|string|max:100', 'phone' => 'nullable|string|max:25']);
        $partner->members()->create($data);

        return back()->with('success', 'Anggota kelompok ditambahkan.');
    }

    public function updateMember(Request $request, Member $member): mixed
    {
        abort_unless($member->partner_id === $request->user()->partner()->value('id'), 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'position' => 'required|string|max:100', 'phone' => 'nullable|string|max:25']);
        $member->update($data);

        return back()->with('success', 'Data anggota diperbarui.');
    }

    public function deleteMember(Request $request, Member $member): mixed
    {
        abort_unless($member->partner_id === $request->user()->partner()->value('id'), 403);
        $member->delete();

        return back()->with('success', 'Anggota dihapus.');
    }
}
