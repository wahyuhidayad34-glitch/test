<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $table = 'purchase_request_items';
    protected $fillable = ['pr_id','item_name','description','quantity','unit','unit_price','total_price','specification'];
    protected $casts = ['quantity'=>'integer','unit_price'=>'decimal:2','total_price'=>'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->total_price = (float) $item->quantity * (float) $item->unit_price;
        });
    }

    public function purchaseRequest(): BelongsTo { return $this->belongsTo(PurchaseRequest::class, 'pr_id'); }
}
