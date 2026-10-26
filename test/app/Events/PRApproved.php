<?php

namespace App\Events;

use App\Models\PurchaseRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PRApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public PurchaseRequest $pr)
    {
    }
}
