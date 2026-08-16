<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Resources;

use EverestHome\Sendify\Http\Response;
use EverestHome\Sendify\Sendify;

/** Requiere una API key con rol admin. */
final class Webhooks
{
    /** Eventos que publica el servicio. `['*']` suscribe a todos. */
    public const EVENTS = [
        'message.received',
        'message.sent',
        'message.status',
        'connection.updated',
        'call.received',
    ];

    public function __construct(private readonly Sendify $sendify)
    {
    }

    public function all(): Response
    {
        return $this->sendify->get('/webhooks');
    }

    /**
     * El `secret` de la respuesta solo se muestra una vez: guárdalo para
     * verificar la firma X-Sendify-Signature.
     *
     * Las entregas fallidas se reintentan 5 veces con retroceso exponencial
     * (2^intentos × 15 s).
     *
     * @param array<int, string> $events uno o varios de Webhooks::EVENTS, o ['*'] para todos
     */
    public function create(string $name, string $url, array $events, bool $active = true): Response
    {
        return $this->sendify->post('/webhooks', [
            'name' => $name,
            'url' => $url,
            'events' => array_values($events),
            'active' => $active,
        ]);
    }

    /** @param array{name?: string, url?: string, events?: array<int, string>, active?: bool} $attributes */
    public function update(int|string $webhookId, array $attributes): Response
    {
        return $this->sendify->put('/webhooks/'.rawurlencode((string) $webhookId), $attributes);
    }

    public function test(int|string $webhookId): Response
    {
        return $this->sendify->post('/webhooks/'.rawurlencode((string) $webhookId).'/test');
    }

    public function delete(int|string $webhookId): Response
    {
        return $this->sendify->delete('/webhooks/'.rawurlencode((string) $webhookId));
    }

    /** @param array<string, scalar|null> $filters */
    public function deliveries(array $filters = []): Response
    {
        return $this->sendify->get('/webhook-deliveries', $filters);
    }

    /**
     * Valida la cabecera X-Sendify-Signature de una entrega.
     *
     * @param string $payload cuerpo crudo de la petición
     * @param string $signature valor de X-Sendify-Signature (sha256=...)
     */
    public static function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, trim($signature));
    }
}
