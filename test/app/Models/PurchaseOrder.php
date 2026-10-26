<?php
namespace App\Models;
 
use App\Models\Concerns\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
 
class PurchaseOrder extends Model
{
    use GeneratesDocumentNumber;

    protected $table = 'purchase_orders';
 
    protected $fillable = [
        'po_number',
        'pr_id',
        'vendor_id',
        'po_date',
        'delivery_date',
        'status',
        'total_amount',
        'payment_terms',
        'notes',
    ];
 
    protected $casts = [
        'po_date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:2',
    ];
 
    // Boot - Auto generate PO Number
    protected static function boot()
    {
        parent::boot();
 
        static::creating(function ($model) {
            if (!$model->po_number) {
                $model->po_number = static::nextDocumentNumber('PO', 'po_number');
            }
        });
    }
 
    // Relationships
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'pr_id');
    }
 
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
 
    public function items(): HasMany
    {
        return $this->hasMany(POItem::class, 'po_id');
    }
 
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'po_id');
    }
 
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'po_id');
    }
 
    // Methods
    public function getReceivedQuantity($poItemId)
    {
        return GRItem::where('po_item_id', $poItemId)->sum('quantity_accepted');
    }
 
    public function isFullyReceived(): bool
    {
        foreach ($this->items as $item) {
            if ($item->received_qty < $item->quantity) {
                return false;
            }
        }
        return true;
    }
}
