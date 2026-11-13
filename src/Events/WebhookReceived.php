<?php

namespace Tekkenking\Swissecho\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a provider callback passes secret verification and the
 * gateway's webhook config names no handler class of its own.
 *
 * It deliberately carries plain data rather than the Request object, so a
 * queued listener can serialise it.
 */
class WebhookReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param string $route   The route the callback arrived on, e.g. 'sms'.
     * @param string $gateway The gateway key, e.g. 'termii'.
     * @param array $payload  The request body and query, as an array.
     * @param array $headers  The request headers.
     */
    public function __construct(
        public string $route,
        public string $gateway,
        public array $payload,
        public array $headers = []
    ) {}
}
