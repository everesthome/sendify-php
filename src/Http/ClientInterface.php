<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Http;

use EverestHome\Sendify\Config\Connection;

interface ClientInterface
{
    /**
     * @param array<string, string> $headers
     * @param array<string, scalar|null> $query
     */
    public function send(
        Connection $connection,
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        array $query = [],
    ): Response;
}
