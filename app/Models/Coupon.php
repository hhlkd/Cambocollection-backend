<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'usage_limit',
        'used_count',
        'min_order_amount',
        'is_active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function isValid()
    {
        return $this->is_active &&
            now()->between($this->start_date, $this->end_date) &&
            ($this->usage_limit === null || $this->used_count < $this->usage_limit);
    }
}
