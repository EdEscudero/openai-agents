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
    protected array $functionTools = [];
    protected array $inputGuardrails = [];
    protected array $outputGuardrails = [];
    protected ?Tracing $tracer = null;
    protected $outputType = null;

    public function __construct(Agent $agent, int $maxTurns = 5, ?Tracing $tracer = null, $outputType = null)
    {
        $this->agent = $agent;
        $this->maxTurns = $maxTurns;
        $this->tracer = $tracer;
        $this->outputType = $outputType ?? $agent->getOutputType();
    }

    public function registerTool(string $name, callable $callback): void
    {
        $this->tools[$name] = $callback;
    }

    public function registerFunctionTool(string $name, callable $fn, array $schema): void
    {
        $this->functionTools[$name] = [
            'fn' => $fn,
            'schema' => [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'parameters' => $schema,
                ],
            ],
        ];
    }

    public function addInputGuardrail(InputGuardrail $guard): void
    {
        $this->inputGuardrails[] = $guard;
    }

    public function addOutputGuardrail(OutputGuardrail $guard): void
    {
        $this->outputGuardrails[] = $guard;
    }

    public function run(string $message)
    {
        $spanId = $this->tracer?->startSpan('runner', ['max_turns' => $this->maxTurns]);
        $turn = 0;
        $input = $message;
        $responseContent = '';
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

            $toolDefs = array_map(fn($t) => $t['schema'], $this->functionTools);
            $response = $this->agent->chat($input, $toolDefs);
            $responseContent = $response['content'] ?? '';

            foreach ($this->outputGuardrails as $guard) {
                try {
                    $responseContent = $guard->validate($responseContent);
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
                'output' => $responseContent,
            ]);

            if (isset($response['tool_calls'])) {
                $toolCall = $response['tool_calls'][0];
                $name = $toolCall['function']['name'];
                $args = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                if (isset($this->functionTools[$name])) {
                    $input = ($this->functionTools[$name]['fn'])($args);
                    $turn++;
                    continue;
                }
            }

            if (preg_match('/\[\[tool:(\w+)(?:\s+([^\]]+))?\]\]/', $responseContent, $m)) {
                $name = $m[1];
                $arg = $m[2] ?? '';
                if (isset($this->tools[$name])) {
                    $input = ($this->tools[$name])($arg);
                    $turn++;
                    continue;
                }
            }

            if (preg_match('/\[\[handoff:(.+)\]\]/', $responseContent, $m)) {
                $prompt = $m[1];
                $this->agent = new Agent($this->agent->getClient(), [], $prompt, $this->outputType);
                $input = '';
                $turn++;
                continue;
            }

            if ($this->outputType !== null) {
                $decoded = json_decode($responseContent, true);
                if (is_array($decoded)) {
                    break;
                }
            } else {
                break;
            }

            $turn++;
        }

        $this->tracer?->endSpan($spanId);
        return $responseContent;
    }
}
