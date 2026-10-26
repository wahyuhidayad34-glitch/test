<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';
    protected $fillable = ['invoice_id','po_item_id','description','quantity','unit_price','total_price'];
    protected $casts = ['quantity'=>'integer','unit_price'=>'decimal:2','total_price'=>'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->total_price = (float) $item->quantity * (float) $item->unit_price;
        });
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function poItem(): BelongsTo { return $this->belongsTo(POItem::class, 'po_item_id'); }
}
