<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Exceptions;

/** No se pudo hablar con el servidor: DNS, TLS, timeout de red. */
final class ConnectionException extends SendifyException
{
}
