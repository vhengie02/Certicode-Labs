<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Anomaly extends Model
{
    use HasFactory, MassPrunable;

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
        $table = $this->getTable();
        $metadata = match ($query->getConnection()->getDriverName()) {
            'pgsql' => "({$table}.metadata::jsonb - 'image_base64')",
            'sqlite' => "json_remove({$table}.metadata, '$.image_base64')",
            default => null,
        };
        if ($metadata === null) {
            return $query;
        }

        $columns = ['id', 'lab_session_id', 'type', 'severity', 'description', 'resolved', 'image_path', 'created_at', 'updated_at'];

        return $query
            ->select(array_map(fn ($column) => "{$table}.{$column}", $columns))
            ->selectRaw("{$metadata} as metadata");
    }

    /**
     * `model:prune` deletes resolved flags older than `app.anomaly_retention_days`.
     * Open flags are never pruned, so nothing unreviewed disappears.
     */
    public function prunable()
    {
        return static::where('resolved', true)
            ->where('created_at', '<', now()->subDays((int) config('app.anomaly_retention_days')));
    }

    /**
     * Remove the webcam snapshot from flags older than `app.snapshot_retention_days`, open ones
     * included: a snapshot is up to 400 KB of base64 in the row. The flag itself is kept, with
     * `snapshot_expired` so the instructor can see a picture existed. Returns the rows changed.
     */
    public static function expireOldSnapshots(int $chunk = 50): int
    {
        $old = static::where('image_path', self::DB_SNAPSHOT)
            ->where('created_at', '<', now()->subDays((int) config('app.snapshot_retention_days')));

        if ((new static)->getConnection()->getDriverName() === 'pgsql') {
            return $old->update([
                'image_path' => null,
                'metadata' => DB::raw("((coalesce(metadata::jsonb, '{}'::jsonb) - 'image_base64') || '{\"snapshot_expired\": true}'::jsonb)::json"),
            ]);
        }

        $changed = 0;
        $old->select(['id', 'metadata'])->chunkById($chunk, function ($anomalies) use (&$changed) {
            foreach ($anomalies as $anomaly) {
                $metadata = $anomaly->metadata ?? [];
                unset($metadata['image_base64']);
                $anomaly->forceFill(['image_path' => null, 'metadata' => $metadata + ['snapshot_expired' => true]])->saveQuietly();
                $changed++;
            }
        });

        return $changed;
    }

    /**
     * Get the lab session.
     */
    public function labSession()
    {
        return $this->belongsTo(LabSession::class);
    }
}
