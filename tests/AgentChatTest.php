<?php

use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\ChatContract;
use OpenAI\LaravelAgents\Agent;
use PHPUnit\Framework\TestCase;

class AgentChatTest extends TestCase
{
    public function test_chat_returns_reply_from_client()
    {
        $chatMock = $this->createMock(ChatContract::class);
        $chatMock->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $params) {
                return isset($params['messages'][0]) &&
                       $params['messages'][0]['role'] === 'user' &&
                       $params['messages'][0]['content'] === 'Hello';
            }))
            ->willReturn([
                'choices' => [
                    ['message' => ['content' => 'Hi there']]
                ]
            ]);

        $clientMock = $this->createMock(ClientContract::class);
        $clientMock->expects($this->once())
            ->method('chat')
            ->willReturn($chatMock);

        $agent = new Agent($clientMock);

        $reply = $agent->chat('Hello');

        $this->assertSame('Hi there', $reply['content']);
    }
}
