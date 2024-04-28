<?php

namespace Tekkenking\Swissecho\Routes\Slack;

use Illuminate\Notifications\Notification;
use Tekkenking\Swissecho\Routes\BaseRoute;

class SlackRoute extends BaseRoute
{

    public function send($notifiable, Notification $notification): static
    {
        // TODO: Implement send() method.
        return $this;
    }

    public function directSend($routeBuilder): static
    {
        dd($routeBuilder);
    }
}
