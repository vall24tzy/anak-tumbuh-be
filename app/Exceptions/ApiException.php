<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception domain ringan (ARCH-001) - dipakai Service buat melempar error
 * bisnis dengan kode snake_case yang jelas, tanpa nulis response JSON manual.
 * Contoh pemakaian nanti (task lain, bukan sekarang):
 *   throw new ApiException('teacher_already_has_active_assignment', 409, 'Guru sudah punya rombel aktif');
 */
class ApiException extends Exception
{
    protected string $errorCode;
    protected int $status;
    protected array $details;

    public function __construct(string $errorCode, int $status, string $message, array $details = [])
    {
        parent::__construct($message);

        $this->errorCode = $errorCode;
        $this->status    = $status;
        $this->details   = $details;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function details(): array
    {
        return $this->details;
    }
}