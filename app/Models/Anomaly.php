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

    protected $appends = ['snapshot_url'];

    /**
     * `image_path` value for a webcam snapshot kept in `metadata.image_base64`.
     * Older rows hold a file path under storage/anomalies instead.
     */
    public const DB_SNAPSHOT = 'db:snapshot';

    public const LEGACY_SNAPSHOT_PATTERN = '#^storage/anomalies/\d+/snapshot_\w+\.(jpg|png|webp)$#';

    public function hasSnapshot(): bool
    {
        return $this->image_path === self::DB_SNAPSHOT
            || (is_string($this->image_path) && preg_match(self::LEGACY_SNAPSHOT_PATTERN, $this->image_path) === 1);
    }

    /**
     * Instructor-only URL for this anomaly's webcam snapshot, if it has one.
     */
    public function getSnapshotUrlAttribute(): ?string
    {
        return $this->hasSnapshot() ? route('anomalies.snapshot', $this->id, false) : null;
    }

    /**
     * Omit the base64 webcam snapshot (~30 KB per row) from `metadata` when listing anomalies.
     * Views link to `snapshot_url`, which streams it from the full row.
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
