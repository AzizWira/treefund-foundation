<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    public function catalog(Request $request): mixed
    {
        $request->validate(['q' => 'nullable|string|max:190', 'species' => 'nullable|string|max:120']);
        $search = trim((string) $request->input('q', ''));

        $published = Offer::published();
        $speciesOptions = (clone $published)->select('species')->distinct()->orderBy('species')->pluck('species');
        $offers = $published->with('plot.partner')
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s->where('species', 'like', '%'.$search.'%')->orWhereHas('plot', fn ($p) => $p->where('name', 'like', '%'.$search.'%')->orWhere('location', 'like', '%'.$search.'%'))))
            ->when($request->filled('species'), fn ($q) => $q->where('species', $request->string('species')))->orderBy('id')->paginate(9)->withQueryString();

        $portalCatalog = $request->routeIs('donor.catalog');

        return view('catalog', compact('offers', 'portalCatalog', 'speciesOptions'));
    }
}
