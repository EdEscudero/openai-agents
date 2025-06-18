<?php

use OpenAI\Contracts\ChatContract;
use OpenAI\Contracts\ClientContract;
use OpenAI\LaravelAgents\Agent;
use OpenAI\LaravelAgents\Runner;
use OpenAI\LaravelAgents\Tracing\Tracing;
use PHPUnit\Framework\TestCase;

class TracingTest extends TestCase
{
    public function test_runner_emits_tracing_events()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->once())
            ->method('create')
            ->willReturn([
                'choices' => [
                    ['message' => ['content' => 'Done']]
                ]
            ]);

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $records = [];
        $tracing = new Tracing([
            function (array $record) use (&$records) { $records[] = $record; }
        ]);

        $agent = new Agent($client);
        $runner = new Runner($agent, 3, $tracing);

        $result = $runner->run('start');

        $this->assertSame('Done', $result);
        $this->assertSame('start_span', $records[0]['type']);
        $this->assertSame('event', $records[1]['type']);
        $this->assertSame('end_span', $records[count($records)-1]['type']);
    }
}
