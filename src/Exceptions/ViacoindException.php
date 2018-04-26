<?php

namespace Onurrr\Viacoin\Exceptions;

use RuntimeException;

class ViacoindException extends RuntimeException
{
    /**
     * Construct new viacoind exception.
     *
     * @param object $error
     *
     * @return void
     */
    public function __construct($error)
    {
        parent::__construct($error['message'], $error['code']);
    }
}
