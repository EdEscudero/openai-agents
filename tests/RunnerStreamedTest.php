<?php

use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\ChatContract;
use Aerobit\OpenaiAgents\Agent;
use Aerobit\OpenaiAgents\Runner;
use PHPUnit\Framework\TestCase;

class RunnerStreamedTest extends TestCase
{
    public function test_run_streamed_yields_chunks()
    {
        $stream = new ArrayIterator([
            ['choices' => [['delta' => ['content' => 'a']]]],
            ['choices' => [['delta' => ['content' => 'b']]]],
        ]);

        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->once())
            ->method('createStreamed')
            ->willReturn($stream);

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client);
        $runner = new Runner($agent, 1);

        $chunks = iterator_to_array($runner->runStreamed('hi'));
        $this->assertSame(['a', 'b'], $chunks);
    }
}
