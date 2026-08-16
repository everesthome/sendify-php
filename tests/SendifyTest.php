<?php

declare(strict_types=1);

use EverestHome\Sendify\Exceptions\AuthenticationException;
use EverestHome\Sendify\Exceptions\InstanceAsleepException;
use EverestHome\Sendify\Exceptions\InstanceNotConnectedException;
use EverestHome\Sendify\Support\Media;
use EverestHome\Sendify\Support\Recipient;
use EverestHome\Sendify\Tests\Support\FakeClient;

it('envía texto a la ruta de la instancia con la API key', function () {
    $client = (new FakeClient())->push(201, [
        'success' => true,
        'data' => ['success' => true, 'messageId' => 'ABC123'],
    ]);

    [$sendify] = sendify($client);

    $response = $sendify->textMessageTo('+52 55 1234 5678', 'Hola');

    expect($response->messageId())->toBe('ABC123')
        ->and($response->successful())->toBeTrue();

    $request = $client->lastRequest();

    expect($request['method'])->toBe('POST')
        ->and($request['url'])->toBe('https://sendify.test/api/sessions/ventas/messages/send-text')
        ->and($request['headers']['X-API-Key'])->toBe('snd_live_key')
        ->and($request['body'])->toBe(['chatId' => '525512345678', 'text' => 'Hola']);
});

it('acepta el nombre del método en PascalCase', function () {
    [$sendify, $client] = sendify();

    $sendify->TextMessageTo('5215551234567', 'Hola');

    expect($client->lastRequest()['url'])->toEndWith('/messages/send-text');
});

it('encadena el destinatario con to()', function () {
    [$sendify, $client] = sendify();

    $sendify->to('5215551234567')->text('Hola');

    expect($client->lastRequest()['body'])->toBe(['chatId' => '5215551234567', 'text' => 'Hola']);
});

it('manda las URL de medios tal cual y el pie de foto', function () {
    [$sendify, $client] = sendify();

    $sendify->imageMessageTo('5215551234567', 'https://example.com/foto.jpg', 'Mira');

    expect($client->lastRequest()['body'])->toBe([
        'chatId' => '5215551234567',
        'url' => 'https://example.com/foto.jpg',
        'caption' => 'Mira',
    ]);
});

it('convierte un archivo local a base64 con su mimetype', function () {
    $path = sys_get_temp_dir().'/sendify-test.pdf';
    file_put_contents($path, '%PDF-1.4 prueba');

    [$sendify, $client] = sendify();

    $sendify->documentMessageTo('5215551234567', $path);

    $body = $client->lastRequest()['body'];

    expect($body['base64'])->toBe(base64_encode('%PDF-1.4 prueba'))
        ->and($body['filename'])->toBe('sendify-test.pdf')
        ->and($body['mimetype'])->not->toBeNull();

    unlink($path);
});

it('normaliza el envío masivo escrito como número => texto', function () {
    [$sendify, $client] = sendify();

    $sendify->bulkMessages(['+52 55 1234 5678' => 'Hola', '5215559876543' => 'Adiós']);

    expect($client->lastRequest()['body'])->toBe([
        'items' => [
            ['chatId' => '525512345678', 'text' => 'Hola'],
            ['chatId' => '5215559876543', 'text' => 'Adiós'],
        ],
    ]);
});

it('manda los filtros del historial como query string', function () {
    [$sendify, $client] = sendify();

    $sendify->messages(['chatId' => '5215551234567', 'limit' => 10]);

    expect($client->lastRequest()['query'])->toBe(['chatId' => '5215551234567', 'limit' => 10]);
});

it('respeta los JID de grupo', function () {
    expect(Recipient::normalize('120363012345678901@g.us'))->toBe('120363012345678901@g.us');
});

it('exige mimetype cuando el medio va en base64 pelón', function () {
    [$sendify] = sendify();

    expect(fn () => $sendify->imageMessageTo('5215551234567', base64_encode('binario')))
        ->toThrow(EverestHome\Sendify\Exceptions\ValidationException::class);
});

