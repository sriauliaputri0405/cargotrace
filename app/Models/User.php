<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{   
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPERATOR = 'operator';
    public const ROLE_CUSTOMER = 'customer';

    public function isAdmin(): bool
{
        return $this->role === self::ROLE_ADMIN;
}

    public function isOperator(): bool
{
        return $this->role === self::ROLE_OPERATOR;
}

    public function isCustomer(): bool
{
        return $this->role === self::ROLE_CUSTOMER;
}
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
