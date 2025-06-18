<?php

namespace OpenAI\LaravelAgents\Guardrails;

abstract class InputGuardrail
{
    /**
     * Validate the provided content.
     *
     * @throws InputGuardrailException
     */
    abstract public function validate(string $content): string;
}
