# OpenAI Agents for Laravel

This package provides a lightweight integration of the [OpenAI PHP client](https://github.com/openai-php/client) with Laravel 12, inspired by the [OpenAI Agents Python SDK](https://github.com/openai/openai-agents-python).

> **Note**: This is a minimal starting point and does not yet implement all features of the official Python SDK.

## Installation

```bash
composer require openai/laravel-agents
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
$reply = $runner->run('Start');
```

## Configuration

The `config/agents.php` file allows you to customize the default model and parameters used when interacting with OpenAI.

## License

Released under the MIT license.
