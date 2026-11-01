<?php

namespace App\Http\Controllers;

use App\Models\Adoption;
use App\Models\Tree;
use App\Models\WorkOrder;
use App\Models\AuditLog;
use App\Models\Monitoring;
use App\Models\Planting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function dashboard(Request $request): mixed
    {
        if ($request->user()->role === 'donor') {
            $user = $request->user();
            $orders = Adoption::where('user_id', $user->id)->latest('id')->get();
            $trees = Tree::with('item.offer.plot', 'planting')->whereHas('item.adoption', fn ($query) => $query->where('user_id', $user->id)->where('status', 'paid'))->get();

            return view('dashboard.donor', compact('orders', 'trees'));
        }

        if ($request->user()->role === 'partner') {
            $partner = $request->user()->partner;
            $works = WorkOrder::with('item.offer.plot', 'item.trees.planting', 'partner')->where('partner_id', $partner?->id ?? 0)->latest('id')->get();

            return view('dashboard.partner', compact('partner', 'works'));
        }

        if ($request->user()->role === 'admin') {
            return view('dashboard.admin', [
                'plantingCount' => Planting::where('status', 'pending')->count(),
                'monitoringCount' => Monitoring::where('status', 'pending')->count(),
                'plantedCount' => Planting::where('status', 'approved')->count(),
                'pendingFunds' => Schema::hasTable('disbursements') ? DB::table('disbursements')->whereIn('status', ['pending', 'approved'])->sum('amount') : 0,
                'plantings' => Planting::with('tree.item.offer.plot.partner')->latest('id')->limit(5)->get(),
                'logs' => AuditLog::with('actor')->latest('id')->limit(6)->get(),
                'mapTrees' => Tree::with('item.offer.plot', 'planting')->whereHas('planting', fn ($query) => $query->where('status', 'approved'))->get(),
                'monthly' => Planting::where('status', 'approved')->whereYear('planted_at', now()->year)->get()->groupBy(fn ($row) => $row->planted_at->format('m'))->map->count(),
            ]);
        }

        $pageTitle = match ($request->user()->role) {
            'admin' => 'Dashboard Admin',
            'partner' => 'Dashboard Mitra',
            default => 'Dashboard Donatur',
        };

        return view('portal.placeholder', compact('pageTitle'));
    }
}
