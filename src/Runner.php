<?php

namespace OpenAI\LaravelAgents;

class Runner
{
    protected Agent $agent;
    protected int $maxTurns;
    protected array $tools = [];
    protected array $functionTools = [];
    protected array $inputGuards = [];
    protected array $outputGuards = [];
    protected $outputType = null;
    protected $tracer;

    public function __construct(Agent $agent, int $maxTurns = 5, ?callable $tracer = null, $outputType = null)
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

    public function addInputGuard(callable $guard): void
    {
        $this->inputGuards[] = $guard;
    }

    public function addOutputGuard(callable $guard): void
    {
        $this->outputGuards[] = $guard;
    }

    public function run(string $message)
    {
        $turn = 0;
        $input = $message;
        $responseContent = '';
        while ($turn < $this->maxTurns) {
            foreach ($this->inputGuards as $guard) {
                $input = $guard($input);
            }

            $toolDefs = array_map(fn($t) => $t['schema'], $this->functionTools);
            $response = $this->agent->chat($input, $toolDefs);
            $responseContent = $response['content'] ?? '';

            foreach ($this->outputGuards as $guard) {
                $responseContent = $guard($responseContent);
            }

            if ($this->tracer) {
                ($this->tracer)([
                    'turn' => $turn + 1,
                    'input' => $input,
                    'output' => $responseContent,
                ]);
            }

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

        return $responseContent;
    }
}
