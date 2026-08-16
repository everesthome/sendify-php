<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Resources;

use EverestHome\Sendify\Http\Response;
use EverestHome\Sendify\Sendify;
use EverestHome\Sendify\Support\Media;
use EverestHome\Sendify\Support\Recipient;

/** Estados de WhatsApp (las "historias" de 24 horas). */
final class Statuses
{
    public function __construct(private readonly Sendify $sendify)
    {
    }

    public function all(): Response
    {
        return $this->sendify->get('/status');
    }

    /**
     * @param array<int, string> $recipients JIDs o números que verán el estado
     * @param string|null $backgroundColor formato #RRGGBB
     * @param int|null $font 0 a 5
     */
    public function text(
        string $text,
        array $recipients = [],
        ?string $backgroundColor = null,
        ?int $font = null,
    ): Response {
        return $this->sendify->post('/status', array_filter([
            'type' => 'text',
            'text' => $text,
            'backgroundColor' => $backgroundColor,
            'font' => $font,
            'statusJidList' => $this->normalizeRecipients($recipients),
        ], static fn ($value) => $value !== null));
    }

    /**
     * @param string $type image, video o audio
     * @param array<int, string> $recipients
     */
    public function media(
        string $type,
        string $source,
        ?string $caption = null,
        ?string $mimetype = null,
        array $recipients = [],
    ): Response {
        $payload = ['type' => $type] + Media::payload($source, $mimetype);

        return $this->sendify->post('/status', array_filter($payload + [
            'caption' => $caption,
            'statusJidList' => $this->normalizeRecipients($recipients),
        ], static fn ($value) => $value !== null));
    }

    public function delete(string $messageId): Response
    {
        return $this->sendify->delete('/status/'.rawurlencode($messageId));
    }

    /**
     * @param array<int, string> $recipients
     * @return array<int, string>|null
     */
    private function normalizeRecipients(array $recipients): ?array
    {
        if ($recipients === []) {
            return null;
        }

        return array_map(static fn (string $to) => Recipient::normalize($to), array_values($recipients));
    }
}
