<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    protected $fillable = ['mobile', 'code_hash', 'expires_at', 'attempts', 'used_at'];
    protected $hidden = ['code_hash'];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime', 'attempts' => 'integer'];
}
