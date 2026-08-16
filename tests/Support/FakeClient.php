<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Tests\Support;

use EverestHome\Sendify\Config\Connection;
use EverestHome\Sendify\Http\ClientInterface;
use EverestHome\Sendify\Http\Response;

/** Cliente HTTP de mentiras: guarda lo que se envía y responde lo que se le encole. */
class FakeClient implements ClientInterface
{
    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: array<string, mixed>|null, query: array<string, mixed>}> */
    public array $requests = [];

    /** @var array<int, Response> */
    private array $queue = [];

    public function __construct(Response ...$responses)
    {
        $this->queue = $responses;
    }

    public function push(int $status, array $body = ['success' => true], array $headers = []): self
    {
        $this->queue[] = new Response($status, $headers, json_encode($body) ?: '{}');

        return $this;
    }

    public function send(
        Connection $connection,
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        array $query = [],
    ): Response {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body === null ? null : json_decode($body, true),
            'query' => $query,
        ];

        return array_shift($this->queue) ?? new Response(200, [], '{"success":true}');
    }

    /** @return array{method: string, url: string, headers: array<string, string>, body: array<string, mixed>|null, query: array<string, mixed>} */
    public function lastRequest(): array
    {
        return $this->requests[count($this->requests) - 1];
    }
}
