<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'google_id', 'avatar', 'rol', 'activo', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'activo' => 'boolean',
    ];

    public function esAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    public function pasos()
    {
        return $this->hasMany(Paso::class, 'usuario_id');
    }
}
