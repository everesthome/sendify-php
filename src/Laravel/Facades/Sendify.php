<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Laravel\Facades;

use EverestHome\Sendify\SendifyManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \EverestHome\Sendify\Sendify connection(?string $name = null)
 * @method static \EverestHome\Sendify\Sendify build(array $config)
 * @method static \EverestHome\Sendify\Sendify instance(string $instance)
 * @method static \EverestHome\Sendify\PendingMessage to(string $to)
 * @method static \EverestHome\Sendify\Http\Response textMessageTo(string $to, string $text)
 * @method static \EverestHome\Sendify\Http\Response imageMessageTo(string $to, string $source, ?string $caption = null, ?string $mimetype = null)
 * @method static \EverestHome\Sendify\Http\Response videoMessageTo(string $to, string $source, ?string $caption = null, ?string $mimetype = null)
 * @method static \EverestHome\Sendify\Http\Response audioMessageTo(string $to, string $source, bool $ptt = false, ?string $mimetype = null)
 * @method static \EverestHome\Sendify\Http\Response documentMessageTo(string $to, string $source, ?string $filename = null, ?string $caption = null, ?string $mimetype = null)
 * @method static \EverestHome\Sendify\Http\Response stickerMessageTo(string $to, string $source, ?string $mimetype = null)
 * @method static \EverestHome\Sendify\Http\Response locationMessageTo(string $to, float $latitude, float $longitude, ?string $description = null, ?string $address = null)
 * @method static \EverestHome\Sendify\Http\Response contactMessageTo(string $to, string $contactName, string $contactNumber)
 * @method static \EverestHome\Sendify\Http\Response pollMessageTo(string $to, string $name, array $options, ?int $selectableCount = null)
 * @method static \EverestHome\Sendify\Http\Response templateMessageTo(string $to, string $template, array $variables = [])
 * @method static \EverestHome\Sendify\Http\Response bulkMessages(array $items)
 * @method static \EverestHome\Sendify\Http\Response batch(string $batchId)
 * @method static \EverestHome\Sendify\Http\Response cancelBatch(string $batchId)
 * @method static \EverestHome\Sendify\Http\Response replyTo(string $to, string $messageId, string $text)
 * @method static \EverestHome\Sendify\Http\Response forwardTo(string $to, string $messageId)
 * @method static \EverestHome\Sendify\Http\Response react(string $messageId, string $emoji)
 * @method static \EverestHome\Sendify\Http\Response editMessage(string $messageId, string $text)
 * @method static \EverestHome\Sendify\Http\Response deleteMessage(string $messageId)
 * @method static \EverestHome\Sendify\Http\Response pinMessage(string $messageId, ?int $durationSeconds = null)
 * @method static \EverestHome\Sendify\Http\Response unpinMessage(string $messageId)
 * @method static \EverestHome\Sendify\Http\Response starMessage(string $messageId, bool $star = true)
 * @method static \EverestHome\Sendify\Http\Response messages(array $filters = [])
 * @method static \EverestHome\Sendify\Http\Response messageMedia(string $messageId)
 * @method static \EverestHome\Sendify\Http\Response messageReactions(string $messageId)
 * @method static \EverestHome\Sendify\Status status()
 * @method static \EverestHome\Sendify\Http\Response statusResponse()
 * @method static \EverestHome\Sendify\Http\Response health()
 * @method static \EverestHome\Sendify\Http\Response healthLive()
 * @method static bool serverReachable()
 * @method static bool connected()
 * @method static \EverestHome\Sendify\Http\Response start()
 * @method static \EverestHome\Sendify\Http\Response stop()
 * @method static \EverestHome\Sendify\Http\Response hibernate()
 * @method static \EverestHome\Sendify\Http\Response wake()
 * @method static \EverestHome\Sendify\Http\Response logout()
 * @method static \EverestHome\Sendify\Http\Response forceKill()
 * @method static \EverestHome\Sendify\Http\Response qr()
 * @method static \EverestHome\Sendify\Http\Response pairingCode(string $phoneNumber, ?string $customCode = null)
 * @method static \EverestHome\Sendify\Http\Response config()
 * @method static \EverestHome\Sendify\Http\Response updateConfig(array $config)
 * @method static \EverestHome\Sendify\Http\Response stats()
 * @method static \EverestHome\Sendify\Resources\Webhooks webhooks()
 * @method static \EverestHome\Sendify\Resources\Templates templates()
 * @method static \EverestHome\Sendify\Resources\Automations automations()
 * @method static \EverestHome\Sendify\Resources\Profile profile()
 * @method static \EverestHome\Sendify\Resources\Statuses statuses()
 * @method static \EverestHome\Sendify\Http\Response get(string $path, array $query = [])
 * @method static \EverestHome\Sendify\Http\Response post(string $path, ?array $body = null)
 * @method static \EverestHome\Sendify\Http\Response put(string $path, ?array $body = null)
 * @method static \EverestHome\Sendify\Http\Response delete(string $path)
 * @method static \EverestHome\Sendify\Http\Response request(string $method, string $path, ?array $body = null, array $query = [])
 *
 * @see \EverestHome\Sendify\SendifyManager
 * @see \EverestHome\Sendify\Sendify
 */
class Sendify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SendifyManager::class;
    }
}
