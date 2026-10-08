<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anomaly extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_session_id',
        'type',
        'severity',
        'description',
        'resolved',
        'metadata',
        'image_path',
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Omit the base64 webcam snapshot (~30 KB per row) from `metadata` when listing anomalies.
     * The snapshot is also saved to `image_path`, which is what the views display.
     */
    public function scopeWithoutSnapshotData($query)
    {
        if ($query->getConnection()->getDriverName() !== 'pgsql') {
            return $query;
        }

        $table = $this->getTable();
        $columns = ['id', 'lab_session_id', 'type', 'severity', 'description', 'resolved', 'image_path', 'created_at', 'updated_at'];

        return $query
            ->select(array_map(fn ($column) => "{$table}.{$column}", $columns))
            ->selectRaw("({$table}.metadata::jsonb - 'image_base64') as metadata");
    }

    /**
     * Get the lab session.
     */
    public function labSession()
    {
        return $this->belongsTo(LabSession::class);
    }
}
