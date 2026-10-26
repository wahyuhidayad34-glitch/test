<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class POItem extends Model
{
    protected $table = 'po_items';
    protected $fillable = ['po_id','item_name','description','quantity','unit','unit_price','total_price','received_qty'];
    protected $casts = ['quantity'=>'integer','received_qty'=>'integer','unit_price'=>'decimal:2','total_price'=>'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->total_price = (float) $item->quantity * (float) $item->unit_price;
        });
    }

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'po_id'); }
    public function receiptItems(): HasMany { return $this->hasMany(GRItem::class, 'po_item_id'); }
}
