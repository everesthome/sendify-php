<?php

declare(strict_types=1);

namespace EverestHome\Sendify\Resources;

use EverestHome\Sendify\Http\Response;
use EverestHome\Sendify\Sendify;

/** Reglas automáticas de la instancia. Requiere API key admin. */
final class Automations
{
    public function __construct(private readonly Sendify $sendify)
    {
    }

    public function all(): Response
    {
        return $this->sendify->get('/automations');
    }

    /**
     * @param string $triggerType message.received, message.status o connection.updated
     * @param array<string, mixed> $conditions por ejemplo ['contains' => 'horario']
     * @param string $actionType send_text o webhook
     * @param array<string, mixed> $actionPayload por ejemplo ['text' => 'Hola {{senderId}}']
     */
    public function create(
        string $name,
        string $triggerType,
        array $conditions,
        string $actionType,
        array $actionPayload,
        bool $active = true,
    ): Response {
        return $this->sendify->post('/automations', [
            'name' => $name,
            'triggerType' => $triggerType,
            'conditions' => $conditions,
            'actionType' => $actionType,
            'actionPayload' => $actionPayload,
            'active' => $active,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(int|string $ruleId, array $attributes): Response
    {
        return $this->sendify->put('/automations/'.rawurlencode((string) $ruleId), $attributes);
    }

    public function delete(int|string $ruleId): Response
    {
        return $this->sendify->delete('/automations/'.rawurlencode((string) $ruleId));
    }
}
