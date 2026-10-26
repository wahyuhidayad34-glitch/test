<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GRItem extends Model
{
    protected $table = 'gr_items';
    protected $fillable = ['gr_id','po_item_id','quantity_received','quantity_accepted','quantity_rejected','notes'];
    protected $casts = ['quantity_received'=>'integer','quantity_accepted'=>'integer','quantity_rejected'=>'integer'];

    public function goodsReceipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class, 'gr_id'); }
    public function poItem(): BelongsTo { return $this->belongsTo(POItem::class, 'po_item_id'); }
}
