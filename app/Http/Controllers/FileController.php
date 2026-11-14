<?php

namespace App\Http\Controllers;

use App\Models\Disbursement;
use App\Models\Monitoring;
use App\Models\Partner;
use App\Models\Planting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function __invoke(Request $request, string $type, int $id): mixed
    {
        $types = ['legal' => Partner::class, 'planting' => Planting::class, 'monitoring' => Monitoring::class, 'transfer' => Disbursement::class];
        abort_unless(isset($types[$type]), 404);
        $row = $types[$type]::findOrFail($id);
        $user = $request->user();
        if ($type === 'legal') {
            $allowed = $user->role === 'admin' || $row->user_id === $user->id;
        } elseif ($type === 'transfer') {
            $allowed = ($user->role === 'admin' && $user->can_manage_finance) || $row->workOrder->partner->user_id === $user->id;
        } else {
            $allowed = $user->role === 'admin' || $row->tree->item->offer->plot->partner->user_id === $user->id || ($row->status === 'approved' && $row->tree->item->adoption->user_id === $user->id);
        }
        abort_unless($allowed, 403);
        $file = match ($type) {
            'legal' => $row->document,'transfer' => $row->proof,default => $row->photo
        };
        abort_unless($file && Storage::disk('local')->exists($file), 404);

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($file, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
        }
        if ($request->boolean('preview')) {
            $title = match ($type) {
                'legal' => 'Dokumen Legalitas Kelompok Tani',
                'planting' => 'Dokumentasi Bukti Tanam',
                'monitoring' => 'Dokumentasi Monitoring',
                'transfer' => 'Bukti Transfer',
            };
            $mime = Storage::disk('local')->mimeType($file) ?: 'application/octet-stream';

            return view('file-preview', compact('title', 'mime', 'type', 'id'));
        }

        return Storage::disk('local')->response($file, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
