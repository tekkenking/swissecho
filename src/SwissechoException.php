<?php

namespace Tekkenking\Swissecho;

use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class SwissechoException extends Exception
{
    private $errorMsg;

    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        $this->errorMsg = $message;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report or log an exception.
     *
     * @return void
     */
    public function report()
    {
        Log::debug($this->errorMsg);
    }

    public function render(): string
    {
        return $this->errorMsg;
    }
}
