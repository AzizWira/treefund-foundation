<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PrivateEvidenceService
{
    public function respond(Request $request, ?string $file, string $title, string $type, int $id): mixed
    {
        $disk = Storage::disk('local');
        abort_unless($file && $disk->exists($file), 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];
        if ($request->boolean('download')) {
            return $disk->download($file, null, $headers);
        }
        if ($request->boolean('preview')) {
            $mime = $disk->mimeType($file) ?: 'application/octet-stream';

            return response()->view('file-preview', compact('title', 'mime', 'type', 'id'), 200, $headers);
        }

        return $disk->response($file, null, $headers);
    }
}
