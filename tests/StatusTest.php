<?php

declare(strict_types=1);

use EverestHome\Sendify\Enums\InstanceState;
use EverestHome\Sendify\Tests\Support\FakeClient;

it('reporta la instancia conectada', function () {
    $client = (new FakeClient())->push(200, [
        'success' => true,
        'instance' => 3,
        'name' => 'ventas',
        'status' => 'connected',
        'hibernated' => false,
        'hasCredentials' => true,
        'needsStart' => false,
        'lastConnectionAt' => '2026-08-15T10:00:00.000-06:00',
    ]);

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status->state)->toBe(InstanceState::Connected)
        ->and($status->value())->toBe('connected')
        ->and($status->canSend())->toBeTrue()
        ->and($status->accountProblem())->toBeFalse()
        ->and($status->instanceName())->toBe('ventas')
        ->and($status->lastConnectionAt())->toBe('2026-08-15T10:00:00.000-06:00');
});

it('reporta hibernación con su motivo', function () {
    $client = (new FakeClient())->push(200, [
        'status' => 'hibernated',
        'hibernated' => true,
        'hibernationReason' => 'inactividad',
        'hasCredentials' => true,
        'needsStart' => true,
    ]);

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status->state)->toBe(InstanceState::Hibernated)
        ->and($status->canSend())->toBeTrue()
        ->and($status->needsStart())->toBeTrue()
        ->and($status->message)->toContain('inactividad');
});

it('trata la instancia desactivada como cuenta suspendida', function () {
    $client = (new FakeClient())->push(200, [
        'status' => 'hibernated',
        'hibernationReason' => 'desactivada',
        'hasCredentials' => true,
    ]);

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status->state)->toBe(InstanceState::Suspended)
        ->and($status->suspended())->toBeTrue()
        ->and($status->accountProblem())->toBeTrue()
        ->and($status->canSend())->toBeFalse();
});

it('detecta que nunca se vinculó un teléfono', function () {
    $client = (new FakeClient())->push(200, [
        'status' => 'hibernated',
        'hasCredentials' => false,
        'needsStart' => true,
    ]);

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status->state)->toBe(InstanceState::Unlinked)
        ->and($status->needsAttention())->toBeTrue()
        ->and($status->hasCredentials())->toBeFalse();
});

it('reporta el QR pendiente de escanear', function () {
    $client = (new FakeClient())->push(200, ['status' => 'qr_ready', 'hasCredentials' => false]);

    [$sendify] = sendify($client);

    expect($sendify->Status()->state)->toBe(InstanceState::QrReady);
});

it('distingue la API key revocada de la expirada', function () {
    [$suspendida] = sendify((new FakeClient())->push(401, ['success' => false, 'error' => 'API key inválida o inactiva']));
    [$expirada] = sendify((new FakeClient())->push(401, ['success' => false, 'error' => 'API key expirada']));
    [$faltante] = sendify((new FakeClient())->push(401, ['success' => false, 'error' => 'API key requerida']));

    expect($suspendida->Status()->state)->toBe(InstanceState::Suspended)
        ->and($expirada->Status()->state)->toBe(InstanceState::KeyExpired)
        ->and($faltante->Status()->state)->toBe(InstanceState::MissingCredentials);
});

it('reporta que la instancia no existe cuando la key no le pertenece', function () {
    $client = (new FakeClient())->push(403, [
        'success' => false,
        'error' => 'La API key no pertenece a la instancia solicitada',
    ]);

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status->state)->toBe(InstanceState::InstanceNotFound)
        ->and($status->accountProblem())->toBeTrue()
        ->and($status->httpStatus)->toBe(403);
});

it('reporta la IP no autorizada', function () {
    $client = (new FakeClient())->push(403, [
        'success' => false,
        'error' => 'IP no autorizada para esta API key',
    ]);

    [$sendify] = sendify($client);

    expect($sendify->Status()->state)->toBe(InstanceState::IpNotAllowed);
});

it('reporta el límite de peticiones', function () {
    $client = (new FakeClient())->push(429, ['success' => false, 'error' => 'Límite de peticiones excedido']);

    [$sendify] = sendify($client, ['retries' => 0]);

    expect($sendify->Status()->state)->toBe(InstanceState::RateLimited);
});

it('reporta el servidor caído sin lanzar excepción', function () {
    $client = new class () extends FakeClient {
        public function send(
            EverestHome\Sendify\Config\Connection $connection,
            string $method,
            string $url,
            array $headers = [],
            ?string $body = null,
            array $query = [],
        ): EverestHome\Sendify\Http\Response {
            throw new EverestHome\Sendify\Exceptions\ConnectionException('No se pudo conectar con Sendify');
        }
    };

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status->state)->toBe(InstanceState::Unreachable)
        ->and($status->canSend())->toBeFalse()
        ->and($sendify->serverReachable())->toBeFalse();
});

it('reporta el error interno del servidor', function () {
    $client = (new FakeClient())->push(500, ['success' => false, 'error' => 'Algo tronó']);

    [$sendify] = sendify($client);

    expect($sendify->Status()->state)->toBe(InstanceState::ServerError);
});

it('se puede serializar a JSON y leer como array', function () {
    $client = (new FakeClient())->push(200, ['status' => 'connected', 'hasCredentials' => true]);

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status['state'])->toBe('connected')
        ->and(json_decode(json_encode($status), true))->toHaveKeys(['state', 'message', 'canSend', 'details'])
        ->and((string) $status)->toStartWith('connected: ');
});

it('consulta la salud del servidor sin API key', function () {
    [$sendify, $client] = sendify((new FakeClient())->push(200, ['status' => 'ok']));

    expect($sendify->serverReachable())->toBeTrue()
        ->and($client->lastRequest()['url'])->toBe('https://sendify.test/health')
        ->and($client->lastRequest()['headers'])->not->toHaveKey('X-API-Key');
});
