<?php

namespace Daniella\VendingMachine\Application\Response;

final class SuccessResponse extends VendingResponse
{
    public function __construct(string $message = 'OK', array $data = [])
    {
        parent::__construct(true, $message, $data);
    }
}