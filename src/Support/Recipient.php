<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Support;

use EverestHome\Sendify\Exceptions\ValidationException;

final class Recipient
{
    /**
     * Acepta "+52 55 1234 5678", "5215551234567", "521...@c.us" o un JID de
     * grupo. Los JID completos se respetan tal cual; lo demás queda en dígitos,
     * que es lo que el servicio resuelve contra WhatsApp.
     */
    public static function normalize(string $to): string
    {
        $value = trim($to);

        if ($value === '') {
            throw new ValidationException('El destinatario (chatId) es requerido.');
        }

        if (str_ends_with($value, '@g.us') || str_ends_with($value, '@s.whatsapp.net')) {
            return $value;
        }

        $digits = preg_replace('/\D+/', '', preg_replace('/@c\.us$/', '', $value)) ?? '';

        if ($digits === '') {
            throw new ValidationException(sprintf('El destinatario "%s" no es un número válido.', $to));
        }

        return $digits;
    }
}
