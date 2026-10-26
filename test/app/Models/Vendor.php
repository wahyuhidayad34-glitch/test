<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = ['code','name','email','phone','address','city','country','payment_terms','rating','is_active'];
    protected $casts = ['rating' => 'decimal:2', 'is_active' => 'boolean'];

    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }
    public function invoices(): HasMany { return $this->hasMany(Invoice::class); }
}
