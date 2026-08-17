# Sendify for PHP and Laravel

[![Packagist](https://img.shields.io/packagist/v/everesthome/sendify.svg)](https://packagist.org/packages/everesthome/sendify)
[![Tests](https://github.com/everesthome/sendify-php/actions/workflows/run-tests.yml/badge.svg)](https://github.com/everesthome/sendify-php/actions/workflows/run-tests.yml)
[![Downloads](https://img.shields.io/packagist/dt/everesthome/sendify.svg)](https://packagist.org/packages/everesthome/sendify)
[![License](https://img.shields.io/packagist/l/everesthome/sendify.svg)](LICENSE.md)

Client for the Sendify service: send WhatsApp messages from any PHP application. On Laravel you
install it, add three keys to your `.env`, and you are done:

```php
Sendify::TextMessageTo('5215551234567', 'Hello from Laravel');
```

The core is framework-agnostic — it only needs cURL and JSON.

**Package:** https://packagist.org/packages/everesthome/sendify

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Sending messages](#sending-messages)
  - [Media](#media)
  - [Responses](#responses)
  - [Bulk sending](#bulk-sending)
  - [Message actions](#message-actions)
- [Instance status](#instance-status)
- [Instance lifecycle](#instance-lifecycle)
- [Webhooks, templates, automations, profile and statuses](#webhooks-templates-automations-profile-and-statuses)
- [Multiple instances or servers](#multiple-instances-or-servers)
- [Error handling](#error-handling)
- [Using it without Laravel](#using-it-without-laravel)
- [Testing](#testing)
- [License](#license)

## Requirements

| Requirement | Version                                     |
| ----------- | ------------------------------------------- |
| PHP         | 8.2, 8.3 or 8.4                             |
| Extensions  | `ext-curl`, `ext-json`                      |
| Laravel     | 10, 11, 12 or 13 (optional — only for the facade and the service provider) |

## Installation

```bash
composer require everesthome/sendify
```

LLM Prompt:

```
# Task: Integrate Sendify WhatsApp notifications (Laravel)

Add WhatsApp notifications via https://github.com/everesthome/sendify-php when
tasks are created, reassigned, and completed.

## Install
1. `composer require everesthome/sendify`
2. Add EXACTLY these three vars to `.env` and `.env.example` — nothing else:
   SENDIFY_URL, SENDIFY_CLIENT, SENDIFY_INSTANCE
3. Do NOT run `vendor:publish`. The package ships its own config and reads the
   env directly. Do NOT create config files or config toggles (enabled flags,
   per-event switches, queue names, country-code settings). Hardcode constants
   in the service class instead.

## Architecture — 2 files, plus 2 lines in the model
Create ONLY:
- `app/Services/TaskWhatsAppNotifier.php` — builds the text and sends it
- `app/Observers/TaskObserver.php` — decides when to fire

Register with `#[ObservedBy(TaskObserver::class)]` on the model. Use a model
observer, NOT edits to controllers: entities are usually created from several
places (multiple panels/roles, series controllers, scheduled commands) and all
of them must notify identically. Verify this by grepping for every `Model::create`
call site before you start.

## Send immediately — no queue
Call `Sendify::textMessageTo()` directly inside the request. Do NOT create a Job,
do NOT use `dispatch()`, do NOT touch queue config. A queued job silently does
nothing until a worker runs, which reads as "the API is broken".

Wrap the call in `try { } catch (SendifyException $e) { Log::warning(...); }` so a
WhatsApp failure never breaks the save. Note the tradeoff in
save now waits on the HTTP call (package default timeout 30s).

## Observer rules
- `created`: notify the assignee.
- `updated`: use `wasChanged('assigned_to')` and `wasChanged('status')`. Only fire
  on reassignment and on the transition INTO the completed s
  timers/counters get written constantly — never notify on every update.
- Actor is `auth()->user()`; it is NULL in console/seeder co
  `?->` and fall back to a "System" label.

## Notifier rules
- Skip when actor === recipient. Nobody needs a WhatsApp for
- Phone normalization: strip non-digits, drop a leading `00`, prepend the country
  code when exactly 10 digits remain, reject anything under
  phones are ACTUALLY stored first (`select phone from users limit 10`) — they are
  usually free-text: "444 547 3439", "+1 (443) 665-4263".
- No usable phone → `Log::info` and return. Never throw.
- Missing API key → `Log::warning` and return. Never fail si
  env looks identical to a broken integration and wastes hours in production.
- Deep links must point at the recipient's own panel (admin
  they land on a 403.
- Message: bold title, then only the fields that are actuall

## Hard constraints
- NEVER modify migrations. If a migration blocks you, report it and stop.
- NEVER run `migrate:reset`, `migrate:fresh`, `migrate:refre
  destroy data, and seeders with fake phone numbers will send real WhatsApps to
  strangers.
- Do NOT create tests, artisan commands, or helper scripts unless asked.
- Do NOT fix unrelated bugs you find. Report them and move o
- Do NOT reformat files you touch; keep the diff to the lines you actually changed
  (watch out for the linter reformatting a whole file).

## Verify
Create a real record via tinker with two users that have phones, then confirm
delivery against the API itself, not just absence of errors:
`Sendify::messages(['limit' => 10, 'direction' => 'outgoing'])`
Timestamps must match record creation to the second (proves
`Sendify::Status()` never throws — use it to check the instance is `connected`;
`unlinked` means nobody has scanned the QR yet and nothing w

## Deployment note for the summary
`.env` is gitignored, so production needs the three vars added manually, then
`php artisan config:clear && php artisan config:cache`, then
Missing any of the three looks exactly like "the integration doesn't work".
```

On Laravel the service provider and the `Sendify` facade are auto-discovered. Publishing the
configuration file is optional:

```bash
php artisan vendor:publish --tag=sendify-config
```

## Configuration

Add this to your application's `.env`:

```dotenv
SENDIFY_URL="https://sendify.mycompany.com"
SENDIFY_CLIENT="snd_live_xxxxxxxxxxxxxxxx"
SENDIFY_INSTANCE="sales"
```

| Variable           | What it is                                                                     |
| ------------------ | ------------------------------------------------------------------------------ |
| `SENDIFY_URL`      | Base URL of that company's Sendify server (each company may run its own)        |
| `SENDIFY_CLIENT`   | API key of the instance; sent in the `X-API-Key` header                         |
| `SENDIFY_INSTANCE` | Numeric ID or name of the WhatsApp instance                                     |

The underscore-less variants are also accepted: `SENDIFYURL`, `SENDIFYCLIENT`, `SENDIFYINSTANCE`.

Optional settings:

| Variable                  | Default     | What it does                                                          |
| ------------------------- | ----------- | --------------------------------------------------------------------- |
| `SENDIFY_TIMEOUT`         | `30`        | Request timeout, in seconds                                            |
| `SENDIFY_CONNECT_TIMEOUT` | `10`        | Connection timeout, in seconds                                         |
| `SENDIFY_RETRIES`         | `1`         | Extra attempts for retryable `503` responses and for `429`             |
| `SENDIFY_VERIFY_SSL`      | `true`      | TLS certificate verification                                           |
| `SENDIFY_CONNECTION`      | `default`   | Connection the facade uses when none is given                          |

Besides the facade, the container also resolves the client by type hint:

```php
use EverestHome\Sendify\Sendify;

public function __construct(private readonly Sendify $sendify)
{
}
```

## Sending messages

PHP method names are case-insensitive, so `Sendify::TextMessageTo()` and
`Sendify::textMessageTo()` are the exact same call. Pick whichever style you prefer.

```php
use EverestHome\Sendify\Laravel\Facades\Sendify;

Sendify::TextMessageTo('+52 55 1234 5678', 'Your order is on its way');
Sendify::ImageMessageTo('5215551234567', 'https://cdn.mycompany.com/promo.jpg', 'Promo of the month');
Sendify::DocumentMessageTo('5215551234567', storage_path('app/invoices/F-1023.pdf'), 'F-1023.pdf', 'Your invoice');
Sendify::LocationMessageTo('5215551234567', 19.4326, -99.1332, 'Downtown branch');
Sendify::ContactMessageTo('5215551234567', 'Support', '5215557654321');
Sendify::PollMessageTo('5215551234567', 'Which time works for you?', ['Morning', 'Afternoon']);
Sendify::TemplateMessageTo('5215551234567', 'welcome', ['name' => 'Jovan']);
```

| Method                                                                  | Notes                                                        |
| ----------------------------------------------------------------------- | ------------------------------------------------------------ |
| `textMessageTo(string $to, string $text)`                                | Plain text                                                    |
| `imageMessageTo(string $to, string $source, ?string $caption, ?string $mimetype)` | See [Media](#media)                                  |
| `videoMessageTo(string $to, string $source, ?string $caption, ?string $mimetype)` | See [Media](#media)                                  |
| `audioMessageTo(string $to, string $source, bool $ptt = false, ?string $mimetype)` | `$ptt = true` sends it as a voice note              |
| `documentMessageTo(string $to, string $source, ?string $filename, ?string $caption, ?string $mimetype)` | Filename defaults to the local file's basename |
| `stickerMessageTo(string $to, string $source, ?string $mimetype)`        | WebP stickers                                                 |
| `locationMessageTo(string $to, float $lat, float $lng, ?string $description, ?string $address)` | —                                     |
| `contactMessageTo(string $to, string $contactName, string $contactNumber)` | Shares a vCard                                             |
| `pollMessageTo(string $to, string $name, array $options, ?int $selectableCount)` | Between 2 and 12 options                             |
| `templateMessageTo(string $to, string $template, array $variables = [])` | Replaces the `{{placeholders}}` of a saved template           |

Phone numbers are normalized for you: `+52 55 1234 5678`, `5215551234567` and
`5215551234567@c.us` all arrive the same. Group JIDs (`...@g.us`) and `@s.whatsapp.net` JIDs are
passed through untouched.

Chained style when you send several things to the same chat:

```php
Sendify::to('5215551234567')->text('Hi');
Sendify::to('5215551234567')->voiceNote(storage_path('app/audio/note.ogg'));
Sendify::to('5215551234567')->document(storage_path('app/invoices/F-1023.pdf'));
```

`to()` returns a `PendingMessage` exposing `text()`, `image()`, `video()`, `audio()`,
`voiceNote()`, `document()`, `sticker()`, `location()`, `contact()`, `poll()`, `template()`,
`reply()`, `forward()` and `messages()`.

### Media

Every media method accepts a public URL, a local path (it is read and sent as base64 with its
mimetype), a `data:` URI, or raw base64 — with raw base64 you must pass the `mimetype` yourself.

Two service rules:

- **25 MB** maximum per base64 file (`Media::MAX_BYTES`). The client checks the size before
  uploading anything and throws `ValidationException` so you do not waste the round trip.
- **URLs are downloaded by the server**, so they must resolve to a public address: `localhost`,
  private LAN, link-local (including `169.254.169.254`) and CGNAT ranges are rejected with `400`.
  If your media server lives on the same private network as Sendify, the service has to run with
  `ALLOW_PRIVATE_MEDIA_URLS=true`.

```php
Sendify::VideoMessageTo('5215551234567', public_path('videos/demo.mp4'), 'Demo');
Sendify::AudioMessageTo('5215551234567', 'https://cdn.mycompany.com/note.ogg', ptt: true);
Sendify::StickerMessageTo('5215551234567', 'https://cdn.mycompany.com/sticker.webp');
```

### Responses

Every send returns a `Response` you can read as an array or through shortcuts:

```php
$response = Sendify::TextMessageTo('5215551234567', 'Hi');

$response->messageId();      // 'BAE5...'
$response->successful();     // true
$response->status();         // 200
$response->data();           // the JSON `data` node
$response['data']['status']; // array-style access
$response->json('data.messageId');
$response->body();           // raw body — binary for messageMedia()
$response->header('retry-after');
```

### Bulk sending

```php
$batch = Sendify::BulkMessages([
    '5215551234567' => 'Hi Ana',
    '5215559876543' => 'Hi Luis',
]);

Sendify::batch($batch->json('id'));         // progress
Sendify::cancelBatch($batch->json('id'));
```

The long form is accepted too, in case two recipients share a number:

```php
Sendify::BulkMessages([
    ['chatId' => '5215551234567', 'text' => 'Hi Ana'],
    ['chatId' => '5215559876543', 'text' => 'Hi Luis'],
]);
```

### Message actions

```php
Sendify::replyTo('5215551234567', $messageId, 'Of course');
Sendify::forwardTo('5215559876543', $messageId);
Sendify::react($messageId, '👍');
Sendify::editMessage($messageId, 'Fixed text');
Sendify::deleteMessage($messageId);
Sendify::pinMessage($messageId, 86400);   // 86400, 604800 or 2592000 seconds
Sendify::unpinMessage($messageId);
Sendify::starMessage($messageId);
Sendify::starMessage($messageId, false);  // unstar

Sendify::messages(['chatId' => '5215551234567', 'limit' => 50]);
Sendify::messageReactions($messageId);
Sendify::messageMedia($messageId)->body(); // raw attachment bytes
```

`messages()` accepts `chatId`, `direction`, `type`, `status`, `search`, `page` and `limit`.

## Instance status

`Sendify::Status()` is the full diagnosis and **never throws**: if the server is down, the instance
does not exist, or the account is suspended, that is the status itself.

```php
$status = Sendify::Status();

$status->state;            // EverestHome\Sendify\Enums\InstanceState::Suspended
$status->value();          // 'suspended'
$status->message;          // human-readable message (see note below)
$status->canSend();        // false
$status->accountProblem(); // true
$status->httpStatus;       // 401
```

| `state`               | What happened                                                       | `canSend()` |
| --------------------- | ------------------------------------------------------------------- | ----------- |
| `connected`           | Connected to WhatsApp                                               | yes         |
| `connecting`          | Bringing the socket up                                              | no          |
| `qr_ready`            | A QR code is waiting to be scanned                                  | no          |
| `hibernated`          | Asleep to save RAM; sending wakes it up automatically               | yes         |
| `disconnected`        | Linked but not connected                                            | no          |
| `unlinked`            | The phone was never linked or the session was closed: scan a QR     | no          |
| `instance_not_found`  | The instance does not exist or the API key does not own it (403/404)| no          |
| `suspended`           | Instance deactivated or API key revoked (401)                       | no          |
| `key_expired`         | API key expired: renewal or payment pending (401)                   | no          |
| `missing_credentials` | No API key was sent (401)                                           | no          |
| `ip_not_allowed`      | This server's IP is not in the key's allowlist (403)                | no          |
| `insufficient_role`   | The API key exists but its role is not enough (403)                 | no          |
| `rate_limited`        | The per-minute request limit was hit (429)                          | no          |
| `unreachable`         | The Sendify server did not answer: down, DNS, TLS or timeout        | no          |
| `server_error`        | The Sendify server answered 5xx                                     | no          |

```php
use EverestHome\Sendify\Enums\InstanceState;

$status = Sendify::Status();

if ($status->canSend()) {
    Sendify::TextMessageTo($phone, $text);
} elseif ($status->accountProblem()) {
    // suspended, expired key, blocked IP or missing instance
    Notification::route('mail', 'admin@mycompany.com')->notify(new SendifyDown($status->message));
} elseif ($status->is(InstanceState::QrReady, InstanceState::Unlinked)) {
    // someone has to scan the QR: Sendify::qr()
}
```

Other shortcuts: `->connected()`, `->hibernated()`, `->suspended()`, `->needsAttention()`,
`->needsStart()`, `->hasCredentials()`, `->hibernationReason()`, `->hibernatedAt()`,
`->instanceId()`, `->instanceName()`, `->business()`, `->lastConnectionAt()`, `->lastActiveAt()`,
`->toArray()`. `Status` is also JSON-serializable, readable as an array (`$status['state']`) and
castable to string (`"suspended: ..."`).

To follow the progress of a linking attempt: `->connecting()`, `->reconnecting()` (a retry is
already scheduled — it is not idle), `->reconnectAttempts()`, `->qrAttempt()`, `->maxQrCycles()`
and `->qrExpiresAt()`.

`hibernatedAt` and `hibernationReason` are only present while the instance is genuinely asleep. An
instance that was never linked, or whose `logout()` wiped the session, reports `disconnected`
without credentials, and the client translates that into `unlinked`.

To tell "server down" apart from "problem with this account" there is `Sendify::serverReachable()`
(hits `/health`, no API key involved). `Sendify::healthLive()` is the liveness probe: it answers
200 as long as the process is alive even if the database is down — `health()` does check the
database and returns 503 when it cannot reach it. And if you prefer the raw JSON with exceptions,
use `Sendify::statusResponse()`.

> **Note:** `$status->message` and `InstanceState::label()` currently ship in Spanish, since they
> mirror the messages returned by the service. Use `$status->value()` or the `InstanceState` enum
> if you need a stable, language-independent value.

## Instance lifecycle

```php
Sendify::connected();   // bool
Sendify::qr();          // { qr, qrExpiresAt, qrAttempt } — each QR lives 60 s
Sendify::start();       // opens the socket or emits a fresh QR
Sendify::stop();        // hibernates, keeping the session
Sendify::hibernate();
Sendify::wake();
Sendify::logout();      // unlinks the phone and DELETES the session
Sendify::forceKill();
Sendify::pairingCode('5215551234567');
Sendify::config();
Sendify::updateConfig(['idleTimeoutMs' => 900000, 'wakeTimeoutMs' => 8000]);
Sendify::stats();
```

`updateConfig()` accepts `hibernationEnabled`, `idleTimeoutMs`, `wakeTimeoutMs`, `maxQrCycles`,
`syncFullHistory`, `active`, `autoReconnect`, `maxReconnectAttempts` and `reconnectDelayMs`.

API key roles: `read-only` reads status, history and stats; `operator` also sends, acts on messages
and can call `wake()`; `admin` also manages the lifecycle, config, webhooks, templates, automations
and profile. Reading the QR does not start anything: if the instance is asleep, `qr()` answers
`qr: null` and `needsStart: true` until you call `start()`.

Group IDs (`...@g.us`) come from `GET /api/management/instances/:id/groups`, which uses a panel
session instead of an API key, so that endpoint is not part of this client.

## Webhooks, templates, automations, profile and statuses

```php
$webhook = Sendify::webhooks()->create('CRM', 'https://crm.mycompany.com/sendify', [
    'message.received', 'message.sent', 'message.status', 'connection.updated',
]);

$secret = $webhook->json('secret'); // shown only once

Sendify::webhooks()->all();
Sendify::webhooks()->test($webhookId);
Sendify::webhooks()->update($webhookId, ['active' => false]);
Sendify::webhooks()->delete($webhookId);
Sendify::webhooks()->deliveries(['status' => 'failed']);

Sendify::templates()->create('welcome', 'Hi {{name}}, thanks for writing.');
Sendify::templates()->all();
Sendify::templates()->update($templateId, ['active' => false]);
Sendify::templates()->delete($templateId);

Sendify::automations()->create(
    name: 'Business hours',
    triggerType: 'message.received',
    conditions: ['contains' => 'hours'],
    actionType: 'send_text',
    actionPayload: ['text' => 'We are open from 9:00 to 18:00.'],
);

Sendify::profile()->name('Everest Home Support');
Sendify::profile()->status('Always online');       // the profile "about" text
Sendify::profile()->picture(public_path('logo.png'));
Sendify::profile()->removePicture();

Sendify::statuses()->text('We are online', backgroundColor: '#25D366');
Sendify::statuses()->media('image', public_path('promo.jpg'), 'This week only');
Sendify::statuses()->all();
Sendify::statuses()->delete($messageId);
```

Available events (`EverestHome\Sendify\Resources\Webhooks::EVENTS`): `message.received`,
`message.sent`, `message.status`, `connection.updated`, `call.received`. Use `['*']` to receive
everything. Failed deliveries are retried 5 times with exponential backoff (`2^attempts × 15 s`)
and end up in `Sendify::webhooks()->deliveries()`.

To validate a delivery's signature in your Laravel controller:

```php
use EverestHome\Sendify\Resources\Webhooks;

if (! Webhooks::verifySignature($request->getContent(), $request->header('X-Sendify-Signature', ''), config('services.sendify.secret'))) {
    abort(401);
}
```

Webhooks, automations and profile calls require an `admin` API key.

## Multiple instances or servers

Each company can run its own Sendify server. Add connections in `config/sendify.php`:

```php
'connections' => [
    'default' => [
        'url' => env('SENDIFY_URL'),
        'client' => env('SENDIFY_CLIENT'),
        'instance' => env('SENDIFY_INSTANCE'),
    ],
    'billing' => [
        'url' => env('SENDIFY_BILLING_URL'),
        'client' => env('SENDIFY_BILLING_CLIENT'),
        'instance' => env('SENDIFY_BILLING_INSTANCE'),
    ],
],
```

```php
Sendify::connection('billing')->TextMessageTo('5215551234567', 'Payment reminder');
```

Same API key, different instance:

```php
Sendify::instance('support')->TextMessageTo('5215551234567', 'Hi');
```

Credentials that live in the database (multi-tenant):

```php
use EverestHome\Sendify\SendifyManager;

$sendify = app(SendifyManager::class)->build([
    'url' => $tenant->sendify_url,
    'client' => $tenant->sendify_key,
    'instance' => $tenant->sendify_instance,
]);

$sendify->TextMessageTo($customer->phone, 'Hi');
```

`build()` inherits `timeout`, `connect_timeout`, `retries` and `verify_ssl` from
`config/sendify.php` unless you override them in the array.

## Error handling

Any response outside the 2xx range throws an exception extending `SendifyException`:

| Exception                       | When                                                                |
| ------------------------------- | ------------------------------------------------------------------- |
| `AuthenticationException`       | 401/403: invalid or expired API key, IP not allowed                 |
| `ValidationException`           | 400/422: missing fields, invalid number, or media over 25 MB        |
| `NotFoundException`             | 404: template, batch or message does not exist                      |
| `InstanceNotConnectedException` | 409: the instance is not connected to WhatsApp                      |
| `RateLimitException`            | 429: the key's request limit was hit                                |
| `InstanceAsleepException`       | 503: hibernating, did not wake up in time (`retryAfter()`)          |
| `ConnectionException`           | No response at all: DNS, TLS, timeout                               |
| `ConfigurationException`        | Missing credentials or an unknown connection name                   |

Retryable 503 responses and 429s are retried automatically according to `SENDIFY_RETRIES`, waiting
as long as the `Retry-After` header asks for (capped between 1 and 30 seconds).

On a 422 the service returns per-field errors; `$e->errors()` hands them back as-is:

```php
[['field' => 'chatId', 'rule' => 'required', 'message' => 'chatId is required']]
```

```php
use EverestHome\Sendify\Exceptions\InstanceAsleepException;
use EverestHome\Sendify\Exceptions\SendifyException;

try {
    Sendify::TextMessageTo($phone, $text);
} catch (InstanceAsleepException $e) {
    SendWhatsApp::dispatch($phone, $text)->delay(now()->addSeconds($e->retryAfter()));
} catch (SendifyException $e) {
    report($e);
}
```

Since everything throws, job retries on a Laravel queue come for free.

## Using it without Laravel

```php
use EverestHome\Sendify\Sendify;

$sendify = Sendify::make('https://sendify.mycompany.com', 'snd_live_xxx', 'sales');

$sendify->textMessageTo('5215551234567', 'Hello from plain PHP');
```

`make()` takes an optional fourth argument with the same HTTP options as the config file:

```php
$sendify = Sendify::make($url, $key, $instance, [
    'timeout' => 15,
    'retries' => 2,
    'verify_ssl' => false,
]);
```

To use another HTTP client (Guzzle, Laravel's, etc.) implement
`EverestHome\Sendify\Http\ClientInterface` and pass it with `$sendify->withHttpClient($client)`, or
bind it in the Laravel container.

## Testing

```bash
composer install
composer test        # Pest
composer analyse     # PHPStan
composer format      # PHP-CS-Fixer
```

The test suite uses a fake HTTP client, so it never touches the network and does not need a running
Sendify server. In your own tests, do the same: implement `ClientInterface` with canned responses
and bind it in the container — the service provider resolves the HTTP client through that
interface.

## License

MIT. See [LICENSE.md](LICENSE.md).
