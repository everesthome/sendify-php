<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Http;

use EverestHome\Sendify\Config\Connection;
use EverestHome\Sendify\Exceptions\ConnectionException;

/**
 * Cliente HTTP con cURL: sin dependencias, funciona igual en Laravel que en
 * PHP puro. Se puede sustituir implementando ClientInterface.
 */
final class CurlClient implements ClientInterface
{
    public function __construct(private readonly string $userAgent = 'sendify-php')
    {
    }

    public function send(
        Connection $connection,
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        array $query = [],
    ): Response {
        $query = array_filter($query, static fn ($value) => $value !== null);

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        $handle = curl_init();
        $responseHeaders = [];

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $connection->timeout,
            CURLOPT_CONNECTTIMEOUT => $connection->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => $connection->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $connection->verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_HEADERFUNCTION => function ($_handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($result === false) {
            throw new ConnectionException(sprintf('No se pudo conectar con Sendify (%s): %s', $url, $error));
        }

        return new Response($status, $responseHeaders, (string) $result);
    }

    /**
     * @param array<string, string> $headers
     * @return array<int, string>
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];

        foreach ($headers as $name => $value) {
            $formatted[] = $name.': '.$value;
        }

        return $formatted;
    }
}
