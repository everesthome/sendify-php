<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Http;

use ArrayAccess;
use JsonSerializable;
use ReturnTypeWillChange;

/**
 * Respuesta del servicio. Se comporta como array para leer el JSON directo
 * ($response['data']) y expone atajos para lo que casi siempre se ocupa.
 *
 * @implements ArrayAccess<string, mixed>
 */
final class Response implements ArrayAccess, JsonSerializable
{
    /** @var array<string, mixed>|null */
    private ?array $decoded = null;

    private bool $decodeAttempted = false;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    /**
     * Cuerpo decodificado. Con $key devuelve esa llave con notación de punto.
     *
     * @return mixed
     */
    public function json(?string $key = null, mixed $default = null)
    {
        if (! $this->decodeAttempted) {
            $this->decodeAttempted = true;
            $decoded = json_decode($this->body, true);
            $this->decoded = is_array($decoded) ? $decoded : null;
        }

        if ($key === null) {
            return $this->decoded ?? [];
        }

        $value = $this->decoded ?? [];

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * El nodo `data` de los envíos, o el JSON completo cuando no existe.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $data = $this->json('data');

        return is_array($data) ? $data : $this->toArray();
    }

    /** ID que WhatsApp asignó al mensaje enviado. */
    public function messageId(): ?string
    {
        $id = $this->json('data.messageId') ?? $this->json('messageId');

        return $id === null ? null : (string) $id;
    }

    /** Mensaje de error que devolvió el servicio. */
    public function error(): ?string
    {
        $error = $this->json('error') ?? $this->json('message');

        return is_string($error) ? $error : null;
    }

    /** Segundos sugeridos antes de reintentar (cabecera Retry-After). */
    public function retryAfter(): ?int
    {
        $value = $this->header('retry-after');

        return $value === null ? null : (int) $value;
    }

    public function retryable(): bool
    {
        return $this->json('retryable') === true;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $json = $this->json();

        return is_array($json) ? $json : [];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string) $offset, $this->toArray());
    }

    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->json((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('La respuesta de Sendify es de solo lectura.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('La respuesta de Sendify es de solo lectura.');
    }
}
