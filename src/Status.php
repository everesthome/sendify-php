<?php

declare(strict_types=1);

namespace EverestHome\Sendify;

use ArrayAccess;
use EverestHome\Sendify\Enums\InstanceState;
use EverestHome\Sendify\Exceptions\ConnectionException;
use EverestHome\Sendify\Exceptions\SendifyException;
use EverestHome\Sendify\Http\Response;
use JsonSerializable;
use ReturnTypeWillChange;
use Stringable;

/**
 * Diagnóstico de una instancia. Nunca lanza excepciones: si el servidor está
 * caído, la cuenta suspendida o la instancia no existe, eso mismo es el estado.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Status implements ArrayAccess, JsonSerializable, Stringable
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly InstanceState $state,
        public readonly string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?Response $response = null,
        public readonly array $details = [],
    ) {
    }

    /** Interpreta la respuesta de GET /api/sessions/:instanceId. */
    public static function fromResponse(Response $response): self
    {
        $reported = (string) ($response->json('status') ?? '');
        $hasCredentials = $response->json('hasCredentials');
        $reason = $response->json('hibernationReason');

        $state = match ($reported) {
            'connected' => InstanceState::Connected,
            'connecting' => InstanceState::Connecting,
            'qr_ready' => InstanceState::QrReady,
            'hibernated' => InstanceState::Hibernated,
            'disconnected' => InstanceState::Disconnected,
            default => InstanceState::Unknown,
        };

        // Desactivar una instancia la hiberna con ese motivo: para la aplicación
        // que la usa es una cuenta suspendida, no una siesta.
        if ($state === InstanceState::Hibernated && is_string($reason) && preg_match('/desactivad|suspend/i', $reason) === 1) {
            $state = InstanceState::Suspended;
        }

        // Sin credenciales guardadas nadie ha vinculado el teléfono todavía,
        // o WhatsApp cerró la sesión y hay que volver a escanear.
        if ($hasCredentials === false && in_array($state, [InstanceState::Hibernated, InstanceState::Disconnected, InstanceState::Unknown], true)) {
            $state = InstanceState::Unlinked;
        }

        $message = $state->label();

        if ($state === InstanceState::Hibernated && is_string($reason) && $reason !== '') {
            $message .= ' — motivo: '.$reason;
        }

        return new self($state, $message, $response->status(), $response, $response->toArray());
    }

    /** Traduce el rechazo del servicio a un estado de cuenta. */
    public static function fromException(SendifyException $exception): self
    {
        if ($exception instanceof ConnectionException) {
            return new self(InstanceState::Unreachable, $exception->getMessage());
        }

        $response = $exception->response;
        $httpStatus = $response?->status();
        $error = $exception->getMessage();

        $state = match (true) {
            $httpStatus === 401 && self::matches($error, '/expirad/i') => InstanceState::KeyExpired,
            $httpStatus === 401 && self::matches($error, '/requerida/i') => InstanceState::MissingCredentials,
            $httpStatus === 401 => InstanceState::Suspended,
            $httpStatus === 403 && self::matches($error, '/\bip\b/i') => InstanceState::IpNotAllowed,
            $httpStatus === 403 && self::matches($error, '/no pertenece/i') => InstanceState::InstanceNotFound,
            $httpStatus === 403 => InstanceState::InsufficientRole,
            $httpStatus === 404 => InstanceState::InstanceNotFound,
            // Un 409 es una instancia registrada que no está conectada. Sin
            // este caso, Status::fromException() de un envío rechazado —el
            // fallo más común— contestaba "desconocido".
            $httpStatus === 409 => InstanceState::Disconnected,
            $httpStatus === 429 => InstanceState::RateLimited,
            $httpStatus === 503 && $response?->retryable() === true => InstanceState::Hibernated,
            $httpStatus !== null && $httpStatus >= 500 => InstanceState::ServerError,
            default => InstanceState::Unknown,
        };

        return new self(
            $state,
            $error !== '' ? $error : $state->label(),
            $httpStatus,
            $response,
            $response?->toArray() ?? [],
        );
    }

    public function is(InstanceState ...$states): bool
    {
        return in_array($this->state, $states, true);
    }

    /** Valor plano del estado: 'connected', 'suspended', 'instance_not_found'… */
    public function value(): string
    {
        return $this->state->value;
    }

    /** Se puede enviar ahora (conectada, o hibernando y despierta sola). */
    public function canSend(): bool
    {
        return $this->state->canSend();
    }

    public function connected(): bool
    {
        return $this->state === InstanceState::Connected;
    }

    public function hibernated(): bool
    {
        return $this->state === InstanceState::Hibernated;
    }

    /** Problema de cuenta: suspendida, key expirada, IP bloqueada, no existe. */
    public function accountProblem(): bool
    {
        return $this->state->isAccountProblem();
    }

    public function suspended(): bool
    {
        return $this->is(InstanceState::Suspended, InstanceState::KeyExpired);
    }

    /** Alguien tiene que escanear el QR o revisar el teléfono. */
    public function needsAttention(): bool
    {
        return $this->state->needsAttention();
    }

    /** Nada corriendo: hace falta un POST /start antes de ver un QR. */
    public function needsStart(): bool
    {
        return ($this->details['needsStart'] ?? false) === true;
    }

    public function hasCredentials(): bool
    {
        return ($this->details['hasCredentials'] ?? false) === true;
    }

    /** Hay un reintento de conexión ya programado: no está ociosa. */
    public function reconnecting(): bool
    {
        return ($this->details['reconnecting'] ?? false) === true;
    }

    /** El socket se está abriendo y todavía no llega ningún QR. */
    public function connecting(): bool
    {
        return ($this->details['connecting'] ?? false) === true;
    }

    /** Fallos acumulados desde la última conexión buena. */
    public function reconnectAttempts(): int
    {
        return (int) ($this->details['reconnectAttempts'] ?? 0);
    }

    /** Cuándo caduca el QR vigente (vive 60 segundos). */
    public function qrExpiresAt(): ?string
    {
        $value = $this->details['qrExpiresAt'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** QR dibujados en este arranque. */
    public function qrAttempt(): int
    {
        return (int) ($this->details['qrAttempt'] ?? 0);
    }

    /** Presupuesto de QR antes de hibernar; null si la hibernación está apagada. */
    public function maxQrCycles(): ?int
    {
        $value = $this->details['maxQrCycles'] ?? null;

        return is_int($value) ? $value : null;
    }

    public function hibernationReason(): ?string
    {
        $reason = $this->details['hibernationReason'] ?? null;

        return is_string($reason) ? $reason : null;
    }

    public function instanceId(): int|string|null
    {
        return $this->details['instance'] ?? null;
    }

    public function instanceName(): ?string
    {
        $name = $this->details['name'] ?? null;

        return is_string($name) ? $name : null;
    }

    /** Número de WhatsApp (sin +) que envía los mensajes, o null si no está vinculado. */
    public function phone(): ?string
    {
        $value = $this->details['phone'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** Nombre del perfil de WhatsApp, o null. */
    public function pushName(): ?string
    {
        $value = $this->details['pushName'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** Fecha ISO de la última conexión con WhatsApp. */
    public function lastConnectionAt(): ?string
    {
        $value = $this->details['lastConnectionAt'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** Última actividad registrada, ISO 8601. */
    public function lastActiveAt(): ?string
    {
        $value = $this->details['lastActiveAt'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** El negocio dueño de la instancia, tal cual lo serializa el servicio. */
    public function business(): array
    {
        $business = $this->details['business'] ?? null;

        return is_array($business) ? $business : [];
    }

    public function hibernatedAt(): ?string
    {
        $value = $this->details['hibernatedAt'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'message' => $this->message,
            'canSend' => $this->canSend(),
            'accountProblem' => $this->accountProblem(),
            'needsAttention' => $this->needsAttention(),
            'httpStatus' => $this->httpStatus,
            'details' => $this->details,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->state->value.': '.$this->message;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->toArray());
    }

    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->toArray()[(string) $offset] ?? $this->details[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('El estado de Sendify es de solo lectura.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('El estado de Sendify es de solo lectura.');
    }

    private static function matches(string $subject, string $pattern): bool
    {
        return preg_match($pattern, $subject) === 1;
    }
}
