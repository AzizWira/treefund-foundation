<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function queue(Request $request, string $type): mixed
    {
        abort_unless($type === 'partners', 404);
        $work = null;
        $request->validate(['review' => 'nullable|integer|min:1']);
        $records = Partner::with('user')
            ->when($request->filled('review'), fn ($query) => $query->whereKey($request->integer('review')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->input('q').'%';
                $query->where(fn ($query) => $query->where('group_name', 'like', $term)->orWhere('address', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.queue', compact('type', 'records', 'work'));
    }
}
