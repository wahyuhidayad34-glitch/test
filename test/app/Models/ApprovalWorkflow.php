<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflow extends Model
{
    protected $table = 'approval_workflows';

    protected $fillable = [
        'document_type',
        'document_id',
        'approval_level',
        'approver_id',
        'status',
        'comments',
        'approved_at',
    ];

    protected $casts = [
        'approval_level' => 'integer',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * FIX: relasi ini sebelumnya tidak ada tetapi dipakai di service & controller.
     * Hanya bermakna untuk document_type = 'pr'.
     */
    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'document_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByDocument($query, $type, $id)
    {
        return $query->where('document_type', $type)->where('document_id', $id);
    }

    // Methods
    public function approve(?string $comments = null): bool
    {
        $this->status = 'approved';
        $this->comments = $comments;
        $this->approved_at = now();
        return $this->save();
    }

    public function reject(?string $comments = null): bool
    {
        $this->status = 'rejected';
        $this->comments = $comments;
        return $this->save();
    }
}
