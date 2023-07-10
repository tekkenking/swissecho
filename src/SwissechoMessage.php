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
    public $phonecode;

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
            $this->to = $this->data['to'] = $to;
        }

        return $this;
    }

    /**
     * @param string $place
     * @return $this
     */
    public function place(string $place): SwissechoMessage
    {
        $this->place = $this->data['place'] = $place;
        return $this;
    }

    /**
     * @param string $gateway
     * @return $this
     */
    public function gateway(string $gateway): SwissechoMessage
    {
        $this->gateway = $this->data['gateway'] = $gateway;
        return $this;
    }

    public function phonecode(string $phonecode): SwissechoMessage
    {
        $this->phonecode = $this->data['phonecode'] = $phonecode;
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
