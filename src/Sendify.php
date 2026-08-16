<?php

declare(strict_types=1);

namespace EverestHome\Sendify;

use EverestHome\Sendify\Config\Connection;
use EverestHome\Sendify\Exceptions\SendifyException;
use EverestHome\Sendify\Http\ClientInterface;
use EverestHome\Sendify\Http\CurlClient;
use EverestHome\Sendify\Http\Response;
use EverestHome\Sendify\Resources\Automations;
use EverestHome\Sendify\Resources\Profile;
use EverestHome\Sendify\Resources\Statuses;
use EverestHome\Sendify\Resources\Templates;
use EverestHome\Sendify\Resources\Webhooks;
use EverestHome\Sendify\Support\Media;
use EverestHome\Sendify\Support\Recipient;

/**
 * Cliente de una instancia de Sendify.
 *
 * Los nombres de método en PHP no distinguen mayúsculas, así que
 * Sendify::TextMessageTo() y Sendify::textMessageTo() son la misma llamada.
 */
class Sendify
{
    private readonly ClientInterface $http;

    public function __construct(
        private readonly Connection $connection,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new CurlClient();
    }

    /**
     * Cliente sin framework:
     * Sendify::make('https://sendify.midominio.mx', 'snd_live_...', 'ventas')
     */
    public static function make(string $url, string $client, string $instance, array $options = []): self
    {
        return new self(Connection::fromArray($options + [
            'url' => $url,
            'client' => $client,
            'instance' => $instance,
        ]));
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /** Misma URL y API key, otra instancia (si la key la autoriza). */
    public function instance(string $instance): self
    {
        return new self($this->connection->withInstance($instance), $this->http);
    }

    public function withHttpClient(ClientInterface $http): self
    {
        return new self($this->connection, $http);
    }

    /** Envío encadenado: $sendify->to('5215551234567')->text('Hola'). */
    public function to(string $to): PendingMessage
    {
        return new PendingMessage($this, $to);
    }

    // ------------------------------------------------------------------
    // Envío de mensajes
    // ------------------------------------------------------------------

    public function textMessageTo(string $to, string $text): Response
    {
        return $this->post('/messages/send-text', [
            'chatId' => Recipient::normalize($to),
            'text' => $text,
        ]);
    }

    /** @param string $source URL, ruta local, data URI o base64 */
    public function imageMessageTo(string $to, string $source, ?string $caption = null, ?string $mimetype = null): Response
    {
        return $this->sendMedia('image', $to, $source, $mimetype, null, $caption);
    }

    public function videoMessageTo(string $to, string $source, ?string $caption = null, ?string $mimetype = null): Response
    {
        return $this->sendMedia('video', $to, $source, $mimetype, null, $caption);
    }

    /** $ptt = true lo manda como nota de voz. */
    public function audioMessageTo(string $to, string $source, bool $ptt = false, ?string $mimetype = null): Response
    {
        return $this->sendMedia('audio', $to, $source, $mimetype, null, null, ['ptt' => $ptt]);
    }

    public function documentMessageTo(
        string $to,
        string $source,
        ?string $filename = null,
        ?string $caption = null,
        ?string $mimetype = null,
    ): Response {
        return $this->sendMedia('document', $to, $source, $mimetype, $filename, $caption);
    }

    public function stickerMessageTo(string $to, string $source, ?string $mimetype = null): Response
    {
        return $this->sendMedia('sticker', $to, $source, $mimetype);
    }

    public function locationMessageTo(
        string $to,
        float $latitude,
        float $longitude,
        ?string $description = null,
        ?string $address = null,
    ): Response {
        return $this->post('/messages/send-location', array_filter([
            'chatId' => Recipient::normalize($to),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'description' => $description,
            'address' => $address,
        ], static fn ($value) => $value !== null));
    }

    public function contactMessageTo(string $to, string $contactName, string $contactNumber): Response
    {
        return $this->post('/messages/send-contact', [
            'chatId' => Recipient::normalize($to),
            'contactName' => $contactName,
            'contactNumber' => $contactNumber,
        ]);
    }

    /** @param array<int, string> $options entre 2 y 12 opciones */
    public function pollMessageTo(string $to, string $name, array $options, ?int $selectableCount = null): Response
    {
        return $this->post('/messages/send-poll', array_filter([
            'chatId' => Recipient::normalize($to),
            'name' => $name,
            'options' => array_values($options),
            'selectableCount' => $selectableCount,
        ], static fn ($value) => $value !== null));
    }

    /** @param array<string, scalar> $variables reemplaza los {{marcadores}} de la plantilla */
    public function templateMessageTo(string $to, string $template, array $variables = []): Response
    {
        return $this->post('/messages/send-template', array_filter([
            'chatId' => Recipient::normalize($to),
            'template' => $template,
            'variables' => $variables === [] ? null : $variables,
        ], static fn ($value) => $value !== null));
    }

    /**
     * Envío masivo asíncrono. Devuelve el lote; su avance se consulta con
     * batch($batchId).
     *
     * Acepta [['chatId' => '52...', 'text' => 'Hola'], ...] o ['52...' => 'Hola'].
     *
     * @param array<int|string, array{chatId?: string, to?: string, text: string}|string> $items
     */
    public function bulkMessages(array $items): Response
    {
        $normalized = [];

        foreach ($items as $key => $item) {
            if (is_string($item)) {
                $normalized[] = ['chatId' => Recipient::normalize((string) $key), 'text' => $item];

                continue;
            }

            $normalized[] = [
                'chatId' => Recipient::normalize((string) ($item['chatId'] ?? $item['to'] ?? '')),
                'text' => (string) $item['text'],
            ];
        }

        return $this->post('/messages/send-bulk', ['items' => $normalized]);
    }

    public function batch(string $batchId): Response
    {
        return $this->get('/messages/batch/'.rawurlencode($batchId));
    }

    public function cancelBatch(string $batchId): Response
    {
        return $this->post('/messages/batch/'.rawurlencode($batchId).'/cancel');
    }

    // ------------------------------------------------------------------
    // Acciones sobre mensajes
    // ------------------------------------------------------------------

    public function replyTo(string $to, string $messageId, string $text): Response
    {
        return $this->post('/messages/reply', [
            'chatId' => Recipient::normalize($to),
            'messageId' => $messageId,
            'text' => $text,
        ]);
    }

    public function forwardTo(string $to, string $messageId): Response
    {
        return $this->post('/messages/forward', [
            'chatId' => Recipient::normalize($to),
            'messageId' => $messageId,
        ]);
    }

    public function react(string $messageId, string $emoji): Response
    {
        return $this->post('/messages/react', ['messageId' => $messageId, 'emoji' => $emoji]);
    }

    public function editMessage(string $messageId, string $text): Response
    {
        return $this->post('/messages/edit', ['messageId' => $messageId, 'text' => $text]);
    }

    public function deleteMessage(string $messageId): Response
    {
        return $this->post('/messages/delete', ['messageId' => $messageId]);
    }

    /** @param int|null $durationSeconds 86400, 604800 o 2592000 */
    public function pinMessage(string $messageId, ?int $durationSeconds = null): Response
    {
        return $this->post('/messages/pin', array_filter([
            'messageId' => $messageId,
            'durationSeconds' => $durationSeconds,
        ], static fn ($value) => $value !== null));
    }

    public function unpinMessage(string $messageId): Response
    {
        return $this->post('/messages/unpin', ['messageId' => $messageId]);
    }

    public function starMessage(string $messageId, bool $star = true): Response
    {
        return $this->post('/messages/star', ['messageId' => $messageId, 'star' => $star]);
    }

    /**
     * Historial paginado.
     *
     * @param array{chatId?: string, direction?: string, type?: string, status?: string, search?: string, page?: int, limit?: int} $filters
     */
    public function messages(array $filters = []): Response
    {
        if (isset($filters['chatId'])) {
            $filters['chatId'] = Recipient::normalize((string) $filters['chatId']);
        }

        return $this->get('/messages', $filters);
    }

    /** Descarga el medio de un mensaje: el binario queda en $response->body(). */
    public function messageMedia(string $messageId): Response
    {
        return $this->get('/messages/'.rawurlencode($messageId).'/media');
    }

    public function messageReactions(string $messageId): Response
    {
        return $this->get('/messages/'.rawurlencode($messageId).'/reactions');
    }

    // ------------------------------------------------------------------
    // Ciclo de vida de la instancia
    // ------------------------------------------------------------------

    /**
     * Diagnóstico de la instancia que no lanza excepciones: distingue entre
     * conectada, hibernando, sin vincular, instancia inexistente, cuenta
     * suspendida, API key expirada, IP no autorizada o servidor caído.
     *
     *   $estado = Sendify::Status();
     *   $estado->state;          // InstanceState::Suspended
     *   $estado->value();        // 'suspended'
     *   $estado->message;        // texto listo para mostrar
     *   $estado->canSend();      // false
     */
    public function status(): Status
    {
        try {
            return Status::fromResponse($this->get('/'));
        } catch (SendifyException $exception) {
            return Status::fromException($exception);
        }
    }

    /** La respuesta cruda de GET /api/sessions/:instanceId; ésta sí lanza excepciones. */
    public function statusResponse(): Response
    {
        return $this->get('/');
    }

    /**
     * Salud del servidor Sendify (GET /health), sin API key de por medio:
     * sirve para distinguir "servidor caído" de "problema con esta cuenta".
     */
    public function health(): Response
    {
        $response = $this->http->send(
            $this->connection,
            'GET',
            $this->connection->url.'/health',
            ['Accept' => 'application/json'],
        );

        if ($response->failed()) {
            throw SendifyException::fromResponse($response);
        }

        return $response;
    }

    /**
     * Sonda de vida (GET /health/live): responde 200 mientras el proceso viva,
     * aunque la base de datos esté caída. `health()` sí revisa la base y
     * contesta 503 cuando no la alcanza.
     */
    public function healthLive(): Response
    {
        $response = $this->http->send(
            $this->connection,
            'GET',
            $this->connection->url.'/health/live',
            ['Accept' => 'application/json'],
        );

        if ($response->failed()) {
            throw SendifyException::fromResponse($response);
        }

        return $response;
    }

    /** ¿Está de pie el servidor Sendify? No mira la instancia ni la API key. */
    public function serverReachable(): bool
    {
        try {
            return $this->health()->successful();
        } catch (SendifyException) {
            return false;
        }
    }

    public function connected(): bool
    {
        return $this->status()->connected();
    }

    public function start(): Response
    {
        return $this->post('/start');
    }

    public function stop(): Response
    {
        return $this->post('/stop');
    }

    public function hibernate(): Response
    {
        return $this->post('/hibernate');
    }

    public function wake(): Response
    {
        return $this->post('/wake');
    }

    /** Desvincula el teléfono y **borra** la sesión: para volver, QR nuevo. */
    public function logout(): Response
    {
        return $this->post('/logout');
    }

    public function forceKill(): Response
    {
        return $this->post('/force-kill');
    }

    /**
     * Código QR vigente: { qr, qrExpiresAt, qrAttempt }. Cada código vive 60
     * segundos y los caducados nunca se sirven (`qr: null`); mientras el socket
     * esté arriba llega uno nuevo solo. Leer el QR no arranca nada: si la
     * instancia duerme responde `qr: null` y `needsStart: true` hasta que
     * alguien llame a start().
     */
    public function qr(): Response
    {
        return $this->get('/qr');
    }


    public function pairingCode(string $phoneNumber, ?string $customCode = null): Response
    {
        return $this->post('/pairing-code', array_filter([
            'phoneNumber' => $phoneNumber,
            'customCode' => $customCode,
        ], static fn ($value) => $value !== null));
    }

    public function config(): Response
    {
        return $this->get('/config');
    }

    /**
     * @param array{hibernationEnabled?: bool, idleTimeoutMs?: int, wakeTimeoutMs?: int, maxQrCycles?: int, syncFullHistory?: bool, active?: bool, autoReconnect?: bool, maxReconnectAttempts?: int, reconnectDelayMs?: int} $config
     */
    public function updateConfig(array $config): Response
    {
        return $this->request('PATCH', '/config', $config);
    }

    public function stats(): Response
    {
        return $this->get('/stats');
    }

    // ------------------------------------------------------------------
    // Recursos
    // ------------------------------------------------------------------

    public function webhooks(): Webhooks
    {
        return new Webhooks($this);
    }

    public function templates(): Templates
    {
        return new Templates($this);
    }

    public function automations(): Automations
    {
        return new Automations($this);
    }

    public function profile(): Profile
    {
        return new Profile($this);
    }

    /** Estados de WhatsApp (historias). */
    public function statuses(): Statuses
    {
        return new Statuses($this);
    }

    // ------------------------------------------------------------------
    // HTTP
    // ------------------------------------------------------------------

    /** @param array<string, scalar|null> $query */
    public function get(string $path, array $query = []): Response
    {
        return $this->request('GET', $path, null, $query);
    }

    /** @param array<string, mixed>|null $body */
    public function post(string $path, ?array $body = null): Response
    {
        return $this->request('POST', $path, $body ?? []);
    }

    /** @param array<string, mixed>|null $body */
    public function put(string $path, ?array $body = null): Response
    {
        return $this->request('PUT', $path, $body ?? []);
    }

    public function delete(string $path): Response
    {
        return $this->request('DELETE', $path);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, scalar|null> $query
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): Response
    {
        $url = $this->connection->baseUrl().($path === '/' ? '' : $path);
        $payload = $body === null ? null : (json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        $headers = [
            'X-API-Key' => $this->connection->client,
            'Accept' => 'application/json',
        ];

        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $attempts = $this->connection->retries + 1;

        for ($attempt = 1; ; $attempt++) {
            $response = $this->http->send($this->connection, $method, $url, $headers, $payload, $query);

            if ($response->successful()) {
                return $response;
            }

            // La instancia hibernada contesta 503 + Retry-After mientras despierta,
            // y el límite de peticiones 429: ambos se reintentan solos.
            $retryable = ($response->status() === 503 && $response->retryable()) || $response->status() === 429;

            if (! $retryable || $attempt >= $attempts) {
                throw SendifyException::fromResponse($response);
            }

            sleep(max(1, min(30, $response->retryAfter() ?? 5)));
        }
    }

    /** @param array<string, mixed> $extra */
    private function sendMedia(
        string $kind,
        string $to,
        string $source,
        ?string $mimetype = null,
        ?string $filename = null,
        ?string $caption = null,
        array $extra = [],
    ): Response {
        $payload = ['chatId' => Recipient::normalize($to)]
            + Media::payload($source, $mimetype, $filename)
            + $extra;

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        return $this->post('/messages/send-'.$kind, $payload);
    }
}
