<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'title',
        'description',
        'content',
        'order_index',
    ];

    /**
     * Get the class this module belongs to.
     */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the coding challenges (laboratories) inside this module.
     */
    public function laboratories()
    {
        return $this->hasMany(Laboratory::class, 'module_id');
    }
}
