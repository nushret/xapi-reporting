<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Statement extends Model
{
    use HasFactory;

    protected $fillable = [
        'statement_id',
        'actor_id',
        'activity_id',
        'verb',
        'result',
        'context',
        'timestamp'
    ];

    protected $casts = [
        'result' => 'array',
        'context' => 'array',
        'timestamp' => 'datetime'
    ];

    public function actor()
    {
        return $this->belongsTo(Actor::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
