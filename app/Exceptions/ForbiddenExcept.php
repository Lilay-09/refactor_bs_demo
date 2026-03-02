<?php

namespace App\Exceptions;

use Exception;

class ForbiddenExcept extends Exception
{
    //
    public function __construct($message = "Forbidden")
    {
        parent::__construct($message,403);
    }
}
