<?php

namespace App\Exceptions;

use RuntimeException;

class StaffAppreciationException extends RuntimeException
{
    public static function windowClosed(): self
    {
        return new self('The nomination period is not currently open.');
    }

    public static function alreadyResponded(): self
    {
        return new self('You have already responded to this month\'s nomination.');
    }
}
