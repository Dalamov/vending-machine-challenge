<?php

namespace Daniella\VendingMachine\Application\Response;

final class ErrorResponse extends VendingResponse
{
    public function __construct(string $message = 'Error', array $data = [])
    {
        parent::__construct(false, $message, $data);
    }
}