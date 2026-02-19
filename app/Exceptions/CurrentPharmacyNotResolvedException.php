<?php

namespace App\Exceptions;

use RuntimeException;

class CurrentPharmacyNotResolvedException extends RuntimeException
{
    public function __construct(string $message = 'Current pharmacy not resolved')
    {
        parent::__construct($message);
    }
}
