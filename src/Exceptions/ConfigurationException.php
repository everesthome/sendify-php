<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Exceptions;

use InvalidArgumentException;

final class ConfigurationException extends InvalidArgumentException
{
    public static function missing(string $key, string $env): self
    {
        return new self(sprintf('Falta la configuración "%s" de Sendify. Define %s en tu .env.', $key, $env));
    }

    public static function unknownConnection(string $name): self
    {
        return new self(sprintf('La conexión "%s" de Sendify no está configurada en config/sendify.php.', $name));
    }
}
