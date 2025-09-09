<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @mixin IdeHelperUser
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [
            'company_id' => $this->company_id,
            'tenant_id' => tenant('id'), // Get current tenant ID
        ];
    }

    /**
     * Get the company that owns this user
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}