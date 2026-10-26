<?php
namespace App\Models;
 
use App\Models\Concerns\GeneratesDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
 
class Invoice extends Model
{
    use GeneratesDocumentNumber;

    protected $table = 'invoices';
 
    protected $fillable = [
        'invoice_number',
        'po_id',
        'vendor_id',
        'invoice_date',
        'due_date',
        'status',
        'total_amount',
        'tax_amount',
        'grand_total',
        'payment_status',
        'notes',
    ];
 
    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];
 
    // Boot - Auto generate Invoice Number
    protected static function boot()
    {
        parent::boot();
 
        static::creating(function ($model) {
            if (!$model->invoice_number) {
                $model->invoice_number = static::nextDocumentNumber('INV', 'invoice_number');
            }
        });
    }
 
    // Relationships
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }
 
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
 
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }
 
    // Scopes
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())->where('payment_status', '!=', 'paid');
    }
 
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
