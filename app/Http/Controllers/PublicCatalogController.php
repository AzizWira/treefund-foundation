<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    public function catalog(Request $request): mixed
    {
        $published = Offer::where('active', true)->whereHas('plot', fn ($q) => $q->where('status', 'approved')->whereHas('partner', fn ($p) => $p->where('status', 'approved')));
        $speciesOptions = (clone $published)->select('species')->distinct()->orderBy('species')->pluck('species');
        $offers = $published->with('plot.partner')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($s) => $s->where('species', 'like', '%'.$request->string('q').'%')->orWhereHas('plot', fn ($p) => $p->where('name', 'like', '%'.$request->string('q').'%')->orWhere('location', 'like', '%'.$request->string('q').'%'))))
            ->when($request->filled('species'), fn ($q) => $q->where('species', $request->string('species')))->paginate(9)->withQueryString();

        $portalCatalog = $request->routeIs('donor.catalog');

        return view('catalog', compact('offers', 'portalCatalog', 'speciesOptions'));
    }
}
