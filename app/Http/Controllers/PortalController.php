<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function dashboard(Request $request): mixed
    {
        $pageTitle = match ($request->user()->role) {
            'admin' => 'Dashboard Admin',
            'partner' => 'Dashboard Mitra',
            default => 'Dashboard Donatur',
        };

        return view('portal.placeholder', compact('pageTitle'));
    }
}
