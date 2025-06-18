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

    public function test_runner_waits_for_structured_output()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                ['choices' => [['message' => ['content' => 'not json']]]],
                ['choices' => [['message' => ['content' => '{"done":true}']]]]
            );

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $schema = ['required' => ['done']];
        $agent = new Agent($client, [], null, $schema);
        $runner = new Runner($agent, 3, null, $schema);

        $result = $runner->run('start');

        $this->assertSame(['done' => true], $result);
    }

    public function test_runner_calls_function_tool()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->exactly(2))
            ->method('create')
            ->withConsecutive(
                [$this->callback(fn($p) => isset($p['tools']) && $p['tools'][0]['function']['name'] === 'echo')],
                [$this->anything()]
            )
            ->willReturnOnConsecutiveCalls(
                ['choices' => [['message' => ['content' => '[[tool:echo "hi"]]']]]],
                ['choices' => [['message' => ['content' => 'Done']]]]
            );

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client);
        $runner = new Runner($agent, 3);
        $runner->registerFunctionTool('echo', fn($arg) => $arg, ['type' => 'object']);

        $result = $runner->run('start');

        $this->assertSame('Done', $result);
    }

    public function test_runner_handles_named_handoff()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                ['choices' => [['message' => ['content' => '[[handoff:helper]]']]]],
                ['choices' => [['message' => ['content' => 'final']]]]
            );

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client);
        $helper = new Agent($client);
        $runner = new Runner($agent, 3);
        $runner->registerAgent('helper', $helper);

        $result = $runner->run('start');

        $this->assertSame('final', $result);
    }

    public function test_runner_run_async()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->once())
            ->method('create')
            ->willReturn(['choices' => [['message' => ['content' => 'ok']]]]);

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client);
        $runner = new Runner($agent, 3);

        $fiber = $runner->runAsync('start');
        $fiber->start();
        $result = $fiber->getReturn();

        $this->assertSame('ok', $result);
    }
}
