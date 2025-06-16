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

    public function __construct(ClientContract $client, array $options = [])
    {
        $this->client = $client;
        $this->options = $options;
    }

    /**
     * Send a message to the agent and get a response.
     */
    public function chat(string $message): string
    {
        $response = $this->client->chat()->create([
            'model' => $this->options['model'] ?? 'gpt-4o',
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => $this->options['temperature'] ?? null,
            'top_p' => $this->options['top_p'] ?? null,
        ]);

        return $response['choices'][0]['message']['content'] ?? '';
    }
}
