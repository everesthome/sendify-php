<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Support;

use EverestHome\Sendify\Exceptions\ValidationException;

final class Media
{
    /** El servicio rechaza cualquier base64 que pese más de 25 MB ya decodificado. */
    public const MAX_BYTES = 25 * 1024 * 1024;

    /**
     * Convierte lo que se le pase en el cuerpo que espera el servicio.
     *
     * - URL http/https  -> ["url" => ...]
     * - Ruta local      -> ["base64" => ..., "mimetype" => ..., "filename" => ...]
     * - data: URI       -> ["base64" => ..., "mimetype" => ...]
     * - base64 pelón    -> ["base64" => ...] (requiere $mimetype)
     *
     * La URL la descarga el servidor y debe resolver a una dirección pública:
     * localhost, LAN privada, link-local y CGNAT se rechazan con 400 (salvo que
     * el servicio corra con ALLOW_PRIVATE_MEDIA_URLS=true).
     *
     * @return array<string, string>
     */
    public static function payload(string $source, ?string $mimetype = null, ?string $filename = null): array
    {
        $source = trim($source);

        if ($source === '') {
            throw new ValidationException('El medio a enviar no puede ir vacío.');
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            return array_filter([
                'url' => $source,
                'mimetype' => $mimetype,
                'filename' => $filename,
            ], static fn ($value) => $value !== null);
        }

        if (str_starts_with($source, 'data:')) {
            [$header, $data] = array_pad(explode(',', $source, 2), 2, '');
            $mimetype ??= trim(explode(';', substr($header, 5))[0] ?: '') ?: null;
            $source = $data;
        } elseif (self::isReadableFile($source)) {
            $filename ??= basename($source);
            $mimetype ??= self::guessMimetype($source);
            $contents = file_get_contents($source);

            if ($contents === false) {
                throw new ValidationException(sprintf('No se pudo leer el archivo "%s".', $source));
            }

            $source = base64_encode($contents);
        }

        if ($mimetype === null) {
            throw new ValidationException('mimetype es requerido cuando el medio se envía en base64.');
        }

        // Mejor fallar aquí que subir 30 MB para que el servicio los rechace.
        $bytes = (int) floor(strlen(rtrim($source, '=')) * 3 / 4);

        if ($bytes > self::MAX_BYTES) {
            throw new ValidationException(sprintf(
                'El archivo pesa %.1f MB y el límite de Sendify es 25 MB.',
                $bytes / 1024 / 1024
            ));
        }

        return array_filter([
            'base64' => $source,
            'mimetype' => $mimetype,
            'filename' => $filename,
        ], static fn ($value) => $value !== null);
    }

    private static function isReadableFile(string $source): bool
    {
        // Un base64 largo reventaría is_file() en algunos sistemas de archivos.
        return strlen($source) < 4096 && ! str_contains($source, "\n") && is_file($source) && is_readable($source);
    }

    private static function guessMimetype(string $path): ?string
    {
        if (class_exists(\finfo::class)) {
            $mimetype = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

            if (is_string($mimetype) && $mimetype !== '') {
                return $mimetype;
            }
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'pdf' => 'application/pdf',
            default => null,
        };
    }
}
