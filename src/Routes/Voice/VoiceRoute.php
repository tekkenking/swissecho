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
        $this->msgBuilderInitForSendViaNotification($this->notification->toVoice($this->notifiable));
        return $this;
    }

    /**
     * @param $routeBuilder
     * @return static
     * @throws SwissechoException
     */
    public function directSend($routeBuilder): static
    {
        $this->msgBuilderInitForDirectSend($routeBuilder);
        return $this;
    }

    /**
     * Get the alphanumeric sender.
     *
     * @return mixed
     */
    protected function prepareSender(): mixed
    {
        if (!$this->msgBuilder->sender) {

            if ($this->notifiable
                && method_exists($this->notifiable, 'routeNotificationVoiceSender')) {
                return $this->notifiable->routeNotificationVoiceSender();
            }

            return $this->gatewaySender();
        }

        return $this->msgBuilder->sender;
    }

}