it('saca el mimetype de un data URI', function () {
    expect(Media::payload('data:image/png;base64,AAAA'))
        ->toBe(['base64' => 'AAAA', 'mimetype' => 'image/png']);
});

it('lanza AuthenticationException con una API key inválida', function () {
    $client = (new FakeClient())->push(401, ['success' => false, 'error' => 'API key inválida o inactiva']);

    [$sendify] = sendify($client);

    expect(fn () => $sendify->textMessageTo('5215551234567', 'Hola'))
        ->toThrow(AuthenticationException::class, 'API key inválida o inactiva');
});

it('lanza InstanceNotConnectedException con 409', function () {
    $client = (new FakeClient())->push(409, ['success' => false, 'error' => 'El cliente no está conectado']);

    [$sendify] = sendify($client);

    expect(fn () => $sendify->textMessageTo('5215551234567', 'Hola'))
        ->toThrow(InstanceNotConnectedException::class);
});

it('reintenta cuando la instancia hibernada responde 503 reintentable', function () {
    $client = (new FakeClient())
        ->push(503, ['success' => false, 'error' => 'La instancia no despertó', 'retryable' => true], ['retry-after' => '1'])
        ->push(201, ['success' => true, 'data' => ['messageId' => 'OK1']]);

    [$sendify] = sendify($client, ['retries' => 1]);

    expect($sendify->textMessageTo('5215551234567', 'Hola')->messageId())->toBe('OK1')
        ->and($client->requests)->toHaveCount(2);
});

it('deja de reintentar cuando se agotan los intentos', function () {
    $client = (new FakeClient())
        ->push(503, ['success' => false, 'error' => 'La instancia no despertó', 'retryable' => true], ['retry-after' => '1']);

    [$sendify] = sendify($client, ['retries' => 0]);

    expect(fn () => $sendify->textMessageTo('5215551234567', 'Hola'))
        ->toThrow(InstanceAsleepException::class);
});

it('apunta a otra instancia sin tocar la configuración', function () {
    [$sendify, $client] = sendify();

    $sendify->instance('soporte')->status();

    expect($client->lastRequest()['url'])->toBe('https://sendify.test/api/sessions/soporte');
});

it('valida la firma de un webhook', function () {
    $payload = '{"event":"message.received"}';
    $secret = 'whsec_prueba';
    $signature = 'sha256='.hash_hmac('sha256', $payload, $secret);

    expect(EverestHome\Sendify\Resources\Webhooks::verifySignature($payload, $signature, $secret))->toBeTrue()
        ->and(EverestHome\Sendify\Resources\Webhooks::verifySignature($payload, 'sha256=falsa', $secret))->toBeFalse();
});

it('rechaza un base64 de más de 25 MB antes de mandarlo', function () {
    [$sendify, $client] = sendify();

    $enorme = str_repeat('A', (int) (26 * 1024 * 1024 * 4 / 3));

    expect(fn () => $sendify->documentMessageTo('5215551234567', $enorme, 'grande.pdf', null, 'application/pdf'))
        ->toThrow(EverestHome\Sendify\Exceptions\ValidationException::class, 'límite de Sendify es 25 MB')
        ->and($client->requests)->toBeEmpty();
});

it('lee los errores por campo de un 422', function () {
    $client = (new FakeClient())->push(422, [
        'success' => false,
        'error' => 'Los datos enviados no son válidos',
        'messages' => [
            ['field' => 'chatId', 'rule' => 'required', 'message' => 'El campo chatId es obligatorio'],
        ],
    ]);

    [$sendify] = sendify($client);

    try {
        $sendify->textMessageTo('5215551234567', 'Hola');
        expect(false)->toBeTrue();
    } catch (EverestHome\Sendify\Exceptions\ValidationException $e) {
        expect($e->errors())->toHaveCount(1)
            ->and($e->errors()[0]['field'])->toBe('chatId');
    }
});

it('consulta la sonda de vida del servidor', function () {
    [$sendify, $client] = sendify((new FakeClient())->push(200, ['status' => 'alive']));

    $sendify->healthLive();

    expect($client->lastRequest()['url'])->toBe('https://sendify.test/health/live');
});
