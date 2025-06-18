<?php

use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\ChatContract;
use OpenAI\Contracts\AudioContract;
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

        $this->assertSame('Hi there', $reply);
    }

    public function test_speak_calls_audio_api()
    {
        $audioMock = $this->createMock(AudioContract::class);
        $audioMock->expects($this->once())
            ->method('speech')
            ->with($this->callback(fn(array $p) => $p['input'] === 'Hello'))
            ->willReturn('audio-data');

        $clientMock = $this->createMock(ClientContract::class);
        $clientMock->method('chat')->willReturn($this->createMock(ChatContract::class));
        $clientMock->expects($this->once())
            ->method('audio')
            ->willReturn($audioMock);

        $agent = new Agent($clientMock);

        $result = $agent->speak('Hello');

        $this->assertSame('audio-data', $result);
    }
}
