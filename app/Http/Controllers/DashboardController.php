<?php

namespace App\Http\Controllers;

use App\Models\ActivityNotification;
use App\Models\AuditLog;
use App\Models\Disbursement;
use App\Models\Monitoring;
use App\Models\Planting;
use App\Models\Tree;
use App\Models\WorkOrder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): mixed
    {
        $user = $request->user();
        if ($user->role === 'admin') {
            return view('dashboard.admin', [
                'plantingCount' => Planting::where('status', 'pending')->count(), 'monitoringCount' => Monitoring::where('status', 'pending')->count(),
                'plantedCount' => Planting::where('status', 'approved')->count(), 'pendingFunds' => Disbursement::whereIn('status', ['pending', 'approved'])->sum('amount'),
                'plantings' => Planting::with('tree.item.offer.plot.partner')->latest()->limit(5)->get(),
                'logs' => AuditLog::with('actor')->latest()->limit(6)->get(),
                'mapTrees' => Tree::with('item.offer.plot', 'planting')->whereHas('planting', fn ($q) => $q->where('status', 'approved'))->get(),
                'monthly' => Planting::where('status', 'approved')->whereYear('planted_at', now()->year)->get()->groupBy(fn ($p) => $p->planted_at->format('m'))->map->count(),
            ]);
        }
        if ($user->role === 'partner') {
            $partner = $user->partner;
            $works = WorkOrder::with('item.offer.plot', 'item.trees.planting', 'partner')->where('partner_id', $partner?->id ?? 0)->latest()->get();

            return view('dashboard.partner', compact('partner', 'works'));
        }
        $orders = $user->adoptions()->with('items.offer.plot', 'certificate')->latest()->get();
        $trees = Tree::with('item.offer.plot', 'planting', 'monitorings')->whereHas('item.adoption', fn ($q) => $q->where('user_id', $user->id)->where('status', 'paid'))->get();

        return view('dashboard.donor', compact('orders', 'trees'));
    }

    public function notifications(Request $request): mixed
    {
        return view('notifications', ['notifications' => $request->user()->activityNotifications()->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('title', 'like', '%'.$request->input('q').'%')->orWhere('body', 'like', '%'.$request->input('q').'%')))->paginate(15)->withQueryString()]);
    }

    public function readAllNotifications(Request $request): mixed
    {
        $request->user()->activityNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi telah ditandai dibaca.');
    }

    public function readNotification(Request $request, ActivityNotification $notification): mixed
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return redirect($notification->url);
    }
}
