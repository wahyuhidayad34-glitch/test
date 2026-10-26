<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Error aturan bisnis (mis. "PR bukan draft", "bukan approver").
 * Dirender otomatis sebagai JSON dengan status HTTP yang tepat (default 422),
 * jadi tidak lagi berubah menjadi 500.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, protected int $status = 422)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render($request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
