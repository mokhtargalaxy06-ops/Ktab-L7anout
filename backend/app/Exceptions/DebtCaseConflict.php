<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DebtCaseConflict extends ConflictHttpException
{
    public function __construct(string $reason)
    {
        parent::__construct(__("cases.$reason"));
    }
}
