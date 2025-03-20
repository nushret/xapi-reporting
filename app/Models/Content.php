<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    use HasFactory;
    
    protected $primaryKey = 'path';
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'path',
        'name',
        'description',
    ];
    
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_content', 'content_path', 'user_id')
                    ->withTimestamps();
    }
}
