<?php

namespace App\Http\Controllers;

use App\Models\Offer;

class AdoptionController extends Controller
{
    public function checkout(int $offer): mixed
    {
        $offer = Offer::published()->with('plot.partner')->findOrFail($offer);
        abort_if($offer->plot->available < 1, 404);

        return view('checkout', compact('offer'));
    }
}
