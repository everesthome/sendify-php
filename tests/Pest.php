<?php

declare(strict_types=1);

use EverestHome\Sendify\Config\Connection;
use EverestHome\Sendify\Sendify;
use EverestHome\Sendify\Tests\Support\FakeClient;

/**
 * @return array{0: Sendify, 1: FakeClient}
 */
function sendify(?FakeClient $client = null, array $config = []): array
{
    $client ??= new FakeClient();

    $connection = Connection::fromArray($config + [
        'url' => 'https://sendify.test/',
        'client' => 'snd_live_key',
        'instance' => 'ventas',
        'retries' => 0,
    ]);

    return [new Sendify($connection, $client), $client];
}
