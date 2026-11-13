<?php

namespace Tekkenking\Swissecho;

use Exception;
use Throwable;

class SwissechoException extends Exception
{
    private $errorMsg;

    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, ?Throwable $previous = null)
    {
        $this->errorMsg = $message;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report or log an exception.
     *
     * Returning false hands this back to Laravel's own handler, which logs
     * it at error level with the stack trace and context, and passes it to
     * Sentry, Flare or whatever else is configured.
     *
     * The previous body called Log::debug() and returned void. Void means
     * 'already reported', so Laravel skipped its own logging, and at the
     * usual production LOG_LEVEL=error the debug line was dropped too -
     * every Swissecho error disappeared without trace.
     *
     * @return bool
     */
    public function report(): bool
    {
        return false;
    }

    /**
     * Build a helpful exception for when no place could be resolved
     * for a given route (no explicit place, no routeNotificationPlace(),
     * and no configured default_place).
     *
     * @param string $route
     * @param array $availablePlaces
     * @return static
     */
    public static function noPlaceConfigured(string $route, array $availablePlaces): self
    {
        $placesList = !empty($availablePlaces) ? implode(', ', $availablePlaces) : 'none configured';
        $example = $availablePlaces[0] ?? 'nga';

        $message = "Swissecho: No place could be determined for the '{$route}' route." . PHP_EOL
            . "Available places for '{$route}': {$placesList}." . PHP_EOL . PHP_EOL
            . "To fix this, do one of the following:" . PHP_EOL
            . "1. Set a global default in your .env file: SWISSECHO_DEFAULT_PLACE={$example}" . PHP_EOL
            . "2. Set it per-route in your published config/swissecho.php under routes_options['{$route}']: 'default_place' => '{$example}'" . PHP_EOL
            . "3. Set it explicitly on your notifiable model by implementing: public function routeNotificationPlace(): string { return '{$example}'; }";

        return new self($message);
    }

    // public function render(): string
    // {
    //     return $this->errorMsg;
    // }
}
