<?php

namespace Tekkenking\Swissecho\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Tekkenking\Swissecho\SendResult;

class AfterSend
{
    use Dispatchable, InteractsWithSockets, SerializesModels;



    /**
     * Create a new event instance.
     */
    public function __construct(
        public array $insightPayload,
        public $formattedResponse,
        public $identifier,
        public ?SendResult $result = null
    ) {}
}
