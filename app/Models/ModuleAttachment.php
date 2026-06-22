<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'file_name',
        'file_path',
        'file_size',
    ];

    /**
     * Get the module that owns the attachment.
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}
