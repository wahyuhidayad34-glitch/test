<?php
namespace App\Models;
 
use App\Models\Concerns\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
 
class GoodsReceipt extends Model
{
    use GeneratesDocumentNumber;

    protected $table = 'goods_receipts';
 
    protected $fillable = [
        'gr_number',
        'po_id',
        'receipt_date',
        'received_by_id',
        'status',
        'notes',
    ];
 
    protected $casts = [
        'receipt_date' => 'date',
    ];
 
    // Boot - Auto generate GR Number
    protected static function boot()
    {
        parent::boot();
 
        static::creating(function ($model) {
            if (!$model->gr_number) {
                $model->gr_number = static::nextDocumentNumber('GR', 'gr_number');
            }
        });
    }
 
    // Relationships
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
 
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }
 
    public function items(): HasMany
    {
        return $this->hasMany(GRItem::class, 'gr_id');
    }
}
