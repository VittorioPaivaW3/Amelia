<?php

namespace App\Http\Controllers;

use App\Models\GutRequestAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GutRequestAttachmentController extends Controller
{
    private const SECTORS = ['mkt', 'juridico', 'rh'];

    public function download(Request $request, GutRequestAttachment $attachment): StreamedResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        $gutRequest = $attachment->gutRequest;
        if (! $gutRequest) {
            abort(404);
        }

        $role = $user->role;
        if ($role === 'admin') {
            return $this->downloadAttachment($attachment);
        }

        if (in_array($role, self::SECTORS, true)) {
            if (($gutRequest->sector ?? '') !== $role && $gutRequest->user_id !== $user->id) {
                abort(403);
            }

            return $this->downloadAttachment($attachment);
        }

        if ($gutRequest->user_id !== $user->id) {
            abort(403);
        }

        return $this->downloadAttachment($attachment);
    }

    private function downloadAttachment(GutRequestAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($attachment->path)) {
            abort(404);
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }
}
