<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'name',
        'description',
        'type',
	'content_path',
        'launch_url'
    ];

    public function statements()
    {
        return $this->hasMany(Statement::class);
    }
}
