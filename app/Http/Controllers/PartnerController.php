<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Monitoring;
use App\Models\Partner;
use App\Models\Planting;
use App\Models\Plot;
use App\Models\TerminRule;
use App\Models\Tree;
use App\Models\WorkOrder;
use App\Services\ActivityService;
use App\Services\DisbursementService;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    private function partner(Request $request): Partner
    {
        $p = $request->user()->partner;
        abort_unless($p, 403, 'Lengkapi pengajuan profil kemitraan terlebih dahulu.');

        return $p;
    }

    private function ownWork(Request $request, WorkOrder $work): void
    {
        abort_unless($work->partner_id === $request->user()->partner?->id, 403);
    }

    private function ownTree(Request $request, Tree $tree): void
    {
        abort_unless($tree->item->offer->plot->partner_id === $request->user()->partner?->id, 403);
    }

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

    public function plots(Request $request): mixed
    {
        return view('partner.plots', ['partner' => $request->user()->partner, 'plots' => $this->filteredRecords($request, Plot::where('partner_id', $request->user()->partner?->id ?? 0)->latest()->get(), fn ($plot) => $plot->name.' '.$plot->location.' '.$plot->proposed_species, 8, fn ($plot) => $plot->status)]);
    }

    public function savePlot(Request $request, ActivityService $activity): mixed
    {
        $partner = $this->partner($request);
        abort_unless($partner->status === 'approved', 403, 'Kemitraan harus disetujui terlebih dahulu.');
        $data = $request->validate(['plot_id' => 'nullable|integer', 'name' => 'required|string|max:150', 'location' => 'required|string|max:190', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'capacity' => 'required|integer|min:1|max:100000', 'proposed_species' => 'required|string|max:150']);
        $plot = isset($data['plot_id']) ? Plot::where('partner_id', $partner->id)->findOrFail($data['plot_id']) : new Plot(['partner_id' => $partner->id]);
        abort_if($plot->exists && $plot->status !== 'revision', 409, 'Hanya pengajuan yang dikembalikan dapat diperbaiki.');
        unset($data['plot_id']);
        $plot->fill(array_merge($data, ['status' => 'pending', 'review_note' => null]))->save();
        $activity->audit('plot.submitted', $plot);
        $activity->notifyAdmins('Pengajuan lahan baru', $plot->name, '/admin/plots');

        return back()->with('success', 'Lahan dikirim untuk verifikasi.');
    }

    public function workOrders(Request $request): mixed
    {
        return view('partner.works', ['works' => $this->filteredRecords($request, WorkOrder::with('item.offer.plot', 'item.adoption', 'item.trees.planting')->where('partner_id', $request->user()->partner?->id ?? 0)->latest()->get(), fn ($work) => 'WO-'.$work->id.' '.$work->item->offer->species.' '.$work->item->offer->plot->name.' '.$work->item->adoption->certificate_name, 15, fn ($work) => $work->progress_status)]);
    }

    public function workOrder(Request $request, WorkOrder $work): mixed
    {
        $this->ownWork($request, $work);
        $work->load('item.offer.plot', 'item.adoption', 'item.trees.planting', 'item.trees.monitorings');

        return view('partner.work', compact('work'));
    }

    public function planting(Request $request, Tree $tree, ActivityService $activity): mixed
    {
        $this->ownTree($request, $tree);
        abort_if($tree->planting && $tree->planting->status !== 'revision', 409, 'Bukti ini sedang diperiksa atau sudah disetujui.');
        $data = $request->validate(['planted_at' => 'required|date|before_or_equal:today', 'latitude' => 'required|numeric|between:-90,90', 'longitude' => 'required|numeric|between:-180,180', 'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $data['photo'] = $request->file('photo')->store('plantings', 'local');
        $record = Planting::updateOrCreate(['tree_id' => $tree->id], array_merge($data, ['status' => 'pending', 'review_note' => null, 'reviewed_by' => null]));
        $activity->audit('planting.submitted', $record);
        $activity->notifyAdmins('Bukti tanam menunggu verifikasi', $tree->code, '/admin/plantings');

        return back()->with('success', 'Bukti penanaman berhasil dikirim.');
    }

    public function monitoring(Request $request): mixed
    {
        $trees = Tree::with('item.offer.plot', 'planting', 'monitorings')->whereHas('item.offer.plot', fn ($q) => $q->where('partner_id', $request->user()->partner?->id ?? 0))->whereHas('planting', fn ($q) => $q->where('status', 'approved'))->get();

        $trees = $this->filteredRecords($request, $trees, fn ($tree) => $tree->code.' '.$tree->item->offer->species.' '.$tree->item->offer->plot->name, 8);

        return view('partner.monitoring', compact('trees'));
    }

    public function saveMonitoring(Request $request, Tree $tree, ActivityService $activity): mixed
    {
        $this->ownTree($request, $tree);
        abort_unless($tree->planting?->status === 'approved', 409, 'Bukti tanam belum disetujui.');
        $data = $request->validate(['monitoring_id' => 'nullable|integer', 'observed_at' => 'required|date|after_or_equal:'.$tree->planting->planted_at->toDateString().'|before_or_equal:today', 'height' => 'required|numeric|min:0|max:100000', 'health' => 'required|in:healthy,care,dead', 'care_note' => 'required|string|max:2000', 'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $record = isset($data['monitoring_id']) ? $tree->monitorings()->where('status', 'revision')->findOrFail($data['monitoring_id']) : new Monitoring(['tree_id' => $tree->id]);
        unset($data['monitoring_id']);
        $data['photo'] = $request->file('photo')->store('monitorings', 'local');
        $record->fill(array_merge($data, ['status' => 'pending', 'review_note' => null, 'reviewed_by' => null]))->save();
        $activity->audit('monitoring.submitted', $record);
        $activity->notifyAdmins('Log monitoring baru', $tree->code, '/admin/monitorings');

        return back()->with('success', 'Log monitoring dikirim untuk verifikasi.');
    }

    public function funds(Request $request): mixed
    {
        $works = WorkOrder::with('item.offer.plot', 'item.trees.planting', 'item.trees.monitorings', 'disbursements.rule')->where('partner_id', $request->user()->partner?->id ?? 0)->get();
        $rules = TerminRule::orderBy('id')->get();

        $works = $this->filteredRecords($request, $works, fn ($work) => 'WO-'.$work->id.' '.$work->item->offer->species.' '.$work->item->offer->plot->name, 8);

        return view('partner.funds', compact('works', 'rules'));
    }

    public function submitFunds(Request $request, DisbursementService $service): mixed
    {
        $data = $request->validate(['work_order_id' => 'required|exists:work_orders,id', 'termin_rule_id' => 'required|exists:termin_rules,id']);
        $work = WorkOrder::findOrFail($data['work_order_id']);
        $this->ownWork($request, $work);
        $service->submit($work, TerminRule::findOrFail($data['termin_rule_id']));

        return back()->with('success', 'Pengajuan pencairan dikirim.');
    }

    public function members(Request $request): mixed
    {
        return view('partner.members', ['partner' => $request->user()->partner, 'members' => $this->filteredRecords($request, Member::where('partner_id', $request->user()->partner?->id ?? 0)->get(), fn ($member) => $member->name.' '.$member->position.' '.$member->phone, 8)]);
    }

    public function saveMember(Request $request): mixed
    {
        $p = $this->partner($request);
        $data = $request->validate(['name' => 'required|string|max:120', 'position' => 'required|string|max:100', 'phone' => 'nullable|string|max:25']);
        $p->members()->create($data);

        return back()->with('success', 'Anggota kelompok ditambahkan.');
    }

    public function deleteMember(Request $request, Member $member): mixed
    {
        abort_unless($member->partner_id === $request->user()->partner?->id, 403);
        $member->delete();

        return back()->with('success', 'Anggota dihapus.');
    }

    public function updateMember(Request $request, Member $member): mixed
    {
        abort_unless($member->partner_id === $request->user()->partner?->id, 403);
        $data = $request->validate(['name' => 'required|string|max:120', 'position' => 'required|string|max:100', 'phone' => 'nullable|string|max:25']);
        $member->update($data);

        return back()->with('success', 'Data anggota diperbarui.');
    }
}
