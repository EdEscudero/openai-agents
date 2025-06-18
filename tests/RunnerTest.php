<?php

use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\ChatContract;
use OpenAI\LaravelAgents\Agent;
use OpenAI\LaravelAgents\Runner;
use PHPUnit\Framework\TestCase;

class RunnerTest extends TestCase
{
    public function test_runner_calls_registered_tool()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                ['choices' => [['message' => ['content' => '[[tool:echo hi]]']]]],
                ['choices' => [['message' => ['content' => 'Done']]]]
            );

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client);
        $runner = new Runner($agent, 3);
        $runner->registerTool('echo', fn($arg) => $arg);

        $result = $runner->run('start');

        $this->assertSame('Done', $result);
    }

    public function test_runner_respects_max_turns()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->once())
            ->method('create')
            ->willReturn(['choices' => [['message' => ['content' => '[[tool:unknown]]']]]]);

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client);
        $runner = new Runner($agent, 1);

        $result = $runner->run('start');

        $this->assertSame('[[tool:unknown]]', $result);
    }
}
