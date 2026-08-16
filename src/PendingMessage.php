<?php

declare(strict_types=1);

namespace EverestHome\Sendify;

use EverestHome\Sendify\Http\Response;

/**
 * Azúcar sintáctico para fijar el destinatario una vez:
 *
 *   Sendify::to('5215551234567')->text('Hola');
 *   Sendify::to('5215551234567')->document(storage_path('factura.pdf'));
 */
final class PendingMessage
{
    public function __construct(
        private readonly Sendify $sendify,
        private readonly string $to,
    ) {
    }

    public function text(string $text): Response
    {
        return $this->sendify->textMessageTo($this->to, $text);
    }

    public function image(string $source, ?string $caption = null, ?string $mimetype = null): Response
    {
        return $this->sendify->imageMessageTo($this->to, $source, $caption, $mimetype);
    }

    public function video(string $source, ?string $caption = null, ?string $mimetype = null): Response
    {
        return $this->sendify->videoMessageTo($this->to, $source, $caption, $mimetype);
    }

    public function audio(string $source, bool $ptt = false, ?string $mimetype = null): Response
    {
        return $this->sendify->audioMessageTo($this->to, $source, $ptt, $mimetype);
    }

    public function voiceNote(string $source, ?string $mimetype = null): Response
    {
        return $this->sendify->audioMessageTo($this->to, $source, true, $mimetype);
    }

    public function document(string $source, ?string $filename = null, ?string $caption = null, ?string $mimetype = null): Response
    {
        return $this->sendify->documentMessageTo($this->to, $source, $filename, $caption, $mimetype);
    }

    public function sticker(string $source, ?string $mimetype = null): Response
    {
        return $this->sendify->stickerMessageTo($this->to, $source, $mimetype);
    }

    public function location(float $latitude, float $longitude, ?string $description = null, ?string $address = null): Response
    {
        return $this->sendify->locationMessageTo($this->to, $latitude, $longitude, $description, $address);
    }

    public function contact(string $contactName, string $contactNumber): Response
    {
        return $this->sendify->contactMessageTo($this->to, $contactName, $contactNumber);
    }

    /** @param array<int, string> $options */
    public function poll(string $name, array $options, ?int $selectableCount = null): Response
    {
        return $this->sendify->pollMessageTo($this->to, $name, $options, $selectableCount);
    }

    /** @param array<string, scalar> $variables */
    public function template(string $template, array $variables = []): Response
    {
        return $this->sendify->templateMessageTo($this->to, $template, $variables);
    }

    public function reply(string $messageId, string $text): Response
    {
        return $this->sendify->replyTo($this->to, $messageId, $text);
    }

    public function forward(string $messageId): Response
    {
        return $this->sendify->forwardTo($this->to, $messageId);
    }

    /** Historial de este chat. */
    public function messages(array $filters = []): Response
    {
        return $this->sendify->messages($filters + ['chatId' => $this->to]);
    }
}
