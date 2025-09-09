<?php

namespace App\Models;

use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @mixin IdeHelperCompany
 */
class Company extends Model
{
    use HasUuids;

    // protected $connection = 'mysql'; // Central database connection

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'address',
        'logo',
        'status',
        'settings',
    ];

    protected $casts = [
        'status' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the tenant that owns this company
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}