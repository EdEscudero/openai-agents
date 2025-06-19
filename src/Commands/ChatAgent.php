<?php
declare(strict_types=1);

namespace Aerobit\OpenaiAgents\Commands;

use Illuminate\Console\Command;
use Aerobit\OpenaiAgents\AgentManager;

class ChatAgent extends Command
{
    protected $signature = 'agent:chat {message}'
        . ' {--system=}'
        . ' {--max-turns=5}'
        . ' {--trace}';

    protected $description = 'Send a prompt to the OpenAI agent and output the response.';

    public function handle(AgentManager $manager): int
    {
        $message = $this->argument('message');
        $system = $this->option('system');
        $max = (int) $this->option('max-turns');
        $trace = $this->option('trace');

        $agent = $manager->agent([], $system);

        $tracer = null;
        if ($trace) {
            $tracer = function (array $data) {
                $this->info('Turn ' . $data['turn']);
                $this->line('> ' . $data['input']);
                $this->line('< ' . $data['output']);
            };
        }

        $runner = new \Aerobit\OpenaiAgents\Runner($agent, $max, $tracer);

        $response = $runner->run($message);

        $this->line($response);

        return self::SUCCESS;
    }
}
