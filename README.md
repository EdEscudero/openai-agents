# OpenAI Agents for Laravel

This package provides a lightweight integration of the [OpenAI PHP client](https://github.com/openai-php/client) with Laravel 12, inspired by the [OpenAI Agents Python SDK](https://github.com/openai/openai-agents-python).


## Installation

```bash
composer require aerobit/laravel-openai-agents
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=config --provider="OpenAI\\LaravelAgents\\AgentServiceProvider"
```

Set your `OPENAI_API_KEY` in the environment file or edit `config/agents.php`.

## Usage

Send a message to the default agent:

```bash
php artisan agent:chat "Hello"
```

You can control the number of turns or provide a system prompt using options:

```bash
php artisan agent:chat "Hello" --system="You are helpful" --max-turns=3
```

You can also resolve the `AgentManager` service from the container to create agents programmatically.

```php
use OpenAI\LaravelAgents\AgentManager;

$manager = app(AgentManager::class);
$response = $manager->agent()->chat('Hello world');
```

The returned `Agent` instance retains the conversation history, allowing for multi-turn chats:

```php
$agent = $manager->agent();

// First user message
$agent->chat('Hello');

// Follow-up message uses previous context
$reply = $agent->chat('What did I just say?');
```

The agent can also convert text responses to speech:

```php
$audio = $agent->speak('Hello world');
file_put_contents('output.mp3', $audio);
```

You can optionally provide a system prompt when constructing the agent:

```php
use OpenAI\LaravelAgents\Agent;
use OpenAI\Client as OpenAIClient;

$client = OpenAIClient::factory()->withApiKey(env('OPENAI_API_KEY'))->make();
$agent = new Agent($client, [], 'You are a helpful assistant.');
```

For more advanced scenarios you can use the `Runner` class which loops until the
agent returns a final response or a turn limit is reached. Tools and basic handoffs
can be registered on the runner:

```php
use OpenAI\LaravelAgents\Runner;

$runner = new Runner($agent, maxTurns: 3);
$runner->registerTool('echo', fn($text) => $text);
$helper = new Agent($client, [], 'Espanol agente');
$runner->registerAgent('spanish', $helper);
$reply = $runner->run('Start');
// inside the conversation use [[handoff:spanish]] to switch
```

The runner can request structured JSON output by providing an output schema:

```php
$schema = ['required' => ['done']];
$runner = new Runner($agent, maxTurns: 3, tracer: null, outputType: $schema);
$result = $runner->run('Start'); // returns an associative array
```

Tools may also be defined with JSON schemas for OpenAI function calling:

```php
$runner->registerFunctionTool('echo', fn(array $args) => $args['text'], [
    'type' => 'object',
    'properties' => ['text' => ['type' => 'string']],
    'required' => ['text'],
]);

// Or let the runner derive the schema automatically
$runner->registerAutoFunctionTool('echo_auto', function (string $text) {
    return $text;
});
```

If you need non-blocking execution you can run the runner inside a PHP `Fiber`:

```php
$fiber = $runner->runAsync('Hello');
$fiber->start();
$result = $fiber->getReturn();
```

You can also stream results as they're generated:

```php
foreach ($runner->runStreamed('Hello') as $chunk) {
    echo $chunk;
}
```

### Voice pipeline

Use the `VoicePipeline` class to handle audio transcription and text-to-speech in one step:

```php
$pipeline = new VoicePipeline($client, $agent);
$audio = $pipeline->run('input.wav');
file_put_contents('reply.mp3', $audio);
```

### Tracing

The package includes a simple tracing system that lets you observe each turn
of a `Runner` execution. Enable tracing in `config/agents.php` and register one
or more processors to handle trace records:

```php
return [
    // ...
    'tracing' => [
        'enabled' => true,
        'processors' => [
            fn(array $record) => logger()->info('agent trace', $record),
            new \OpenAI\LaravelAgents\Tracing\HttpProcessor('https://example.com/trace'),
        ],
    ],
];
```

When enabled, each call to `Runner::run()` will emit start and end span events
as well as per-turn events containing the input and output.

### Guardrails

Guardrails let you validate input and output during a run. They can transform
the content or throw an exception to stop execution.

```php
use OpenAI\LaravelAgents\Guardrails\InputGuardrail;
use OpenAI\LaravelAgents\Guardrails\OutputGuardrail;
use OpenAI\LaravelAgents\Guardrails\OutputGuardrailException;

$runner->addInputGuardrail(new class extends InputGuardrail {
    public function validate(string $content): string
    {
        return strtoupper($content);
    }
});

$runner->addOutputGuardrail(new class extends OutputGuardrail {
    public function validate(string $content): string
    {
        if (str_contains($content, 'bad')) {
            throw new OutputGuardrailException('Bad content');
        }
        return $content;
    }
});
```

## Configuration

The `config/agents.php` file allows you to customize the default model and parameters used when interacting with OpenAI. It also contains options to enable tracing and provide custom processors for handling trace data.

## License

Released under the MIT license.
