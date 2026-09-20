<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lunar\Base\LunarUser;
use Lunar\Base\Traits\LunarUser as LunarUserTrait;

/**
 * Implements Lunar\Base\LunarUser (a Lunar infrastructure concern): links
 * this account to Lunar Customer/Cart/Order records. The application domain
 * (app/Domain, app/Application) has no knowledge of this interface - only
 * adapters under app/Infrastructure/Lunar may depend on it.
 */
class User extends Authenticatable implements LunarUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LunarUserTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
