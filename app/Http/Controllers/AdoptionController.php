<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Services\AdoptionService;
use Illuminate\Http\Request;

class AdoptionController extends Controller
{
    public function checkout(int $offer): mixed
    {
        $offer = Offer::published()->with('plot.partner')->findOrFail($offer);
        abort_if($offer->plot->available < 1, 404);

        return view('checkout', compact('offer'));
    }

    public function store(Request $request, AdoptionService $service): mixed
    {
        $data = $request->validate([
            'offer_id' => 'required|integer|exists:offers,id',
            'quantity' => 'required|integer|min:1|max:1000',
            'certificate_name' => 'required|string|max:150',
            'agree' => 'accepted',
        ]);
        $order = $service->checkout($request->user(), Offer::findOrFail($data['offer_id']), (int) $data['quantity'], $data['certificate_name']);

        return redirect()->route('orders.show', $order)->with('success', 'Pesanan dibuat. Kuota dicadangkan hingga batas waktu pembayaran.');
    }
}
