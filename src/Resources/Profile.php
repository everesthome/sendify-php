<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Resources;

use EverestHome\Sendify\Http\Response;
use EverestHome\Sendify\Sendify;
use EverestHome\Sendify\Support\Media;

/** Perfil de WhatsApp de la instancia. Requiere API key admin. */
final class Profile
{
    public function __construct(private readonly Sendify $sendify)
    {
    }

    public function name(string $name): Response
    {
        return $this->sendify->put('/profile/name', ['value' => $name]);
    }

    /** El "recado" del perfil. */
    public function status(string $status): Response
    {
        return $this->sendify->put('/profile/status', ['value' => $status]);
    }

    /** @param string $source URL, ruta local, data URI o base64 */
    public function picture(string $source, ?string $mimetype = null): Response
    {
        return $this->sendify->put('/profile/picture', Media::payload($source, $mimetype));
    }

    public function removePicture(): Response
    {
        return $this->sendify->delete('/profile/picture');
    }
}
