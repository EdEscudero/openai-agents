<?php

namespace OpenAI\LaravelAgents;

class Runner
{
    protected Agent $agent;
    protected int $maxTurns;
    protected array $tools = [];
    protected array $inputGuards = [];
    protected array $outputGuards = [];
    protected $tracer;

    public function __construct(Agent $agent, int $maxTurns = 5, ?callable $tracer = null)
    {
        $this->agent = $agent;
        $this->maxTurns = $maxTurns;
        $this->tracer = $tracer;
    }

    public function registerTool(string $name, callable $callback): void
    {
        $this->tools[$name] = $callback;
    }

    public function addInputGuard(callable $guard): void
    {
        $this->inputGuards[] = $guard;
    }

    public function addOutputGuard(callable $guard): void
    {
        $this->outputGuards[] = $guard;
    }

    public function run(string $message): string
    {
        $turn = 0;
        $input = $message;
        $response = '';
        while ($turn < $this->maxTurns) {
            foreach ($this->inputGuards as $guard) {
                $input = $guard($input);
            }

            $response = $this->agent->chat($input);

            foreach ($this->outputGuards as $guard) {
                $response = $guard($response);
            }

            if ($this->tracer) {
                ($this->tracer)([
                    'turn' => $turn + 1,
                    'input' => $input,
                    'output' => $response,
                ]);
            }

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

        return $response;
    }
}
