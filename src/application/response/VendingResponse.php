<?php

namespace Daniella\VendingMachine\Application\Response;

final class VendingResponse
{
    public function __construct(
        private bool $success,
        private string $message,
        private array $data = []
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}