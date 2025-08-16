<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CheckoutRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'part_id',
        'part_name',
        'user_id',
        'user_name',
        'custody_assigned_to',
        'usage_type',
        'checked_out_at',
        'returned_at',
        'checkout_photos',
        'notes',
    ];

    protected $casts = [
        'checked_out_at'  => 'datetime',
        'returned_at'     => 'datetime',
        'checkout_photos' => 'array',
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

    // Helper: is this checkout currently active?
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->returned_at);
    }
}
