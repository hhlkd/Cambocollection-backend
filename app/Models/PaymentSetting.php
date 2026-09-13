<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = ['khqr_image', 'aba_holder_name', 'aba_account'];
}
