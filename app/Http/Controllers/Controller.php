<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class Controller
{
    protected function filteredRecords(Request $request, Collection $records, callable $text, int $size = 15, ?callable $status = null, string $pageName = 'page', string $prefix = ''): LengthAwarePaginator
    {
        $query = mb_strtolower(trim((string) $request->input($prefix.'q', '')));
        $selectedStatus = (string) $request->input($prefix.'status', '');
        $filtered = $records->filter(fn ($record) => ($query === '' || str_contains(mb_strtolower($text($record)), $query))
            && ($selectedStatus === '' || $status === null || $status($record) === $selectedStatus))->values();
        $page = min(max(1, (int) $request->input($pageName, 1)), max(1, (int) ceil($filtered->count() / $size)));

        return (new LengthAwarePaginator($filtered->forPage($page, $size)->values(), $filtered->count(), $size, $page, [
            'path' => $request->url(), 'pageName' => $pageName,
        ]))->withQueryString();
    }
}
