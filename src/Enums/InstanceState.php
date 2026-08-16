<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Enums;

/**
 * Estado consolidado de una instancia: mezcla lo que reporta
 * GET /api/sessions/:instanceId con el motivo por el que el servicio rechazó
 * la petición, para no tener que interpretar códigos HTTP en la aplicación.
 */
enum InstanceState: string
{
    /** Conectada a WhatsApp: se puede enviar. */
    case Connected = 'connected';

    /** Levantando el socket; en segundos suele quedar conectada. */
    case Connecting = 'connecting';

    /** Hay un QR esperando a que alguien lo escanee. */
    case QrReady = 'qr_ready';

    /** Vinculada pero sin socket vivo; el siguiente envío la despierta. */
    case Hibernated = 'hibernated';

    /** Registrada, con sesión guardada, pero desconectada de WhatsApp. */
    case Disconnected = 'disconnected';

    /** Nunca se vinculó un teléfono, o se cerró la sesión: hay que escanear QR. */
    case Unlinked = 'unlinked';

    /** La instancia no existe o la API key no pertenece a ella. */
    case InstanceNotFound = 'instance_not_found';

    /** Instancia desactivada o API key revocada: cuenta suspendida. */
    case Suspended = 'suspended';

    /** La API key expiró: renovación o cobro pendiente. */
    case KeyExpired = 'key_expired';

    /** No se mandó API key. */
    case MissingCredentials = 'missing_credentials';

    /** La IP de este servidor no está en la lista blanca de la API key. */
    case IpNotAllowed = 'ip_not_allowed';

    /** La API key existe pero su rol no alcanza para la operación. */
    case InsufficientRole = 'insufficient_role';

    /** Se topó el límite de peticiones por minuto de la API key. */
    case RateLimited = 'rate_limited';

    /** No hubo respuesta del servidor Sendify: caído, DNS, TLS o timeout. */
    case Unreachable = 'unreachable';

    /** El servidor Sendify respondió con un error interno. */
    case ServerError = 'server_error';

    case Unknown = 'unknown';

    /** Texto listo para mostrar en un panel o un log. */
    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Conectada a WhatsApp',
            self::Connecting => 'Conectando',
            self::QrReady => 'Esperando que se escanee el código QR',
            self::Hibernated => 'Hibernando (despierta sola al enviar)',
            self::Disconnected => 'Desconectada de WhatsApp',
            self::Unlinked => 'Sin sesión de WhatsApp vinculada',
            self::InstanceNotFound => 'La instancia no existe o la API key no pertenece a ella',
            self::Suspended => 'Cuenta suspendida: instancia desactivada o API key revocada',
            self::KeyExpired => 'API key expirada: renueva la suscripción o genera una nueva',
            self::MissingCredentials => 'Falta la API key de Sendify',
            self::IpNotAllowed => 'La IP de este servidor no está autorizada para la API key',
            self::InsufficientRole => 'La API key no tiene el rol necesario',
            self::RateLimited => 'Límite de peticiones excedido',
            self::Unreachable => 'No se pudo contactar al servidor Sendify',
            self::ServerError => 'Error interno del servidor Sendify',
            self::Unknown => 'Estado desconocido',
        };
    }

    /** ¿Se puede mandar un mensaje ahora? La hibernada cuenta: despierta sola. */
    public function canSend(): bool
    {
        return $this === self::Connected || $this === self::Hibernated;
    }

    /** ¿Es un problema de cuenta, credenciales o configuración, no de WhatsApp? */
    public function isAccountProblem(): bool
    {
        return in_array($this, [
            self::InstanceNotFound,
            self::Suspended,
            self::KeyExpired,
            self::MissingCredentials,
            self::IpNotAllowed,
            self::InsufficientRole,
        ], true);
    }

    /** ¿Hace falta que una persona escanee el QR o vincule el teléfono? */
    public function needsAttention(): bool
    {
        return $this === self::QrReady || $this === self::Unlinked || $this === self::Disconnected;
    }
}
