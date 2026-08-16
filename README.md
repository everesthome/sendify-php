# Sendify para PHP y Laravel

Cliente del servicio [Sendify](https://github.com/everesthome/sendify-service) para mandar WhatsApp desde
cualquier aplicación PHP. En Laravel se instala, se ponen tres claves en el `.env` y ya:

```php
Sendify::TextMessageTo('5215551234567', 'Hola desde Laravel');
```

El núcleo no depende de ningún framework: sólo cURL y JSON.

## Instalación

```bash
composer require everesthome/sendify
```

En Laravel el service provider y la fachada `Sendify` se registran solos. La configuración es
opcional:

```bash
php artisan vendor:publish --tag=sendify-config
```

## Configuración

En el `.env` de tu aplicación:

```dotenv
SENDIFY_URL="https://sendify.miempresa.mx"
SENDIFY_CLIENT="snd_live_xxxxxxxxxxxxxxxx"
SENDIFY_INSTANCE="ventas"
```

| Variable           | Qué es                                                                    |
| ------------------ | ------------------------------------------------------------------------- |
| `SENDIFY_URL`      | Base del servidor Sendify de esa empresa (cada empresa puede tener el suyo) |
| `SENDIFY_CLIENT`   | API key de la instancia; viaja en el header `X-API-Key`                    |
| `SENDIFY_INSTANCE` | ID numérico o nombre de la instancia de WhatsApp                           |

También se aceptan sin guion bajo (`SENDIFYURL`, `SENDIFYCLIENT`, `SENDIFYINSTANCE`).

Opcionales: `SENDIFY_TIMEOUT` (30), `SENDIFY_CONNECT_TIMEOUT` (10), `SENDIFY_RETRIES` (1),
`SENDIFY_VERIFY_SSL` (true), `SENDIFY_CONNECTION` (`default`).

## Uso

Los nombres de método en PHP no distinguen mayúsculas, así que `Sendify::TextMessageTo()` y
`Sendify::textMessageTo()` son lo mismo. Elige el estilo que prefieras.

```php
use EverestHome\Sendify\Laravel\Facades\Sendify;

Sendify::TextMessageTo('+52 55 1234 5678', 'Tu pedido va en camino');
Sendify::ImageMessageTo('5215551234567', 'https://cdn.miempresa.mx/promo.jpg', 'Promo del mes');
Sendify::DocumentMessageTo('5215551234567', storage_path('app/facturas/F-1023.pdf'), 'F-1023.pdf', 'Tu factura');
Sendify::LocationMessageTo('5215551234567', 19.4326, -99.1332, 'Sucursal Centro');
Sendify::ContactMessageTo('5215551234567', 'Soporte', '5215557654321');
Sendify::PollMessageTo('5215551234567', '¿Qué horario prefieres?', ['Mañana', 'Tarde']);
Sendify::TemplateMessageTo('5215551234567', 'bienvenida', ['nombre' => 'Jovan']);
```

El número se limpia solo: `+52 55 1234 5678`, `5215551234567` y `5215551234567@c.us` llegan igual.
Los JID de grupo (`...@g.us`) se respetan tal cual.

Estilo encadenado si mandas varias cosas al mismo chat:

```php
Sendify::to('5215551234567')->text('Hola');
Sendify::to('5215551234567')->voiceNote(storage_path('app/audios/nota.ogg'));
```

### Medios

Cualquier método de medios acepta una URL pública, una ruta local (se lee y se manda en base64 con
su mimetype), un `data:` URI o base64 crudo (aquí sí hay que pasar el `mimetype`).

```php
Sendify::VideoMessageTo('5215551234567', public_path('videos/demo.mp4'), 'Demo');
Sendify::AudioMessageTo('5215551234567', 'https://cdn.miempresa.mx/nota.ogg', ptt: true);
Sendify::StickerMessageTo('5215551234567', 'https://cdn.miempresa.mx/sticker.webp');
```

### Respuesta

Todos los envíos devuelven un `Response` que se puede leer como array o con atajos:

```php
$response = Sendify::TextMessageTo('5215551234567', 'Hola');

$response->messageId();      // 'BAE5...'
$response->successful();     // true
$response->data();           // nodo data del JSON
$response['data']['status']; // acceso tipo array
$response->json('data.messageId');
```

### Envío masivo

```php
$batch = Sendify::BulkMessages([
    '5215551234567' => 'Hola Ana',
    '5215559876543' => 'Hola Luis',
]);

Sendify::batch($batch->json('id'));   // avance
Sendify::cancelBatch($batch->json('id'));
```

### Acciones sobre mensajes

```php
Sendify::replyTo('5215551234567', $messageId, 'Claro que sí');
Sendify::forwardTo('5215559876543', $messageId);
Sendify::react($messageId, '👍');
Sendify::editMessage($messageId, 'Texto corregido');
Sendify::deleteMessage($messageId);
Sendify::pinMessage($messageId, 86400);
Sendify::starMessage($messageId);

Sendify::messages(['chatId' => '5215551234567', 'limit' => 50]);
Sendify::messageMedia($messageId)->body(); // binario del adjunto
```

### Estado de la instancia

`Sendify::Status()` es el diagnóstico completo y **nunca lanza excepciones**: si el servidor está
caído, la instancia no existe o la cuenta está suspendida, eso mismo es el estado.

```php
$estado = Sendify::Status();

$estado->state;            // EverestHome\Sendify\Enums\InstanceState::Suspended
$estado->value();          // 'suspended'
$estado->message;          // 'Cuenta suspendida: instancia desactivada o API key revocada'
$estado->canSend();        // false
$estado->accountProblem(); // true
$estado->httpStatus;       // 401
```

| `state`               | Qué pasó                                                              | `canSend()` |
| --------------------- | --------------------------------------------------------------------- | ----------- |
| `connected`           | Conectada a WhatsApp                                                  | sí          |
| `connecting`          | Levantando el socket                                                  | no          |
| `qr_ready`            | Hay un QR esperando a que lo escaneen                                 | no          |
| `hibernated`          | Dormida para no gastar RAM; el envío la despierta sola                | sí          |
| `disconnected`        | Vinculada pero sin conexión                                           | no          |
| `unlinked`            | Nunca se vinculó el teléfono o se cerró la sesión: hay que escanear   | no          |
| `instance_not_found`  | La instancia no existe o la API key no pertenece a ella (403/404)     | no          |
| `suspended`           | Instancia desactivada o API key revocada (401)                        | no          |
| `key_expired`         | API key expirada: renovación o cobro pendiente (401)                  | no          |
| `missing_credentials` | No se mandó API key (401)                                             | no          |
| `ip_not_allowed`      | La IP de este servidor no está en la lista blanca de la key (403)     | no          |
| `insufficient_role`   | La API key existe pero su rol no alcanza (403)                        | no          |
| `rate_limited`        | Se topó el límite de peticiones por minuto (429)                      | no          |
| `unreachable`         | El servidor Sendify no contestó: caído, DNS, TLS o timeout            | no          |
| `server_error`        | El servidor Sendify respondió 5xx                                     | no          |

```php
use EverestHome\Sendify\Enums\InstanceState;

$estado = Sendify::Status();

if ($estado->canSend()) {
    Sendify::TextMessageTo($telefono, $texto);
} elseif ($estado->accountProblem()) {
    // suspendida, key expirada, IP bloqueada o instancia inexistente
    Notification::route('mail', 'admin@miempresa.mx')->notify(new SendifyCaido($estado->message));
} elseif ($estado->is(InstanceState::QrReady, InstanceState::Unlinked)) {
    // alguien tiene que escanear el QR: Sendify::qr()
}
```

Otros atajos: `->connected()`, `->hibernated()`, `->suspended()`, `->needsAttention()`,
`->needsStart()`, `->hasCredentials()`, `->hibernationReason()`, `->instanceName()`,
`->lastConnectionAt()`, `->toArray()`. También se serializa a JSON y se lee como array
(`$estado['state']`).

Para distinguir "servidor caído" de "problema de esta cuenta" está `Sendify::serverReachable()`
(pega a `/health`, sin API key de por medio). Y si prefieres el JSON crudo con excepciones,
`Sendify::statusResponse()`.

### Instancia

```php
Sendify::connected();   // bool
Sendify::qr();          // código QR vigente
Sendify::start();
Sendify::stop();
Sendify::hibernate();
Sendify::wake();
Sendify::pairingCode('5215551234567');
Sendify::config();
Sendify::updateConfig(['idleTimeoutMs' => 900000, 'wakeTimeoutMs' => 8000]);
Sendify::stats();
```

Las rutas de ciclo de vida piden una API key con rol `admin`; los envíos, una de rol `operator`.

### Webhooks, plantillas, automatizaciones y perfil

```php
$webhook = Sendify::webhooks()->create('CRM', 'https://crm.miempresa.mx/sendify', [
    'message.received', 'message.status', 'connection.updated',
]);

$secret = $webhook->json('secret'); // se muestra una sola vez

Sendify::templates()->create('bienvenida', 'Hola {{nombre}}, gracias por escribir.');

Sendify::automations()->create(
    name: 'Horario',
    triggerType: 'message.received',
    conditions: ['contains' => 'horario'],
    actionType: 'send_text',
    actionPayload: ['text' => 'Atendemos de 9:00 a 18:00.'],
);

Sendify::profile()->name('Soporte Everest Home');
Sendify::statuses()->text('Estamos en línea', backgroundColor: '#25D366');
```

Para validar la firma de una entrega en tu controlador de Laravel:

```php
use EverestHome\Sendify\Resources\Webhooks;

if (! Webhooks::verifySignature($request->getContent(), $request->header('X-Sendify-Signature', ''), config('services.sendify.secret'))) {
    abort(401);
}
```

## Varias instancias o varios servidores

Cada empresa puede tener su propio servidor Sendify. Agrega conexiones en `config/sendify.php`:

```php
'connections' => [
    'default' => [
        'url' => env('SENDIFY_URL'),
        'client' => env('SENDIFY_CLIENT'),
        'instance' => env('SENDIFY_INSTANCE'),
    ],
    'cobranza' => [
        'url' => env('SENDIFY_COBRANZA_URL'),
        'client' => env('SENDIFY_COBRANZA_CLIENT'),
        'instance' => env('SENDIFY_COBRANZA_INSTANCE'),
    ],
],
```

```php
Sendify::connection('cobranza')->TextMessageTo('5215551234567', 'Recordatorio de pago');
```

Misma API key, otra instancia:

```php
Sendify::instance('soporte')->TextMessageTo('5215551234567', 'Hola');
```

Credenciales que viven en la base de datos (multi-tenant):

```php
use EverestHome\Sendify\SendifyManager;

$sendify = app(SendifyManager::class)->build([
    'url' => $tenant->sendify_url,
    'client' => $tenant->sendify_key,
    'instance' => $tenant->sendify_instance,
]);

$sendify->TextMessageTo($cliente->telefono, 'Hola');
```

## Errores

Toda respuesta fuera del rango 2xx lanza una excepción que hereda de `SendifyException`:

| Excepción                       | Cuándo                                                             |
| ------------------------------- | ------------------------------------------------------------------ |
| `AuthenticationException`       | 401/403: API key inválida, expirada, IP no permitida               |
| `ValidationException`           | 400/422: faltan campos o el número no es válido                    |
| `NotFoundException`             | 404: plantilla, lote o mensaje inexistente                         |
| `InstanceNotConnectedException` | 409: la instancia no está conectada a WhatsApp                     |
| `RateLimitException`            | 429: se topó el límite de peticiones de la key                     |
| `InstanceAsleepException`       | 503: hibernando, no despertó a tiempo (`retryAfter()`)             |
| `ConnectionException`           | No hubo respuesta: DNS, TLS, timeout                               |

Las respuestas 503 reintentables y las 429 se reintentan solas según `SENDIFY_RETRIES`, esperando lo
que indique el header `Retry-After`.

```php
use EverestHome\Sendify\Exceptions\InstanceAsleepException;
use EverestHome\Sendify\Exceptions\SendifyException;

try {
    Sendify::TextMessageTo($telefono, $texto);
} catch (InstanceAsleepException $e) {
    SendWhatsApp::dispatch($telefono, $texto)->delay(now()->addSeconds($e->retryAfter()));
} catch (SendifyException $e) {
    report($e);
}
```

Como todo lanza excepciones, en una cola de Laravel el reintento del job sale gratis.

## Sin Laravel

```php
use EverestHome\Sendify\Sendify;

$sendify = Sendify::make('https://sendify.miempresa.mx', 'snd_live_xxx', 'ventas');

$sendify->textMessageTo('5215551234567', 'Hola desde PHP puro');
```

Para usar otro cliente HTTP (Guzzle, el de Laravel, etc.) implementa
`EverestHome\Sendify\Http\ClientInterface` y pásalo con `$sendify->withHttpClient($cliente)` o enlázalo
en el contenedor de Laravel.

## Pruebas

```bash
composer install
composer test
```

Las pruebas usan un cliente HTTP falso; no tocan la red ni requieren un Sendify corriendo.

## Licencia

MIT. Ver [LICENSE.md](LICENSE.md).
