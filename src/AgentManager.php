<?php

namespace OpenAI\LaravelAgents;

use OpenAI\Client as OpenAIClient;

class AgentManager
{
    /**
     * The configuration array.
     */
    protected array $config;

    /**
     * Create a new manager instance.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Create a new agent instance.
     */
    public function agent(array $options = []): Agent
    {
        $options = array_replace_recursive($this->config['default'] ?? [], $options);

        $client = OpenAIClient::factory()->withApiKey(env('OPENAI_API_KEY'))->make();

        return new Agent($client, $options);
    }
}
