<?php

namespace App\Http\Controllers;

use App\Models\TelemetryLog;
use App\Support\WebcamSnapshot;

class CameraCheckPictureController extends Controller
{
    use Concerns\AuthorizesClassAccess;

    /**
     * Stream the picture a pre-lab camera check passed on to the instructor who manages its
     * class. The face count came from the browser; this picture is what it was counted on.
     */
    public function __invoke(int $id)
    {
        $log = TelemetryLog::where('event_type', TelemetryLog::CAMERA_CHECK_EVENT)
            ->with('labSession.laboratory.module')
            ->findOrFail($id);
        $this->authorizeClassManager($log->labSession?->laboratory?->module?->class_id);

        $picture = WebcamSnapshot::decode($log->payload['image_base64'] ?? null);
        abort_unless($picture, 404);

        return response($picture['bytes'], 200, [
            'Content-Type' => $picture['mime'],
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
