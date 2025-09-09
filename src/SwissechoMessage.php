<?php

namespace Tekkenking\Swissecho;

use Illuminate\Support\Str;

class SwissechoMessage
{
    /**
     * @var array
     */
    protected array $data = [];

    public $body;
    public $message;
    public $to;
    public $from;
    public $sender;
    public $place;
    public $gateway;
    public $identifier = null;
    public $route;

    /**
     * @param $msg
     * @return $this
     */
    public function line($msg): SwissechoMessage
    {
        if(!isset($this->data['message'])) {
            $this->data['message'] = $msg;
        }else{
            $this->data['message'] .= "\n".$msg;
        }

        $this->body = $this->message = $this->data['message'];
        return $this;
    }

    /**
     * @param $msg
     * @return $this
     */
    public function content($msg): SwissechoMessage
    {
        return $this->line($msg);
    }

    public function sender($sender): SwissechoMessage
    {

        if($sender) {
            if(!isset($this->data['sender'])){
                $this->data['sender'] = Str::limit($sender, 10, '');
            }
            $this->sender = $this->from = $this->data['sender'];
        }

        return $this;
    }

    public function from($sender): SwissechoMessage
    {
        $this->sender($sender);
        $this->from = $this->sender;
        return $this;
    }

    public function to($to): SwissechoMessage
    {
        if($to) {
            $this->data['to'] = $to;
            $this->to = $this->data['to'];
        }

        return $this;
    }

    public function identifier($identifier): SwissechoMessage
    {
        $this->identifier = $this->data['identifier'] = $identifier;
        return $this;
    }

    /**
     * @param string $place
     * @return $this
     */
    public function place(string $place): SwissechoMessage
    {
        $this->place = $place;
        return $this;
    }

    /**
     * @param string $gateway
     * @return $this
     */
    public function gateway(string $gateway): SwissechoMessage
    {
        $this->gateway = $gateway;
        return $this;
    }

    public function route(string $route): SwissechoMessage
    {
        $this->route = $route;
        return $this;
    }

    /**
     * @return array
     */
    public function get(): array
    {
        return $this->data;
    }
}
