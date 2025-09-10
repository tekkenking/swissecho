<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SwissechoMock
{

    private array $config;

    /*private mixed $mockedNotifiable;

    public function setMockedNotifiable($mocked)
    {
        $this->mockedNotifiable = $mocked;
    }*/

    private function mockBuildInfo($gatewayClass, $msgBuilder)
    {
        $build = '';
        if(App::environment('local')) {
            $build  = "From: ". $msgBuilder->from."\n";
            $build .= "To: ". implode(',', $msgBuilder->to)."\n";
            $build .= "Message: ". $msgBuilder->message . "\n";
            $build .= "============================================\n";
            $build .= "BUILD INFO: (Not included in the actual BODY):\n";
            $build .= "============================================\n";
            $build .= "Country (for ".$msgBuilder->route." route): ". $msgBuilder->place ."\n";
            $build .= "PhoneCode (for ".$msgBuilder->route." route): ". $msgBuilder->phonecode ."\n";
            $build .= "Route: ". $msgBuilder->route ."\n";
            $build .= "Gateway: ". $msgBuilder->gateway ."\n";
            $build .= "Gateway Class: ". $gatewayClass ."\n";
        }

        return $build;
    }

    public function mockSend($gatewayClass, $msgBuilder)
    {
        $this->config = config('swissecho');
        $buildMock = $this->mockBuildInfo($gatewayClass, $msgBuilder);
        $mockMethod = 'mockBy'.Str::studly($this->config['fake']);

        if($buildMock) {
            $this->$mockMethod($buildMock, $msgBuilder);
            return [
                'status'    =>  true,
                'response'  =>  [
                    'gateway'   =>  $msgBuilder->gateway,
                    'route'   =>  $msgBuilder->route,
                    'message'   =>  $buildMock
                ],
                'from'      =>  $msgBuilder->from,
                'to'        =>  $msgBuilder->to,
                'body'      =>  $msgBuilder->message
            ];
        }

        return [];
    }

    public function mockByMail($buildMock, $msgBuilder)
    {
        Mail::raw($buildMock, function($message) use ($msgBuilder) {
            $message->to([
                $this->config['fake_mail']
            ])->subject('Mock: ['.implode(',', $msgBuilder->to).']');
        });
    }

    public function mockByLog($buildMock, $msgBuilder)
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
