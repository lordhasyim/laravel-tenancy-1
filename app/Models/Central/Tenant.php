<?php

namespace App\Models\Central;

use App\Models\Company;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

/**
 * @mixin IdeHelperTenant
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'data',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get tenant name from data field
     */
    public function getNameAttribute()
    {
        return $this->data['name'] ?? null;
    }

    /**
     * Get tenant email from data field
     */
    public function getEmailAttribute()
    {
        return $this->data['email'] ?? null;
    }

    /**
     * Get tenant phone from data field
     */
    public function getPhoneAttribute()
    {
        return $this->data['phone'] ?? null;
    }

    /**
     * Get tenant address from data field
     */
    public function getAddressAttribute()
    {
        return $this->data['address'] ?? null;
    }

    /**
     * Get tenant status from data field
     */
    public function getStatusAttribute()
    {
        return $this->data['status'] ?? true;
    }

    /**
     * Get the database connection configuration for this tenant
     */
    public function getConnectionData(): array
    {
        return [
            'host' => $this->data['tenancy_db_host'] ?? env('DB_HOST', '127.0.0.1'),
            'port' => $this->data['tenancy_db_port'] ?? env('DB_PORT', '3306'),
            'database' => $this->data['tenancy_db_name'] ?? 'tenant' . str_replace('-', '', $this->id),
            'username' => $this->data['tenancy_db_user'] ?? env('DB_USERNAME'),
            'password' => $this->data['tenancy_db_password'] ?? env('DB_PASSWORD'),
        ];
    }

    /**
     * Get companies belonging to this tenant (when in tenant context)
     */
    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Scope for active tenants
     */
    public function scopeActive($query)
    {
        return $query->whereJsonContains('data->status', true);
    }
}