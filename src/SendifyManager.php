<?php

declare(strict_types=1);

namespace EverestHome\Sendify;

use EverestHome\Sendify\Config\Connection;
use EverestHome\Sendify\Exceptions\ConfigurationException;
use EverestHome\Sendify\Http\ClientInterface;

/**
 * Resuelve las conexiones configuradas y delega en el cliente por defecto,
 * de modo que Sendify::textMessageTo() use la conexión "default" y
 * Sendify::connection('otra')->textMessageTo() use otra instancia o servidor.
 *
 * @mixin Sendify
 */
class SendifyManager
{
    /** @var array<string, Sendify> */
    private array $resolved = [];

    /** @param array<string, mixed> $config contenido de config/sendify.php */
    public function __construct(
        private readonly array $config,
        private readonly ?ClientInterface $http = null,
    ) {
    }

    public function connection(?string $name = null): Sendify
    {
        $name ??= (string) ($this->config['default'] ?? 'default');

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $connections = $this->config['connections'] ?? [];

        if (! is_array($connections) || ! isset($connections[$name]) || ! is_array($connections[$name])) {
            throw ConfigurationException::unknownConnection($name);
        }

        return $this->resolved[$name] = $this->build($connections[$name]);
    }

    /**
     * Conexión armada al vuelo, por ejemplo con credenciales guardadas por
     * cliente en la base de datos.
     *
     * @param array<string, mixed> $config
     */
    public function build(array $config): Sendify
    {
        $defaults = [
            'timeout' => $this->config['timeout'] ?? 30,
            'connect_timeout' => $this->config['connect_timeout'] ?? 10,
            'retries' => $this->config['retries'] ?? 1,
            'verify_ssl' => $this->config['verify_ssl'] ?? true,
        ];

        return new Sendify(Connection::fromArray($config + $defaults), $this->http);
    }

    /** @param array<int, mixed> $parameters */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->{$method}(...$parameters);
    }
}
