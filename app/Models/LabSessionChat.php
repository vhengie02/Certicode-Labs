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
    ];

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
