<?php

namespace Tekkenking\Swissecho\Tests;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Tekkenking\Swissecho\Events\WebhookReceived;
use Tekkenking\Swissecho\SwissechoServiceProvider;

class WebhookTest extends TestCase
{
    private const SECRET = 'kjsdfu923hjhds9893ioe';

    protected function getPackageProviders($app): array
    {
        return [SwissechoServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set(
            'swissecho.routes_options.sms.gateway_options.termii.webhook',
            ['secret' => self::SECRET, 'handle' => 'handle']
        );
    }

    private function url(string $route, string $gateway, string $secret): string
    {
        return "/webhook/swissecho/route/{$route}/{$gateway}/{$secret}";
    }

    public function test_the_webhook_route_is_registered(): void
    {
        //The route file was never loaded, so every provider callback got a
        //404 from the router before the controller was reached.
        $this->assertTrue(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => $route->getName() === 'swissecho.webhook'
            )
        );
    }

    public function test_a_valid_callback_is_accepted_and_announced(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->postJson($this->url('sms', 'termii', self::SECRET), ['status' => 'DELIVERED'])
            ->assertOk()
            ->assertJson(['message' => 'Webhook successful']);

        Event::assertDispatched(WebhookReceived::class, function (WebhookReceived $event) {
            return $event->route === 'sms'
                && $event->gateway === 'termii'
                && $event->payload['status'] === 'DELIVERED';
        });
    }

    public function test_a_wrong_secret_is_rejected(): void
    {
        Event::fake([WebhookReceived::class]);

        $this->postJson($this->url('sms', 'termii', 'not-the-secret'))
            ->assertStatus(401)
            ->assertJson(['message' => 'invalid key']);

        Event::assertNotDispatched(WebhookReceived::class);
    }

    public function test_the_gateway_name_is_not_accepted_as_the_secret(): void
    {
        //Laravel binds non-class route arguments positionally, so the old
        //signature received 'termii' as the secret and dropped the real one.
        Event::fake([WebhookReceived::class]);

        $this->postJson($this->url('sms', 'termii', 'termii'))->assertStatus(401);

        Event::assertNotDispatched(WebhookReceived::class);
    }

    public function test_a_gateway_with_no_webhook_block_is_a_404(): void
    {
        $this->postJson($this->url('sms', 'wirepick', self::SECRET))
            ->assertStatus(404)
            ->assertJson(['message' => 'no webhook setup']);
    }

    public function test_an_unconfigured_secret_is_rejected(): void
    {
        config(['swissecho.routes_options.sms.gateway_options.termii.webhook' => ['handle' => 'handle']]);

        $this->postJson($this->url('sms', 'termii', 'anything'))
            ->assertStatus(401)
            ->assertJson(['message' => 'webhook not configured']);
    }

    public function test_the_leftover_debug_route_is_gone(): void
    {
        //src/Urls/web.php declared GET hello_swissecho, which called dd().
        $this->get('/hello_swissecho')->assertNotFound();
    }
}
