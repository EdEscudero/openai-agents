<?php
declare(strict_types=1);

namespace Aerobit\OpenaiAgents;

use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\AudioContract;

class Agent
{
    /**
     * The OpenAI client instance.
     */
    protected ClientContract $client;

    /**
     * Agent configuration options.
     */
    protected array $options = [];

    /**
     * Stored conversation messages.
     */
    protected array $messages = [];

    /**
     * Optional output type/schema to request from the model.
     */
    protected $outputType = null;

    /**
     * Optional user provided context for dependency injection.
     */
    protected array $context = [];

    protected ?string $systemPrompt = null;
    public function __construct(ClientContract $client, array $options = [], ?string $systemPrompt = null, $outputType = null, array $context = [])
    {
        $this->client = $client;
        $this->options = $options;
        $this->outputType = $outputType;
        $this->systemPrompt = $systemPrompt;
        $this->context = $context;
        if ($systemPrompt !== null) {
            $this->messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
    }

    /**
     * Get the underlying OpenAI client instance.
     */
    public function getClient(): ClientContract
    {
        return $this->client;
    }

    /**
     * Get the context array.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Clone the current agent optionally overriding options or system prompt.
     */
    public function clone(array $options = [], ?string $systemPrompt = null, array $context = null): self
    {
        $options = array_replace_recursive($this->options, $options);
        $systemPrompt = $systemPrompt ?? $this->systemPrompt;
        $context = $context ?? $this->context;

        return new self($this->client, $options, $systemPrompt, $this->outputType, $context);
    }

    /**
     * Send a message to the agent and get a response.
     */
    public function chat(string $message, array $toolDefinitions = [], $outputType = null): string
    {
        $this->messages[] = ['role' => 'user', 'content' => $message];

        $params = [
            'model' => $this->options['model'] ?? 'gpt-4o',
            'messages' => $this->messages,
            'temperature' => $this->options['temperature'] ?? null,
            'top_p' => $this->options['top_p'] ?? null,
        ];

        if (!empty($toolDefinitions)) {
            $params['tools'] = array_map(function (array $tool) {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'parameters' => $tool['schema'],
                    ],
                ];
            }, $toolDefinitions);
            $params['tool_choice'] = 'auto';
        }

        $outputType = $outputType ?? $this->outputType;
        if ($outputType !== null) {
            $params['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->client->chat()->create($params);

        $reply = $response['choices'][0]['message']['content'] ?? '';

        if ($reply !== '') {
            $this->messages[] = ['role' => 'assistant', 'content' => $reply];
        }

        return $reply;
    }

    /**
     * Send a message and return a streaming iterator of chunks.
     *
     * @return iterable<int, string>
     */
    public function chatStreamed(string $message, array $toolDefinitions = [], $outputType = null): iterable
    {
        $this->messages[] = ['role' => 'user', 'content' => $message];

        $params = [
            'model' => $this->options['model'] ?? 'gpt-4o',
            'messages' => $this->messages,
            'temperature' => $this->options['temperature'] ?? null,
            'top_p' => $this->options['top_p'] ?? null,
            'stream' => true,
        ];

        if (!empty($toolDefinitions)) {
            $params['tools'] = array_map(function (array $tool) {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'parameters' => $tool['schema'],
                    ],
                ];
            }, $toolDefinitions);
            $params['tool_choice'] = 'auto';
        }

        $outputType = $outputType ?? $this->outputType;
        if ($outputType !== null) {
            $params['response_format'] = ['type' => 'json_object'];
        }

        $stream = $this->client->chat()->createStreamed($params);

        foreach ($stream as $response) {
            $delta = $response['choices'][0]['delta']['content'] ?? '';
            if ($delta !== '') {
                yield $delta;
            }
        }
    }

    /**
     * Convert the provided text to speech using OpenAI's API.
     */
    public function speak(string $text, array $options = []): string
    {
        $params = [
            'model' => $options['model'] ?? 'tts-1',
            'voice' => $options['voice'] ?? 'alloy',
            'input' => $text,
            'response_format' => $options['response_format'] ?? 'mp3',
        ];

        $response = $this->client->audio()->speech($params);

        return $response;
    }
}
