<?php

namespace App\Http\Controllers;

use App\Models\Adoption;
use App\Models\Tree;
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

        $pageTitle = match ($request->user()->role) {
            'admin' => 'Dashboard Admin',
            'partner' => 'Dashboard Mitra',
            default => 'Dashboard Donatur',
        };

        return view('portal.placeholder', compact('pageTitle'));
    }
}
