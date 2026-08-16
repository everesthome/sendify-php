<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Exceptions;

/**
 * La instancia estaba hibernando y no despertó dentro de wakeTimeoutMs.
 * El servicio marca esta respuesta como reintentable.
 */
final class InstanceAsleepException extends SendifyException
{
    public function retryAfter(): int
    {
        return $this->response?->retryAfter() ?? 5;
    }
}
