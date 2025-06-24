<?php
declare(strict_types=1);

namespace Aerobit\OpenaiAgents;

use OpenAI\OpenAI;

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
    public function agent(array $options = [], ?string $systemPrompt = null): Agent
    {
        $options = array_replace_recursive($this->config['default'] ?? [], $options);

        $client = OpenAI::factory()->withApiKey($this->config['api_key'])->make();

        return new Agent($client, $options, $systemPrompt);
    }
}
