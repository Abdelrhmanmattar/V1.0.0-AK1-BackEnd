<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name'];
        // Relations
    public function parts()
    {
        return $this->hasMany(Part::class);
    }
}
