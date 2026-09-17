<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves files from the public disk when the `public/storage` symlink is
 * missing — the usual production case after a deploy that did not run
 * `storage:link`, or a host that will not follow that link.
 *
 * When the symlink exists the web server answers `/storage/...` first and this
 * controller is never reached.
 */
class PublicStorageController extends Controller
{
    public function show(Request $request, string $path): StreamedResponse
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $download = $request->boolean('download');

        return $download
            ? $disk->download($path, basename($path))
            : $disk->response($path);
    }
}
