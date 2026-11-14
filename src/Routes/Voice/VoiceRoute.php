<?php

namespace Tekkenking\Swissecho\Routes\Voice;

use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\SwissechoException;

class VoiceRoute extends BaseRoute
{

    /**
     * @return static
     * @throws SwissechoException
     */
    public function sendViaNotification(): static
    {
        $this->prepareAndSend($this->notification->toVoice($this->notifiable));
        return $this;
    }

    /**
     * @param $routeBuilder
     * @return static
     * @throws SwissechoException
     */
    public function directSend($routeBuilder): static
    {
        $this->prepareAndSend($routeBuilder);
        return $this;
    }

    /**
     * Get the alphanumeric sender.
     *
     * @return mixed
     */
    protected function prepareSender(): mixed
    {
        if($this->msgBuilder->sender) {
            return $this->msgBuilder->sender;
        }

        return $this->senderFromNotifiable(['routeNotificationVoiceSender']) ?: $this->gatewaySender();
    }

}
