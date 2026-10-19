<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function __invoke(Request $request, string $type, int $id): mixed
    {
        abort_unless($type === 'legal', 404);
        $row = Partner::findOrFail($id);
        $user = $request->user();
        $allowed = $user->role === 'admin' || $row->user_id === $user->id;
        abort_unless($allowed, 403);
        $file = $row->document;
        abort_unless($file && Storage::disk('local')->exists($file), 404);

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($file, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
        }
        if ($request->boolean('preview')) {
            $title = 'Dokumen Legalitas Kelompok Tani';
            $mime = Storage::disk('local')->mimeType($file) ?: 'application/octet-stream';

            return view('file-preview', compact('title', 'mime', 'type', 'id'));
        }

        return Storage::disk('local')->response($file, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
