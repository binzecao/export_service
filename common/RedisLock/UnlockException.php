<?php

namespace common\RedisLock;

use \Exception;
use Throwable;

class UnlockException extends Exception
{
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'unlock fail.';
        }
        parent::__construct($message, $code, $previous);
    }
}