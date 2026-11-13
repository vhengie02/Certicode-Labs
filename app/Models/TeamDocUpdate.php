<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One batch of edits to a team's shared document, as the extension sent it. The server keeps
 * it for teammates who join later and never reads what is inside.
 */
class TeamDocUpdate extends Model
{
    public const UPDATED_AT = null;

    /** Largest single update, in base64 characters (about 512 KB of edits). */
    public const MAX_PAYLOAD_CHARS = 700_000;

    protected $fillable = ['team_doc_id', 'user_id', 'payload'];

    public function teamDoc()
    {
        return $this->belongsTo(TeamDoc::class);
    }
}
