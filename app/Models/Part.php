<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Part extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'part_number',
        'serial_number',
        'name',
        'facility_classification',
        'facility_type',
        'facility_details',
        'location',
        'department_id',
        'type',
        'photo',
        'invoice_photo',
        'status',
        'condition',
        'qr_code',
        'price',
        'vat',
        'all_vat',
        'notes',
        'checkup_schedule',
        'last_checkup',
        'next_checkup',
    ];

    protected $casts = [
        'price'         => 'decimal:2',
        'vat'           => 'decimal:2',
        'all_vat'       => 'decimal:2',
        'last_checkup'  => 'datetime',
        'next_checkup'  => 'datetime',
    ];

    // Relations
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function checkoutRecords()
    {
        return $this->hasMany(CheckoutRecord::class);
    }

    public function checkupRecords()
    {
        return $this->hasMany(CheckupRecord::class);
    }

    // Who currently holds this part (latest checkout without return)
    public function currentCheckout()
    {
        return $this->hasOne(CheckoutRecord::class)
            ->whereNull('returned_at')
            ->latestOfMany('checked_out_at');
    }

    // Scopes (optional)
    public function scopeDevices($q)   { return $q->where('type', 'device'); }
    public function scopeFurniture($q) { return $q->where('type', 'furniture'); }
    public function scopeAvailable($q) { return $q->where('status', 'available'); }
    public function scopeCheckedOut($q){ return $q->where('status', 'checked-out'); }
}
