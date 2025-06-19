<?php
declare(strict_types=1);

namespace Aerobit\OpenaiAgents\Guardrails;

abstract class OutputGuardrail
{
    /**
     * Validate the provided content.
     *
     * @throws OutputGuardrailException
     */
    abstract public function validate(string $content): string;
}
