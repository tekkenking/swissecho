<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Routes;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

abstract class BaseRoute implements BaseRouteInterface
{
    /**
     * @var  array
     */
    protected array $config;

    /**
     * @var string
     */
    protected string $gateway;

    /**
     * @var string
     */
    protected string $route;

    /**
     * @var mixed
     */
    protected mixed $msgBuilder;

    protected $defaultPlace;

    /**
     * @var array
     */
    private array $gatewayConfig;

    protected $mockedNotifiable;

    abstract public function send($notifiable, Notification $notification): static;

    abstract public function directSend($routeBuilder): static;

    /**
     * @return void
     */
    private function loadConfig(): void
    {
        $this->config = config('swissecho');
    }

    private function getDefaultRoute()
    {
        return $this->config['route'];
    }

    /**
     * @param string $route
     * @return $this
     */
    public function route(string $route): static
    {
        $this->loadConfig();

        $this->route = $route ?? $this->getDefaultRoute();
        return $this;
    }


    protected function getRoute(): string
    {
        return strtolower($this->route);
    }

    protected function getDefaultGateway()
    {
        return reset($this->config['routes_options'][$this->getRoute()]['places'])['gateway'];
        //dd($places);
        //return $this->config['routes_options'][$this->getRoute()]['gateway'];
    }

    protected function getGateway(): string
    {
        return strtolower($this->gateway);
    }

    /**
     * @return void
     */
    private function loadGatewayConfig(): void
    {
        $this->gatewayConfig = $this->config['routes_options'][$this->getRoute()]['gateway_options'][$this->getGateway()];
    }

    /**
     * @param string|null $gateway
     * @return $this
     */
    public function gateway(string | null $gateway = null): static
    {
        $this->gateway = $gateway ?? $this->getDefaultGateway();

        $this->loadGatewayConfig();

        return $this;
    }

    /**
     * @return array
     */
    protected function gatewayConfig(): array
    {
        return $this->gatewayConfig;
    }

    /**
     * @return mixed
     */
    protected function gatewaySender(): mixed
    {
        return (isset($this->gatewayConfig()['sender']))
            ? $this->gatewayConfig()['sender']
            : $this->config['sender'];
    }

    /**
     * @param $notifiable
     * @param Notification $notification
     * @return void
     */
    public function bootByNotification($notifiable, Notification $notification): void
    {
        $this->send($notifiable, $notification);
    }

    /**
     * @param $routeBuilder
     * @return void
     */
    public function bootByDirect($routeBuilder): void
    {
        $this->loadConfig();
        if($routeBuilder->gateway) {
            $this->gateway($routeBuilder->gateway);
        }

        $this->directSend($routeBuilder);
    }

    public function setMockedNotifiable($mocked)
    {
        $this->mockedNotifiable = $mocked;
    }

    private function mockBuildInfo($gatewayConfig, $msgBuilder)
    {
        $build = '';
        if(App::environment('local')) {
            //dump($msgBuilder);
            //dd($gatewayConfig);
            $build  = "From: ". $msgBuilder->from."\n";
            $build .= "To: ". implode(',', $msgBuilder->to)."\n";
            $build .= "Message: ". $msgBuilder->message . "\n";
            $build .= "============================================\n";
            $build .= "BUILD INFO: (Not included in the actual BODY):\n";
            $build .= "============================================\n";
            $build .= "Country (for sms route): ". $msgBuilder->place ."\n";
            $build .= "PhoneCode (for sms route): ". $msgBuilder->phonecode ."\n";
            $build .= "Route: ". $this->config['route'] ."\n";
            $build .= "Gateway: ". $msgBuilder->gateway ."\n";
            $build .= "Gateway Class: ". $gatewayConfig['class'] ."\n";
        }

        return $build;
    }

    public function mockSend($gatewayConfig, $msgBuilder)
    {
        $buildMock = $this->mockBuildInfo($gatewayConfig, $msgBuilder);
        $mockMethod = 'mockBy'.Str::studly($this->config['fake']);

        if($buildMock) {
            $this->$mockMethod($buildMock, $gatewayConfig, $msgBuilder);
        }
    }

    public function mockByMail($buildMock, $gatewayConfig, $msgBuilder)
    {
        Mail::raw($buildMock, function($message) use ($msgBuilder) {
            $message->to([
                $this->config['fake_mail']
            ])->subject('Mock: ['.implode(',', $msgBuilder->to).']');
        });
    }

    public function mockByLog($buildMock, $gatewayConfig, $msgBuilder)
    {
        $this->_prepareLogFile();
        Log::channel('swissecho_mock')
            ->info("Mock: [".implode(',', $msgBuilder->to)."] \n".$buildMock. "\n");
    }

    /*
     * @return void
     */
    private function _prepareLogFile(): void
    {
        $hasLogFile = config('logging.channels.swissecho_mock', null);

        if(!$hasLogFile) {

            $existingConfig = config('logging.channels');
            $existingConfig['swissecho_mock'] =  [
                'driver' => 'single',
                'path' => storage_path('logs/swissecho_mock.log'),
                'level' => 'debug',
            ];

            config(['logging.channels' => $existingConfig]);
        }

        $file = config('logging.channels.swissecho_mock.path');
        if(!File::exists($file)) {
            File::put($file, '');
        }

    }

}
