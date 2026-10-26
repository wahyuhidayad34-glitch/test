<?php

namespace App\Events;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApprovalNeeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PurchaseRequest $pr,
        public User $approver,
        public int $level,
    ) {
    }
}
