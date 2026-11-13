<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabSessionChat extends Model
{
    use HasFactory;

    protected $table = 'lab_session_chats';

    protected $fillable = [
        'lab_session_id',
        'user_id',
        'user_name',
        'avatar_color',
        'message',
        'code_snippet',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public const AVATAR_COLORS = ['#3ecf8e', '#38bdf8', '#f59e0b', '#a855f7', '#ec4899', '#10b981', '#6366f1'];

    /** The message as the extension and the lab page read it. */
    public function toPayload(): array
    {
        $nameParts = explode(' ', $this->user_name);
        $initials = strtoupper(substr($nameParts[0], 0, 1).(isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'initials' => $initials ?: 'ST',
            'avatar_color' => $this->avatar_color,
            'message' => $this->message,
            'code_snippet' => $this->code_snippet,
            'is_system' => (bool) $this->is_system,
            'time' => ($this->created_at ?? now())->format('H:i'),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Get the session associated with this message.
     */
    public function session()
    {
        return $this->belongsTo(LabSession::class, 'lab_session_id');
    }

    /**
     * Get the user who sent the message.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
