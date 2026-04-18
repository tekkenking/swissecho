<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tekkenking\Swissecho\SwissechoMessage;
use Tekkenking\Swissecho\SwissechoException;

class SwissechoMessageTest extends TestCase
{
    public function test_line_sets_body_and_message(): void
    {
        $msg = new SwissechoMessage();
        $msg->line('Hello World');

        $this->assertEquals('Hello World', $msg->body);
        $this->assertEquals('Hello World', $msg->message);
    }

    public function test_line_appends_newline_on_second_call(): void
    {
        $msg = new SwissechoMessage();
        $msg->line('First')->line('Second');

        $this->assertEquals("First\nSecond", $msg->body);
    }

    public function test_to_sets_recipient(): void
    {
        $msg = new SwissechoMessage();
        $msg->to('08012345678');

        $this->assertEquals('08012345678', $msg->to);
    }

    public function test_sender_limits_to_11_chars(): void
    {
        $msg = new SwissechoMessage();
        $msg->sender('MyCompanyName'); // 13 chars

        $this->assertEquals('MyCompanyN', $msg->sender); // Str::limit with 10
    }

    public function test_get_returns_data_array(): void
    {
        $msg = new SwissechoMessage();
        $msg->line('Test')->to('123');

        $data = $msg->get();
        $this->assertIsArray($data);
        $this->assertEquals('Test', $data['message']);
        $this->assertEquals('123', $data['to']);
    }

    public function test_swissecho_exception_is_throwable(): void
    {
        $this->expectException(SwissechoException::class);
        throw new SwissechoException('Test error');
    }

    public function test_swissecho_exception_with_previous(): void
    {
        $previous = new \RuntimeException('previous');
        $e = new SwissechoException('wrapped', 0, $previous);

        $this->assertSame($previous, $e->getPrevious());
        $this->assertEquals('wrapped', $e->getMessage());
    }
}
