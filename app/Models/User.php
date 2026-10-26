<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;   // FIX: HasApiTokens wajib untuk createToken()

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department',
        'manager_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',      // FIX: password otomatis di-hash saat diisi
        'is_active' => 'boolean',
    ];

    // Relationships
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'requester_id');
    }

    public function approvalWorkflows(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class, 'approver_id');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'received_by_id');
    }

    // Scopes
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Role helpers
    public function isRequester(): bool
    {
        return $this->role === 'requester';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isFinance(): bool
    {
        return $this->role === 'finance';
    }

    public function isDirector(): bool   // FIX: sebelumnya tidak ada
    {
        return $this->role === 'director';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHr(): bool
    {
        return $this->role === 'hr';
    }

    /** Staf HR penuh: admin dan hr */
    public function isHrStaff(): bool
    {
        return in_array($this->role, ['admin', 'hr'], true);
    }

    public function getPendingApprovals()
    {
        return $this->approvalWorkflows()->where('status', 'pending')->get();
    }
}
