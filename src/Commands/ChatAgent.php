<?php

namespace OpenAI\LaravelAgents\Commands;

use Illuminate\Console\Command;
use OpenAI\LaravelAgents\AgentManager;

class ChatAgent extends Command
{
    protected $signature = 'agent:chat {message}';

    protected $description = 'Send a prompt to the OpenAI agent and output the response.';

    public function handle(AgentManager $manager): int
    {
        $message = $this->argument('message');
        $agent = $manager->agent();

        $response = $agent->chat($message);

        $this->line($response);

        return self::SUCCESS;
    }
}
