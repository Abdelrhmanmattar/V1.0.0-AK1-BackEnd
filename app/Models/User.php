<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable =     ['name',
    'username',
    'email',
    'phone',
    'address',
    'role',
    'password',
    'is_static',
];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_static' => 'boolean',
        ];
    }
    public function getJWTIdentifier()
    {
        return (string) $this->getKey();
    }
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    // Block changing username/password for static users
    protected static function booted()
    {
        static::updating(function (User $user) {
            if ($user->is_static && ($user->isDirty('username') || $user->isDirty('password'))) {
                throw ValidationException::withMessages([
                    'user' => 'Username/password cannot be changed for the static user.',
                ]);
            }
        });

        static::deleting(function (User $user) {
            if ($user->is_static) {
                throw ValidationException::withMessages([
                    'user' => 'Static user cannot be deleted.',
                ]);
            }
        });
    }

     // Relations
    public function checkoutRecords()
    {
        return $this->hasMany(CheckoutRecord::class);
    }
    public function checkupRecords()
    {
        return $this->hasMany(CheckupRecord::class);
    }
    public function doorEvents()
    {
        return $this->hasMany(DoorEvent::class);
    }

        // Role helpers
    public function isDevicesManager(): bool
    {
        return $this->role === 'devices_manager';
    }
    public function isFurnitureManager(): bool
    {
        return $this->role === 'furniture_manager';
    }
}
