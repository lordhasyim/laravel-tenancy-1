<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperMasterPermission
 */
class MasterPermission extends Model
{
    protected $connection = 'mysql'; // Central database connection
    
    protected $fillable = [
        'name',
        'guard_name',
        'category',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}