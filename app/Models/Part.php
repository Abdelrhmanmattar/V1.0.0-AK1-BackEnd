<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

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
    //protected $hidden = ['qr'];


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

    protected static function booted()
    {
        static::saving(function (Part $part) {
            // If serial_number is missing → generate UUID and store it
            if (empty($part->serial_number)) {
                $part->serial_number = (string) Str::uuid();
            }

            // Ensure part_number is never null (fallback to UUID too if empty)
            $pn = $part->part_number ?: '';
            $sn = $part->serial_number;

            // Build raw QR string
            $raw = "PN:{$pn}|SN:{$sn}";

            // Encrypt with AES (Laravel Crypt uses AES-256-CBC)
            $part->qr = Crypt::encryptString($raw);
        });
    }

    /**
     * Accessor to decrypt QR easily
     */
    public function getQrDecodedAttribute()
    {
        return Crypt::decryptString($this->qr);
    }
    // Scopes (optional)
    public function scopeDevices($q)
    {
        return $q->where('type', 'device');
    }
    public function scopeFurniture($q)
    {
        return $q->where('type', 'furniture');
    }
    public function scopeAvailable($q)
    {
        return $q->where('status', 'available');
    }
    public function scopeCheckedOut($q)
    {
        return $q->where('status', 'checked-out');
    }
}
