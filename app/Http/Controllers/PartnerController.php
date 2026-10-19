<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Services\ActivityService;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function partnership(Request $request): mixed
    {
        return view('partner.partnership', ['partner' => $request->user()->partner]);
    }

    public function savePartnership(Request $request, ActivityService $activity): mixed
    {
        $partner = $request->user()->partner;
        abort_if($partner && $partner->status === 'approved', 409, 'Profil telah disetujui. Hubungi Admin untuk perubahan legalitas.');
        $data = $request->validate(['group_name' => 'required|string|max:150', 'address' => 'required|string|max:1000', 'bank_name' => 'required|string|max:80', 'bank_account' => 'required|regex:/^[0-9]{5,30}$/', 'account_holder' => 'required|string|max:150', 'document' => ($partner ? 'nullable' : 'required').'|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        if ($request->hasFile('document')) {
            $data['document'] = $request->file('document')->store('legal', 'local');
        } else {
            unset($data['document']);
        }
        $partner = Partner::updateOrCreate(['user_id' => $request->user()->id], array_merge($data, ['status' => 'pending', 'review_note' => null]));
        $activity->audit('partnership.submitted', $partner);
        $activity->notifyAdmins('Pengajuan mitra baru', $partner->group_name, '/admin/partners');

        return back()->with('success', 'Profil dan legalitas dikirim untuk diverifikasi.');
    }

}
