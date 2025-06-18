<?php

namespace OpenAI\LaravelAgents;

use OpenAI\LaravelAgents\Guardrails\GuardrailException;
use OpenAI\LaravelAgents\Guardrails\InputGuardrail;
use OpenAI\LaravelAgents\Guardrails\OutputGuardrail;
use OpenAI\LaravelAgents\Tracing\Tracing;

class Runner
{
    protected Agent $agent;
    protected int $maxTurns;
    protected array $tools = [];
    protected array $inputGuardrails = [];
    protected array $outputGuardrails = [];
    protected ?Tracing $tracer = null;

    public function __construct(Agent $agent, int $maxTurns = 5, ?Tracing $tracer = null)
    {
        $this->agent = $agent;
        $this->maxTurns = $maxTurns;
        $this->tracer = $tracer;
    }

    public function registerTool(string $name, callable $callback): void
    {
        $this->tools[$name] = $callback;
    }

    public function addInputGuardrail(InputGuardrail $guard): void
    {
        $this->inputGuardrails[] = $guard;
    }

    public function addOutputGuardrail(OutputGuardrail $guard): void
    {
        $this->outputGuardrails[] = $guard;
    }

    public function run(string $message): string
    {
        $spanId = $this->tracer?->startSpan('runner', ['max_turns' => $this->maxTurns]);
        $turn = 0;
        $input = $message;
        $response = '';
        while ($turn < $this->maxTurns) {
            foreach ($this->inputGuardrails as $guard) {
                try {
                    $input = $guard->validate($input);
                } catch (GuardrailException $e) {
                    $this->tracer?->recordEvent($spanId, [
                        'turn' => $turn + 1,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $response = $this->agent->chat($input);

            foreach ($this->outputGuardrails as $guard) {
                try {
                    $response = $guard->validate($response);
                } catch (GuardrailException $e) {
                    $this->tracer?->recordEvent($spanId, [
                        'turn' => $turn + 1,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            $this->tracer?->recordEvent($spanId, [
                'turn' => $turn + 1,
                'input' => $input,
                'output' => $response,
            ]);

            if (preg_match('/\[\[tool:(\w+)(?:\s+([^\]]+))?\]\]/', $response, $m)) {
                $name = $m[1];
                $arg = $m[2] ?? '';
                if (isset($this->tools[$name])) {
                    $input = ($this->tools[$name])($arg);
                    $turn++;
                    continue;
                }
            }

            if (preg_match('/\[\[handoff:(.+)\]\]/', $response, $m)) {
                $prompt = $m[1];
                $this->agent = new Agent($this->agent->getClient(), [], $prompt);
                $input = '';
                $turn++;
                continue;
            }

            break;
        }

        $this->tracer?->endSpan($spanId);
        return $response;
    }
}
