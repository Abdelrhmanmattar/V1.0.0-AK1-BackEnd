<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CheckupRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'part_id',
        'part_name',
        'user_id',
        'user_name',
        'checkup_date',
        'status',
        'notes',
        'next_checkup_date',
        'checkup_photos',
    ];

    protected $casts = [
        'checkup_date'      => 'datetime',
        'next_checkup_date' => 'datetime',
        'checkup_photos'    => 'array',
    ];

    // Relations
    public function part()
    {
        return $this->belongsTo(Part::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes (optional)
    public function scopeUpcoming($q)
    {
        return $q->where('next_checkup_date', '>=', now())->orderBy('next_checkup_date');
    }
}
