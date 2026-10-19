<?php

namespace App\Http\Controllers;

use App\Models\Anomaly;
use App\Support\WebcamSnapshot;

class AnomalySnapshotController extends Controller
{
    use Concerns\AuthorizesClassAccess;

    /**
     * Stream a camera anomaly's webcam snapshot to the instructor who manages its class.
     */
    public function __invoke(int $id)
    {
        $anomaly = Anomaly::with('labSession.laboratory.module')->findOrFail($id);
        $this->authorizeClassManager($anomaly->labSession?->laboratory?->module?->class_id);

        $headers = [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $snapshot = WebcamSnapshot::decode($anomaly->metadata['image_base64'] ?? null);
        if ($snapshot) {
            return response($snapshot['bytes'], 200, $headers + ['Content-Type' => $snapshot['mime']]);
        }

        // Rows from before snapshots moved into the database point at a file instead.
        if ($anomaly->hasSnapshot() && $anomaly->image_path !== Anomaly::DB_SNAPSHOT) {
            $file = storage_path('app/public/'.substr($anomaly->image_path, strlen('storage/')));
            if (is_file($file)) {
                return response()->file($file, $headers);
            }
        }

        abort(404);
    }
}
