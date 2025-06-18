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

    public function __construct(ClientContract $client, array $options = [], ?string $systemPrompt = null)
    {
        $this->client = $client;
        $this->options = $options;
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
     * Send a message to the agent and get a response.
     */
    public function chat(string $message): string
    {
        $this->messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->client->chat()->create([
            'model' => $this->options['model'] ?? 'gpt-4o',
            'messages' => $this->messages,
            'temperature' => $this->options['temperature'] ?? null,
            'top_p' => $this->options['top_p'] ?? null,
        ]);

        $reply = $response['choices'][0]['message']['content'] ?? '';

        if ($reply !== '') {
            $this->messages[] = ['role' => 'assistant', 'content' => $reply];
        }

        return $reply;
    }
}
