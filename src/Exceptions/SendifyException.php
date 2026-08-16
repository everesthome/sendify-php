<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Exceptions;

use EverestHome\Sendify\Http\Response;
use RuntimeException;

class SendifyException extends RuntimeException
{
    public ?Response $response = null;

    public static function fromResponse(Response $response): self
    {
        $message = $response->error() ?? 'Sendify respondió con el estado '.$response->status();

        $exception = match (true) {
            $response->status() === 401, $response->status() === 403 => new AuthenticationException($message, $response->status()),
            $response->status() === 404 => new NotFoundException($message, 404),
            $response->status() === 409 => new InstanceNotConnectedException($message, 409),
            $response->status() === 422, $response->status() === 400 => new ValidationException($message, $response->status()),
            $response->status() === 429 => new RateLimitException($message, 429),
            $response->status() === 503 && $response->retryable() => new InstanceAsleepException($message, 503),
            default => new self($message, $response->status()),
        };

        $exception->response = $response;

        return $exception;
    }

    /**
     * Errores por campo de un 422. El servicio los manda en `messages`, con el
     * formato de VineJS: [{"field": "...", "rule": "...", "message": "..."}].
     *
     * @return array<int|string, mixed>
     */
    public function errors(): array
    {
        $errors = $this->response?->json('messages') ?? $this->response?->json('errors');

        return is_array($errors) ? $errors : [];
    }
}
