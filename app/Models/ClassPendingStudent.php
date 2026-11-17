<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A roster entry for an email with no Certicode account yet. It turns into an enrollment
 * when someone signs in with that email (see ClassRoster::enrollPending).
 */
class ClassPendingStudent extends Model
{
    protected $fillable = ['class_id', 'email', 'name', 'added_by'];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
