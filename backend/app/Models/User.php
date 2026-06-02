<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role_id', 'branch_id',
        'avatar', 'last_login', 'is_active'
    ];

    protected $hidden = ['password', 'remember_token'];
    
    protected $casts = [
        'last_login' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function hasPermission($permission)
    {
        return $this->role->permissions()->where('name', $permission)->exists();
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}