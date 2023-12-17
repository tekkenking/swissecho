<?php

namespace Tekkenking\Swissecho\Routes\Voice;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Tekkenking\Swissecho\Routes\BaseRoute;
use Tekkenking\Swissecho\SwissechoException;

class VoiceRoute extends BaseRoute
{


    public function send($notifiable, Notification $notification): static
    {
        $this->msgBuilder = $notification->toSms($notifiable);
        $this->msgBuilder->to($this->prepareTo($notifiable));
        $this->pushToGateway($notifiable);
        return $this;
    }

    public function directSend($routeBuilder): static
    {
        $this->msgBuilder = $routeBuilder;
        $this->pushToGateway($this->mockedNotifiable);
        return $this;
    }

    protected function pushToGateway($notifiable = null)
    {

    }

}
