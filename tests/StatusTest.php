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

it('expone los campos nuevos del snapshot', function () {
    $client = (new FakeClient())->push(200, [
        'status' => 'qr_ready',
        'hasCredentials' => false,
        'reconnecting' => false,
        'connecting' => false,
        'reconnectAttempts' => 2,
        'qrExpiresAt' => '2026-08-16T21:49:39.000-06:00',
        'qrAttempt' => 1,
        'maxQrCycles' => 4,
        'lastActiveAt' => '2026-08-16T21:40:00.000-06:00',
        'business' => ['id' => 7, 'name' => 'Everest Home'],
    ]);

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status->qrAttempt())->toBe(1)
        ->and($status->maxQrCycles())->toBe(4)
        ->and($status->qrExpiresAt())->toBe('2026-08-16T21:49:39.000-06:00')
        ->and($status->reconnectAttempts())->toBe(2)
        ->and($status->reconnecting())->toBeFalse()
        ->and($status->lastActiveAt())->toBe('2026-08-16T21:40:00.000-06:00')
        ->and($status->business()['name'])->toBe('Everest Home');
});

it('trata como no vinculada la instancia desconectada sin credenciales', function () {
    $client = (new FakeClient())->push(200, [
        'status' => 'disconnected',
        'hasCredentials' => false,
        'hibernatedAt' => null,
        'hibernationReason' => null,
        'needsStart' => true,
    ]);

    [$sendify] = sendify($client);

    expect($sendify->Status()->state)->toBe(EverestHome\Sendify\Enums\InstanceState::Unlinked);
});

it('reporta reconexión en curso', function () {
    $client = (new FakeClient())->push(200, [
        'status' => 'connecting',
        'hasCredentials' => true,
        'reconnecting' => true,
        'connecting' => true,
        'reconnectAttempts' => 3,
    ]);

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status->state)->toBe(EverestHome\Sendify\Enums\InstanceState::Connecting)
        ->and($status->reconnecting())->toBeTrue()
        ->and($status->connecting())->toBeTrue();
});

/*
 * Los cuerpos de abajo se capturaron del servicio real corriendo, no se
 * inventaron: son la defensa contra que la API cambie y el paquete no.
 */

it('trata la instancia desactivada como cuenta suspendida aunque la key sea válida', function () {
    // Desactivar una instancia bloquea todas sus keys, incluidas las scoped.
    $client = (new FakeClient())->push(401, [
        'success' => false,
        'error' => 'La instancia está desactivada',
    ]);

    [$sendify] = sendify($client);

    $status = $sendify->Status();

    expect($status->state)->toBe(InstanceState::Suspended)
        ->and($status->suspended())->toBeTrue()
        ->and($status->accountProblem())->toBeTrue()
        ->and($status->canSend())->toBeFalse();
});

it('traduce el rechazo 409 de un envío a una instancia desconectada', function () {
    $client = (new FakeClient())->push(409, [
        'success' => false,
        'error' => 'La instancia 1 no está conectada a WhatsApp: no hay sesión vinculada, escanea el QR',
    ]);

    [$sendify] = sendify($client);

    try {
        $sendify->to('5215551234567')->text('hola');
        $this->fail('El envío debió lanzar una excepción.');
    } catch (EverestHome\Sendify\Exceptions\InstanceNotConnectedException $exception) {
        $status = EverestHome\Sendify\Status::fromException($exception);

        expect($status->state)->toBe(InstanceState::Disconnected)
            ->and($status->needsAttention())->toBeTrue()
            ->and($status->canSend())->toBeFalse();
    }
});

it('reporta el rechazo de una URL privada como error de validación', function () {
    $client = (new FakeClient())->push(400, [
        'success' => false,
        'error' => 'La URL del archivo apunta a una dirección privada; usa un enlace público',
    ]);

    [$sendify] = sendify($client);

    expect(fn () => $sendify->to('5215551234567')->image('https://ejemplo.mx/foto.png'))
        ->toThrow(EverestHome\Sendify\Exceptions\ValidationException::class, 'dirección privada');
});
