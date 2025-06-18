<?php
declare(strict_types=1);

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
    protected array $namedAgents = [];
    protected $outputType = null;
    protected array $inputGuardrails = [];
    protected array $outputGuardrails = [];
    protected ?Tracing $tracer = null;

    public function __construct(Agent $agent, int $maxTurns = 5, ?Tracing $tracer = null, $outputType = null)
    {
        $this->agent = $agent;
        $this->maxTurns = $maxTurns;
        $this->tracer = $tracer;
        $this->outputType = $outputType;
    }

    public function registerTool(string $name, callable $callback): void
    {
        $this->registerFunctionTool($name, $callback, []);
    }

    public function registerFunctionTool(string $name, callable $fn, array $schema): void
    {
        $this->tools[$name] = [
            'callback' => $fn,
            'schema' => $schema,
            'name' => $name,
        ];
    }

    public function registerAutoFunctionTool(string $name, callable $fn): void
    {
        $ref = new \ReflectionFunction($fn);
        $schema = ['type' => 'object', 'properties' => [], 'required' => []];
        foreach ($ref->getParameters() as $param) {
            $type = 'string';
            if ($param->hasType()) {
                $map = ['int' => 'integer', 'float' => 'number', 'bool' => 'boolean'];
                $t = $param->getType()->getName();
                $type = $map[$t] ?? 'string';
            }
            $schema['properties'][$param->getName()] = ['type' => $type];
            if (!$param->isOptional()) {
                $schema['required'][] = $param->getName();
            }
        }
        if (empty($schema['required'])) {
            unset($schema['required']);
        }
        $this->registerFunctionTool($name, $fn, $schema);
    }

    public function registerAgent(string $name, Agent $agent): void
    {
        $this->namedAgents[$name] = $agent;
    }

    public function addInputGuardrail(InputGuardrail $guard): void
    {
        $this->inputGuardrails[] = $guard;
    }

    public function addOutputGuardrail(OutputGuardrail $guard): void
    {
        $this->outputGuardrails[] = $guard;
    }

    /** @return string|array */
    public function run(string $message): string|array
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

            $toolDefs = array_values(array_filter($this->tools, fn($t) => !empty($t['schema'])));
            $response = $this->agent->chat($input, $toolDefs, $this->outputType);

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
                    $tool = $this->tools[$name];
                    $args = $arg;
                    if (!empty($tool['schema'])) {
                        $decoded = json_decode($arg, true);
                        $args = $decoded ?? $arg;
                    }
                    $input = ($tool['callback'])($args);
                    $turn++;
                    continue;
                }
            }

            if (preg_match('/\[\[handoff:(.+)\]\]/', $response, $m)) {
                $target = trim($m[1]);
                if (isset($this->namedAgents[$target])) {
                    $this->agent = $this->namedAgents[$target];
                } else {
                    $this->agent = new Agent($this->agent->getClient(), [], $target);
                }
                $input = '';
                $turn++;
                continue;
            }

            if ($this->outputType !== null && !$this->outputMatches($response)) {
                $input = '';
                $turn++;
                continue;
            }

            break;
        }

        $this->tracer?->endSpan($spanId);
        if ($this->outputType !== null && $this->outputMatches($response)) {
            return json_decode($response, true);
        }
        return $response;
    }

    public function runAsync(string $message): \Fiber
    {
        return new \Fiber(function () use ($message) {
            return $this->run($message);
        });
    }

    /**
     * Run the agent and yield streamed output chunks.
     *
     * @return iterable<int, string>
     */
    public function runStreamed(string $message): iterable
    {
        $toolDefs = array_values(array_filter($this->tools, fn($t) => !empty($t['schema'])));
        yield from $this->agent->chatStreamed($message, $toolDefs, $this->outputType);
    }

    protected function outputMatches(string $content): bool
    {
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        if (is_array($this->outputType) && isset($this->outputType['required'])) {
            foreach ($this->outputType['required'] as $key) {
                if (!array_key_exists($key, $data)) {
                    return false;
                }
            }
        }
        return true;
    }
}
