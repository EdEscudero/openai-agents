<?php

namespace OpenAI\LaravelAgents;

use OpenAI\Contracts\ClientContract;

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
     * Optional expected output type schema or class name.
     */
    protected $outputType = null;

    public function __construct(ClientContract $client, array $options = [], ?string $systemPrompt = null, $outputType = null)
    {
        $this->client = $client;
        $this->options = $options;
        if ($systemPrompt !== null) {
            $this->messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $this->outputType = $outputType;
    }

    /**
     * Get the underlying OpenAI client instance.
     */
    public function getClient(): ClientContract
    {
        return $this->client;
    }

    public function getOutputType()
    {
        return $this->outputType;
    }

    /**
     * Send a message to the agent and get a response.
     */
    public function chat(string $message, array $tools = []): array
    {
        $this->messages[] = ['role' => 'user', 'content' => $message];

        $params = [
            'model' => $this->options['model'] ?? 'gpt-4o',
            'messages' => $this->messages,
            'temperature' => $this->options['temperature'] ?? null,
            'top_p' => $this->options['top_p'] ?? null,
        ];

        if ($tools !== []) {
            $params['tools'] = $tools;
            $params['tool_choice'] = 'auto';
        }

        $response = $this->client->chat()->create($params);

        $messageData = $response['choices'][0]['message'];

        if (isset($messageData['content']) && $messageData['content'] !== '') {
            $this->messages[] = ['role' => 'assistant', 'content' => $messageData['content']];
        } elseif (isset($messageData['tool_calls'])) {
            $this->messages[] = ['role' => 'assistant', 'tool_calls' => $messageData['tool_calls']];
        }

        return $messageData;
    }
}
