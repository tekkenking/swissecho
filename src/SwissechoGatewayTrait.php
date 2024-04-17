<?php

namespace Tekkenking\Swissecho;

use Illuminate\Support\Str;

trait SwissechoGatewayTrait
{

    /**
     * @var mixed
     */
    public mixed $to;

    /**
     * @var string|mixed
     */
    public string $sender;

    /**
     * @var string
     */
    public string $body;

    /**
     * @var array
     */
    private array $payload;

    /**
     * @var array
     */
    public array $config;

    /**
     * @var array
     */
    private array $serverResponse;

    /**
     * @return void
     */
    public function boot(): self
    {
        //dump($this->init());
        $this->processDependencies();
        $ch = $this->send($this->init());
        $this->execCurl($ch ?? null);
        //dd($this->getServerResponse());

        return $this;
    }

    public function coldBoot(): self
    {
        return $this;
    }

    /**
     * @return array
     */
    protected function checkDependencies(): array
    {
        return [];
    }

    /**
     * @return void
     * @throws SwissechoException
     */
    protected function processDependencies(): void
    {

        $dependenciesArr = $this->checkDependencies();

        foreach ($dependenciesArr ?? [] as $depArr) {
            $className = $depArr[0];
            $composerRequire = $depArr[1];

            if(!class_exists($className)) {
                $driverName = Str::before($className, '\\');
                throw new SwissechoException('Notification: '.$driverName.' driver is missing required dependencies. RUN: & '.$composerRequire .'  in CLI');
            }
        }


    }

    /**
     * @param $ch
     * @return mixed
     */
    protected function hookBeforeExecCurl($ch)
    {
        return $ch;
    }

    /**
     * @param $output
     * @return mixed
     */
    protected function hookAfterExecCurl($output): mixed
    {
        return $output;
    }

    /**
     * @param $ch
     * @return array|bool|string|void
     */
    protected function execCurl($ch = null)
    {

       /* if(!$ch) {

        }*/

        try {
            //get response
            $ch = $this->hookBeforeExecCurl($ch);

            $output = curl_exec($ch);

            //Print error if any
            $isError = false;
            $errorMessage = '';
            if (curl_errno($ch)) {
                $isError = true;
                $errorMessage = curl_error($ch);
            }
            curl_close($ch);

            $output = $this->hookAfterExecCurl($output);

            if($isError){
                $data = ['error' => true , 'message' => $errorMessage];
            }else{
                $data = $output;
            }

            $status = !$isError;
            $this->setServerResponse($status, $data);

            return $data;

        } catch (\Exception $exception) {
            $this->setServerResponse(false, $exception->getMessage());
        }
    }


    /**
     * @return array
     */
    public function getServerResponse(): array
    {
        return $this->serverResponse;
    }


}
