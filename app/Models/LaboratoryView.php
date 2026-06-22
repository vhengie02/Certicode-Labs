<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LaboratoryView extends Model
{
    use HasFactory;

    protected $fillable = [
        'laboratory_id',
        'user_id',
    ];

    public function laboratory()
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
