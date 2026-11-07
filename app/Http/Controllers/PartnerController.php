<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Partner;
use App\Models\Plot;
use App\Services\ActivityService;
use App\Models\Monitoring;
use App\Models\Tree;
use App\Models\WorkOrder;
use App\Models\TerminRule;
use App\Services\DisbursementService;
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

    public function workOrders(Request $request): mixed
    {
        $filters = $request->validate(['q' => 'nullable|string|max:190', 'status' => 'nullable|in:waiting,pending,revision,planted']);
        $search = trim($filters['q'] ?? '');
        $works = WorkOrder::where('partner_id', $request->user()->partner?->id ?? 0)
            ->with('item.offer.plot', 'item.adoption')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($matching) use ($search): void {
                if (preg_match('/^(?:WO-)?([0-9]+)$/i', $search, $parts)) {
                    $matching->where('id', (int) $parts[1]);
                }
                $matching->orWhereHas('item.offer', fn ($offer) => $offer->where('species', 'like', '%'.$search.'%')
                    ->orWhereHas('plot', fn ($plot) => $plot->where('name', 'like', '%'.$search.'%')))
                    ->orWhereHas('item.adoption', fn ($adoption) => $adoption->where('certificate_name', 'like', '%'.$search.'%'));
            }))
            ->latest('id')->paginate(15)->withQueryString();

        return view('partner.works', compact('works'));
    }

    public function workOrder(Request $request, WorkOrder $work): mixed
    {
        abort_unless($work->partner_id === $request->user()->partner?->id, 403);
        $work->load('item.offer.plot', 'item.adoption', 'item.trees.planting');

        return view('partner.work', compact('work'));
    }

    public function planting(Request $request, Tree $tree, ActivityService $activity): mixed
    {
        abort_unless($tree->item->workOrder?->partner_id === $request->user()->partner?->id && $request->user()->partner, 403);
        $data = $request->validate([
            'planted_at' => 'required|date|before_or_equal:today',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        unset($data['photo']);
        $path = $request->file('photo')->store('plantings', 'local');
        abort_unless($path, 500);
        try {
            $old = DB::transaction(function () use ($tree, $data, $path, $activity): ?string {
                $work = WorkOrder::lockForUpdate()->findOrFail($tree->item->workOrder->id);
                $tree = Tree::lockForUpdate()->findOrFail($tree->id);
                $existing = $tree->planting()->first();
                abort_if($existing && $existing->status !== 'revision', 409, 'Bukti ini sedang diperiksa atau sudah disetujui.');
                $row = $tree->planting()->updateOrCreate([], $data + [
                    'photo' => $path, 'status' => 'pending', 'review_note' => null,
                    'reviewed_by' => null, 'submission_version' => ($existing?->submission_version ?? 0) + 1,
                ]);
                $work->refreshPlantingStatus();
                $activity->audit('planting.submitted', $row);

                return $existing?->photo;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'Bukti penanaman berhasil dikirim.');
    }

    public function monitoring(Request $request): mixed
    {
        $trees = Tree::with('item.offer.plot', 'planting', 'monitorings')
            ->whereHas('item.workOrder', fn ($query) => $query->where('partner_id', $request->user()->partner?->id ?? 0))
            ->whereHas('planting', fn ($query) => $query->where('status', 'approved'))
            ->latest('id')->paginate(8);

        return view('partner.monitoring', compact('trees'));
    }

    public function saveMonitoring(Request $request, Tree $tree, ActivityService $activity): mixed
    {
        abort_unless($request->user()->partner && $tree->item->workOrder?->partner_id === $request->user()->partner->id, 403);
        abort_unless($tree->planting?->status === 'approved', 409, 'Bukti tanam belum disetujui.');
        $data = $request->validate([
            'monitoring_id' => 'nullable|integer',
            'observed_at' => 'required|date|after_or_equal:'.$tree->planting->planted_at->toDateString().'|before_or_equal:today',
            'height' => 'required|numeric|min:0|max:100000',
            'health' => 'required|in:healthy,care,dead',
            'care_note' => 'required|string|max:2000',
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        $id = $data['monitoring_id'] ?? null;
        unset($data['monitoring_id'], $data['photo']);
        $path = $request->file('photo')->store('monitorings', 'local');
        abort_unless($path, 500);
        try {
            $old = DB::transaction(function () use ($tree, $id, $data, $path, $activity): ?string {
                $tree = Tree::lockForUpdate()->findOrFail($tree->id);
                $row = $id ? $tree->monitorings()->where('status', 'revision')->findOrFail($id) : new Monitoring(['tree_id' => $tree->id]);
                $old = $row->photo;
                $row->fill($data + ['photo' => $path, 'status' => 'pending', 'review_note' => null, 'reviewed_by' => null, 'submission_version' => ($row->submission_version ?? 0) + 1])->save();
                $activity->audit('monitoring.submitted', $row);

                return $old;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($old) {
            Storage::disk('local')->delete($old);
        }

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
        abort_unless($work->partner_id === $request->user()->partner?->id, 403);
        $service->submit($work, TerminRule::findOrFail($data['termin_rule_id']));

        return back()->with('success', 'Pengajuan pencairan dikirim.');
    }
}
