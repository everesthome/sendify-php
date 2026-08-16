# Changelog

Todos los cambios relevantes de `everesthome/sendify` se documentan aquí.

## 1.1.0 - 2026-08-16

- Al día con la API del servicio: `Status()` expone `reconnecting`, `connecting`,
  `reconnectAttempts`, `qrAttempt`, `qrExpiresAt`, `maxQrCycles`, `lastActiveAt` y `business`.
- `SendifyException::errors()` lee los errores por campo de un 422 desde `messages`.
- Los medios en base64 se rechazan en el cliente arriba de 25 MB (`Media::MAX_BYTES`).
- `Webhooks::EVENTS` incluye `message.sent`; se documenta el comodín `['*']`.
- Nuevo `healthLive()` para la sonda `GET /health/live`.
- README con el enlace a Packagist y las reglas de medios (25 MB y URL pública).

## 1.0.0 - 2026-08-16

- Primera versión: cliente PHP del servicio Sendify e integración con Laravel
  (service provider, configuración publicable y fachada `Sendify`).
- `Sendify::Status()` devuelve un diagnóstico que no lanza excepciones y
  distingue instancia inexistente, cuenta suspendida, API key expirada, IP no
  autorizada, sin vincular, hibernando o servidor caído.
