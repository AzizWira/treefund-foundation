<?php

namespace App\Http\Controllers;

use App\Models\Adoption;
use App\Models\AuditLog;
use App\Models\Disbursement;
use App\Models\Monitoring;
use App\Models\Offer;
use App\Models\Partner;
use App\Models\Planting;
use App\Models\Plot;
use App\Models\TerminRule;
use App\Models\Tree;
use App\Models\WorkOrder;
use App\Services\ActivityService;
use App\Services\DisbursementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function queue(Request $request, string $type): mixed
    {
        $map = ['partners' => Partner::class, 'plots' => Plot::class, 'plantings' => Planting::class, 'monitorings' => Monitoring::class];
        abort_unless(isset($map[$type]), 404);
        $relations = match ($type) {
            'partners' => ['user'],
            'plots' => ['partner'],
            default => ['tree.item.offer.plot.partner'],
        };
        $work = null;
        if (in_array($type, ['plantings', 'monitorings']) && $request->filled('work_order_id')) {
            $request->validate(['work_order_id' => 'required|integer|min:1']);
            $work = WorkOrder::with('partner', 'item.offer.plot')->findOrFail($request->integer('work_order_id'));
        }
        $request->validate(['review' => 'nullable|integer|min:1']);
        $records = $map[$type]::with($relations)
            ->when($request->filled('review'), fn ($query) => $query->whereKey($request->integer('review')))
            ->when($work, fn ($query) => $query->whereHas('tree', fn ($trees) => $trees->where('adoption_item_id', $work->adoption_item_id)))
            ->when($request->filled('q'), function ($query) use ($request, $type) {
                $term = '%'.$request->input('q').'%';
                $query->where(function ($query) use ($term, $type) {
                    if ($type === 'partners') {
                        $query->where('group_name', 'like', $term)->orWhere('address', 'like', $term);
                    } elseif ($type === 'plots') {
                        $query->where('name', 'like', $term)->orWhere('location', 'like', $term)->orWhereHas('partner', fn ($q) => $q->where('group_name', 'like', $term));
                    } else {
                        $query->whereHas('tree', fn ($q) => $q->where('code', 'like', $term)->orWhereHas('item.offer', fn ($q) => $q->where('species', 'like', $term)->orWhereHas('plot', fn ($q) => $q->where('name', 'like', $term)->orWhereHas('partner', fn ($q) => $q->where('group_name', 'like', $term)))));
                    }
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->latest()->paginate(15)->withQueryString();

        return view('admin.queue', compact('type', 'records', 'work'));
    }

    public function review(Request $request, string $type, int $id, ActivityService $activity): mixed
    {
        $map = ['partners' => Partner::class, 'plots' => Plot::class, 'plantings' => Planting::class, 'monitorings' => Monitoring::class];
        abort_unless(isset($map[$type]), 404);
        $data = $request->validate(['decision' => 'required|in:approved,revision', 'review_note' => 'required_if:decision,revision|nullable|string|max:2000']);
        DB::transaction(function () use ($map, $type, $id, $data, $activity, $request) {
            $record = $map[$type]::lockForUpdate()->findOrFail($id);
            abort_unless($record->status === 'pending', 409, 'Pengajuan sudah diperiksa.');
            if ($type === 'plots') {
                abort_unless($record->partner->status === 'approved', 409, 'Profil Mitra belum disetujui.');
            }
            if ($type === 'monitorings') {
                abort_unless($record->tree->planting?->status === 'approved', 409, 'Bukti tanam belum disetujui.');
            }
            $record->update(['status' => $data['decision'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id]);
            $owner = match ($type) {
                'partners' => $record->user_id,'plots' => $record->partner->user_id,default => $record->tree->item->offer->plot->partner->user_id
            };
            $activity->audit($type.'.'.$data['decision'], $record, ['note' => $data['review_note'] ?? null]);
            $activity->notify($owner, 'Hasil pemeriksaan pengajuan', ($data['decision'] === 'approved' ? 'Pengajuan Anda telah disetujui. ' : 'Pengajuan Anda memerlukan perbaikan. ').($data['review_note'] ?? ''), match ($type) {
                'partners' => '/partner/partnership','plots' => '/partner/plots','monitorings' => '/partner/monitoring',default => '/partner/work-orders'
            }, 'partnership', ['decision' => $data['decision'], 'note' => $data['review_note'] ?? 'Pengajuan telah disetujui. Lihat rincian dan langkah berikutnya melalui akun Anda.']);
            if (in_array($type, ['plantings', 'monitorings']) && $data['decision'] === 'approved') {
                $activity->notify($record->tree->item->adoption->user_id, $type === 'plantings' ? 'Pohon Anda sudah ditanam' : 'Laporan perkembangan pohon terbaru', 'Laporan '.$record->tree->code.' telah diverifikasi.', '/trees?tree='.$record->tree->id, 'field_report', ['tree_code' => $record->tree->code]);
                if ($type === 'plantings') {
                    $work = $record->tree->item->workOrder;
                    if ($work && $work->item->trees->every(fn ($tree) => $tree->planting?->status === 'approved')) {
                        $work->update(['status' => 'planted']);
                    }
                }
            }
        });

        return back()->with('success', 'Keputusan verifikasi disimpan.');
    }

    public function catalog(Request $request): mixed
    {
        return view('admin.catalog', ['offers' => $this->filteredRecords($request, Offer::with('plot.partner')->latest()->get(), fn ($offer) => $offer->species.' '.$offer->plot->name.' '.$offer->plot->partner->group_name, 15, fn ($offer) => $offer->active ? 'active' : 'inactive'), 'plots' => Plot::where('status', 'approved')->get()]);
    }

    public function saveOffer(Request $request, ActivityService $activity): mixed
    {
        $data = $request->validate(['offer_id' => 'nullable|integer', 'plot_id' => 'required|exists:plots,id', 'species' => 'required|string|max:120', 'description' => 'required|string|max:3000', 'price' => 'required|integer|min:1000|max:100000000', 'active' => 'nullable|boolean', 'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $plot = Plot::findOrFail($data['plot_id']);
        abort_unless($plot->status === 'approved' && $plot->partner->status === 'approved', 422, 'Lahan dan Mitra harus terverifikasi.');
        $offer = isset($data['offer_id']) ? Offer::findOrFail($data['offer_id']) : new Offer;
        unset($data['offer_id']);
        $data['active'] = $request->boolean('active');
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('offers', 'public');
        } else {
            unset($data['photo']);
        }
        $offer->fill($data)->save();
        $activity->audit('catalog.saved', $offer);

        return back()->with('success', 'Katalog disimpan. Riwayat transaksi tetap utuh.');
    }

    public function funds(Request $request): mixed
    {
        abort_unless($request->user()->can_manage_finance, 403);

        return view('admin.funds', ['works' => $this->filteredRecords($request, WorkOrder::with('partner', 'item.offer')->withExists('disbursements')->latest()->get(), fn ($work) => 'WO-'.$work->id.' '.$work->partner->group_name.' '.$work->item->offer->species, 8, null, 'allocation_page', 'allocation_'), 'records' => $this->filteredRecords($request, Disbursement::with('workOrder.partner', 'workOrder.item.offer.plot', 'rule')->latest()->get(), fn ($fund) => 'WO-'.$fund->work_order_id.' '.$fund->rule->name.' '.$fund->workOrder->partner->group_name.' '.$fund->workOrder->item->offer->plot->name, 15, fn ($fund) => $fund->status)]);
    }

    public function allocation(Request $request, WorkOrder $work, ActivityService $activity): mixed
    {
        abort_unless($request->user()->can_manage_finance, 403);
        $data = $request->validate(['allocated_amount' => 'required|integer|min:0|max:'.($work->item->unit_price * $work->item->quantity)]);
        DB::transaction(function () use ($work, $data, $activity) {
            $work = WorkOrder::lockForUpdate()->findOrFail($work->id);
            if ($work->disbursements()->exists()) {
                throw ValidationException::withMessages(['allocated_amount' => 'Alokasi WO-'.$work->id.' dikunci karena sudah ada pengajuan pencairan. Nilai alokasi tidak diubah.']);
            }
            $work->update($data);
            $activity->audit('work_order.allocation_set', $work, ['amount' => $data['allocated_amount']]);
        });

        return back()->with('success', 'Alokasi dana work order ditetapkan.');
    }

    public function reviewFunds(Request $request, Disbursement $disbursement, DisbursementService $service, ActivityService $activity): mixed
    {
        abort_unless($request->user()->can_manage_finance, 403);
        $data = $request->validate(['decision' => 'required|in:approved,revision', 'note' => 'required_if:decision,revision|nullable|string|max:2000']);
        DB::transaction(function () use ($disbursement, $service, $activity, $request, $data) {
            $work = WorkOrder::lockForUpdate()->findOrFail($disbursement->work_order_id);
            $row = Disbursement::lockForUpdate()->findOrFail($disbursement->id);
            abort_unless($row->status === 'pending', 409);
            if ($data['decision'] === 'approved') {
                abort_unless($service->eligible($work, $row->rule), 422, 'Milestone belum terpenuhi.');
                $used = $work->disbursements()->where('id', '!=', $row->id)->whereIn('status', ['approved', 'paid'])->sum('amount');
                abort_if($used + $row->amount > $work->allocated_amount, 422, 'Melebihi alokasi dana.');
            }
            $row->update(['status' => $data['decision'], 'approved_by' => $request->user()->id, 'note' => $data['note'] ?? null]);
            $activity->audit('disbursement.'.$data['decision'], $row);
            $activity->notify($work->partner->user_id, 'Hasil verifikasi pencairan', $data['decision'] === 'approved' ? 'Pengajuan pencairan Anda disetujui dan menunggu proses transfer.' : 'Pengajuan pencairan memerlukan perbaikan. Periksa catatan Admin.', '/partner/funds', 'funds', ['term' => $row->rule->name, 'amount' => $row->amount, 'reference' => 'WO-'.$work->id, 'note' => $data['note'] ?? null]);
        });

        return back()->with('success', 'Keputusan pencairan disimpan.');
    }

    public function recordTransfer(Request $request, Disbursement $disbursement, ActivityService $activity): mixed
    {
        abort_unless($request->user()->can_manage_finance, 403);
        $data = $request->validate(['amount' => 'required|integer', 'transferred_at' => 'required|date|before_or_equal:today', 'proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
        DB::transaction(function () use ($request, $disbursement, $data, $activity) {
            $row = Disbursement::lockForUpdate()->findOrFail($disbursement->id);
            abort_unless($row->status === 'approved', 409, 'Transfer sudah dicatat atau pengajuan belum disetujui.');
            abort_unless((int) $data['amount'] === $row->amount, 422, 'Nominal harus sama dengan nilai yang disetujui.');
            $proof = $request->file('proof')->store('transfers', 'local');
            $row->update(['status' => 'paid', 'transferred_at' => $data['transferred_at'], 'proof' => $proof, 'recorded_by' => $request->user()->id]);
            $activity->audit('disbursement.transfer_recorded', $row);
            $activity->notify($row->workOrder->partner->user_id, 'Dana kemitraan telah ditransfer', 'Bukti transfer '.$row->rule->name.' sudah dicatat oleh Admin Keuangan dan tersedia melalui akun Anda.', '/partner/funds', 'funds', ['term' => $row->rule->name, 'amount' => $row->amount, 'reference' => 'WO-'.$row->work_order_id]);
        });

        return back()->with('success', 'Bukti transfer dicatat. Tidak ada transfer bank otomatis dari aplikasi.');
    }

    public function reports(Request $request): mixed
    {
        $data = ['paidTotal' => Adoption::where('status', 'paid')->sum('total'), 'paidCount' => Adoption::where('status', 'paid')->count(), 'plantedCount' => Planting::where('status', 'approved')->count(), 'transferred' => Disbursement::where('status', 'paid')->sum('amount'), 'orders' => Adoption::with('user', 'items.offer.plot')->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('reference', 'like', '%'.$request->input('q').'%')->orWhereHas('user', fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))))->latest()->paginate(15)->withQueryString()];
        $data['healthCounts'] = Tree::with(['monitorings' => fn ($q) => $q->where('status', 'approved')])->whereHas('planting', fn ($q) => $q->where('status', 'approved'))->get()->groupBy(fn ($tree) => $tree->monitorings->first()?->health ?? 'unknown')->map->count();
        $data['termTotals'] = TerminRule::all()->map(fn ($rule) => ['name' => $rule->name, 'amount' => Disbursement::where('termin_rule_id', $rule->id)->where('status', 'paid')->sum('amount'), 'count' => Disbursement::where('termin_rule_id', $rule->id)->where('status', 'paid')->count()]);

        return view('admin.reports', $data);
    }

    public function export(): mixed
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Referensi', 'Donatur', 'Total IDR', 'Status', 'Tanggal']);
            Adoption::with('user')->orderBy('id')->chunk(100, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    $name = $row->user->name;
                    if (preg_match('/^[=+@\-\t\r]/', $name)) {
                        $name = "'".$name;
                    } fputcsv($out, [$row->reference, $name, $row->total, $row->status, $row->created_at->toDateString()]);
                }
            });
            fclose($out);
        }, 'rekap-treefund-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function settings(Request $request): mixed
    {
        return view('admin.settings', ['rules' => TerminRule::all(), 'rulesLocked' => Disbursement::exists(), 'logs' => AuditLog::with('actor')->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('action', 'like', '%'.$request->input('q').'%')->orWhere('subject_type', 'like', '%'.$request->input('q').'%')->orWhereHas('actor', fn ($q) => $q->where('name', 'like', '%'.$request->input('q').'%'))))->latest()->paginate(15)->withQueryString()]);
    }

    public function rules(Request $request): mixed
    {
        abort_unless($request->user()->can_manage_finance, 403);
        $data = $request->validate(['percentages' => 'required|array|size:3', 'percentages.*' => 'required|integer|min:1|max:100']);
        if (array_sum($data['percentages']) !== 100) {
            throw ValidationException::withMessages(['percentages' => 'Total tiga termin harus 100%.']);
        }
        if (Disbursement::exists()) {
            throw ValidationException::withMessages(['percentages' => 'Aturan termin dikunci karena sudah ada pengajuan pencairan. Persentase tidak diubah.']);
        }
        foreach (TerminRule::orderBy('id')->get() as $i => $rule) {
            $rule->update(['percentage' => $data['percentages'][$i]]);
        }

        return back()->with('success', 'Persentase termin diperbarui.');
    }
}
