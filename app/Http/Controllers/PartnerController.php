<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PartnerController extends Controller
{
    public function partnership(Request $request): mixed
    {
        return view('partner.partnership', ['partner' => $request->user()->partner()->first()]);
    }

    public function savePartnership(Request $request, ActivityService $activity): mixed
    {
        $partner = $request->user()->partner()->first();
        abort_if($partner && $partner->status === 'approved', 409, 'Profil telah disetujui. Hubungi Admin untuk perubahan legalitas.');
        $data = $request->validate(['group_name' => 'required|string|max:150', 'address' => 'required|string|max:1000', 'bank_name' => 'required|string|max:80', 'bank_account' => 'required|regex:/^[0-9]{5,30}$/', 'account_holder' => 'required|string|max:150', 'document' => ($partner ? 'nullable' : 'required').'|file|mimes:pdf,jpg,jpeg,png|max:10240']);
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
}
