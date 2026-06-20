<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'school_classes';

    protected $fillable = [
        'name',
        'code',
        'instructor_id',
        'description',
    ];

    /**
     * Get the instructor of this class.
     */
    public function instructor()
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Get the students enrolled in this class.
     */
    public function students()
    {
        return $this->belongsToMany(User::class, 'class_student', 'class_id', 'student_id')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    /**
     * Get the modules associated with this class.
     */
    public function modules()
    {
        return $this->hasMany(Module::class, 'class_id')->orderBy('order_index');
    }
}
