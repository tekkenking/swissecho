<?php

declare(strict_types=1);

namespace Tekkenking\Swissecho;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use LogicException;

/**
 * What happened to a send, in the same shape for every provider.
 *
 * Each gateway reads its own provider's reply and fills this in, so
 * `$result->success` means the same thing whether the provider signals a
 * rejection with an HTTP status, a field in a JSON body, or a line of text.
 *
 * Array access is supported for the snake_case keys - `$result['success']`,
 * `$result['provider_res']` - which are also the keys added to the
 * AfterSend event's formattedResponse.
 *
 * @implements ArrayAccess<string, mixed>
 * @implements Arrayable<string, mixed>
 */
final class SendResult implements ArrayAccess, Arrayable, JsonSerializable
{
    /**
     * @param bool        $success      Whether the provider accepted the message.
     * @param string|null $messageId    The provider's own ID for it, when given.
     * @param string|null $errorCode    The provider's error code, on failure.
     * @param string|null $errorMessage The provider's reason, on failure.
     * @param mixed       $providerRes  The provider's reply, untouched. Null when nothing came back.
     * @param mixed       $to           The recipient this result is for.
     * @param string|null $route        sms, voice, whatsapp, slack or telegram.
     * @param string|null $gateway      The gateway that handled it.
     * @param bool        $mocked       True when mock mode stood in for the provider.
     * @param SendResult[] $results     One per request, when a send made several.
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly mixed $providerRes = null,
        public readonly mixed $to = null,
        public readonly ?string $route = null,
        public readonly ?string $gateway = null,
        public readonly bool $mocked = false,
        public readonly array $results = [],
    ) {}

    public function failed(): bool
    {
        return !$this->success;
    }

    /**
     * Fold several results into one, for a send that made one request per
     * recipient. It succeeds only if every request did; the first failure
     * supplies the error, and each request's own result stays in $results.
     *
     * @param SendResult[] $results
     */
    public static function combine(array $results): ?self
    {
        $results = array_values($results);

        if (count($results) <= 1) {
            return $results[0] ?? null;
        }

        $failures = array_values(array_filter($results, fn (self $result) => $result->failed()));
        $first = $failures[0] ?? null;

        return new self(
            success: $failures === [],
            errorCode: $first?->errorCode,
            errorMessage: $first?->errorMessage,
            providerRes: array_map(fn (self $result) => $result->providerRes, $results),
            to: array_map(fn (self $result) => $result->to, $results),
            route: $results[0]->route,
            gateway: $results[0]->gateway,
            mocked: $results[0]->mocked,
            results: $results,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'       => $this->success,
            'message_id'    => $this->messageId,
            'error_code'    => $this->errorCode,
            'error_message' => $this->errorMessage,
            'provider_res'  => $this->providerRes,
            'to'            => $this->to,
            'route'         => $this->route,
            'gateway'       => $this->gateway,
            'mocked'        => $this->mocked,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('A SendResult cannot be changed.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('A SendResult cannot be changed.');
    }
}
