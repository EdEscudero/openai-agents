<?php

use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\ChatContract;
use Aerobit\OpenaiAgents\Agent;
use PHPUnit\Framework\TestCase;

class AgentCloneTest extends TestCase
{
    public function test_clone_copies_state_and_overrides()
    {
        $chat = $this->createMock(ChatContract::class);
        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client, ['temperature' => 0.5], 'sys', null, ['foo' => 'bar']);
        $clone = $agent->clone(['temperature' => 1.0], 'new');

        $this->assertInstanceOf(Agent::class, $clone);
        $this->assertNotSame($agent, $clone);
        $this->assertSame(['foo' => 'bar'], $clone->getContext());

        $chat->expects($this->once())->method('create')->willReturn(['choices' => [['message' => ['content' => 'ok']]]]);
        $result = $clone->chat('hi');
        $this->assertSame('ok', $result);
    }
}
