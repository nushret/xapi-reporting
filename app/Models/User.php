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
        'name', // name alanını ekleyin
        'first_name',
        'last_name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
    ];
    
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($user) {
            if (!$user->name) {
                $user->name = $user->first_name . ' ' . $user->last_name;
            }
        });
        
        static::updating(function ($user) {
            if ($user->isDirty(['first_name', 'last_name'])) {
                $user->name = $user->first_name . ' ' . $user->last_name;
            }
        });
    }
    
    public function contents()
    {
        return $this->belongsToMany(Content::class, 'user_content', 'user_id', 'content_path')
                    ->withTimestamps();
    }
    
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
