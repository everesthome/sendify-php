<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Config;

use EverestHome\Sendify\Exceptions\ConfigurationException;

/**
 * Credenciales de una instancia de Sendify: a dónde apuntar, con qué API key y
 * sobre qué instancia trabajar.
 */
final class Connection
{
    public function __construct(
        public readonly string $url,
        public readonly string $client,
        public readonly string $instance,
        public readonly int $timeout = 30,
        public readonly int $connectTimeout = 10,
        public readonly int $retries = 1,
        public readonly bool $verifySsl = true,
    ) {
        if ($this->url === '') {
            throw ConfigurationException::missing('url', 'SENDIFY_URL');
        }

        if ($this->client === '') {
            throw ConfigurationException::missing('client', 'SENDIFY_CLIENT');
        }

        if ($this->instance === '') {
            throw ConfigurationException::missing('instance', 'SENDIFY_INSTANCE');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            url: rtrim(trim((string) ($config['url'] ?? '')), '/'),
            client: trim((string) ($config['client'] ?? $config['api_key'] ?? '')),
            instance: trim((string) ($config['instance'] ?? '')),
            timeout: (int) ($config['timeout'] ?? 30),
            connectTimeout: (int) ($config['connect_timeout'] ?? 10),
            retries: max(0, (int) ($config['retries'] ?? 1)),
            verifySsl: (bool) ($config['verify_ssl'] ?? true),
        );
    }

    /** Base de las rutas OpenWA del servicio: /api/sessions/:instanceId */
    public function baseUrl(): string
    {
        return $this->url.'/api/sessions/'.rawurlencode($this->instance);
    }

    public function withInstance(string $instance): self
    {
        return new self(
            url: $this->url,
            client: $this->client,
            instance: $instance,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            retries: $this->retries,
            verifySsl: $this->verifySsl,
        );
    }
}
