<?php

namespace App\Listeners;

use App\Events\ApprovalNeeded;
use App\Events\PRApproved;
use App\Events\PRRejected;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder notifikasi: saat ini hanya menulis ke storage/logs/laravel.log.
 * Ganti isinya dengan email / WhatsApp / notifikasi database bila diperlukan.
 * (Laravel otomatis menemukan method handle* ini lewat event discovery.)
 */
class LogApprovalActivity
{
    public function handleApprovalNeeded(ApprovalNeeded $event): void
    {
        Log::info("Approval dibutuhkan: {$event->pr->pr_number} level {$event->level} -> {$event->approver->email}");
    }

    public function handlePRApproved(PRApproved $event): void
    {
        Log::info("PR disetujui penuh: {$event->pr->pr_number}");
    }

    public function handlePRRejected(PRRejected $event): void
    {
        Log::info("PR ditolak: {$event->pr->pr_number} - {$event->reason}");
    }
}
