<?php

namespace OpenAI\LaravelAgents\Guardrails;

abstract class OutputGuardrail
{
    /**
     * Validate the provided content.
     *
     * @throws OutputGuardrailException
     */
    abstract public function validate(string $content): string;
}
