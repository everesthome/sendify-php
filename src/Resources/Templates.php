<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Resources;

use EverestHome\Sendify\Http\Response;
use EverestHome\Sendify\Sendify;

/** Plantillas de mensaje del negocio; se envían con templateMessageTo(). */
final class Templates
{
    public function __construct(private readonly Sendify $sendify)
    {
    }

    public function all(): Response
    {
        return $this->sendify->get('/templates');
    }

    /** $content admite marcadores tipo {{nombre}}. */
    public function create(string $name, string $content, bool $active = true): Response
    {
        return $this->sendify->post('/templates', [
            'name' => $name,
            'content' => $content,
            'active' => $active,
        ]);
    }

    /** @param array{name?: string, content?: string, active?: bool} $attributes */
    public function update(int|string $templateId, array $attributes): Response
    {
        return $this->sendify->put('/templates/'.rawurlencode((string) $templateId), $attributes);
    }

    public function delete(int|string $templateId): Response
    {
        return $this->sendify->delete('/templates/'.rawurlencode((string) $templateId));
    }
}
