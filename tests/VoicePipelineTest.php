<?php

use OpenAI\Contracts\AudioContract;
use OpenAI\Contracts\ClientContract;
use OpenAI\Contracts\ChatContract;
use OpenAI\LaravelAgents\Agent;
use OpenAI\LaravelAgents\VoicePipeline;
use PHPUnit\Framework\TestCase;

class VoicePipelineTest extends TestCase
{
    public function test_run_transcribes_and_speaks()
    {
        $audio = $this->createMock(AudioContract::class);
        $audio->expects($this->once())
            ->method('transcribe')
            ->willReturn('hello');
        $audio->expects($this->once())
            ->method('speech')
            ->willReturn('audio-data');

        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->once())
            ->method('create')
            ->willReturn(['choices' => [['message' => ['content' => 'reply']]]]);

        $client = $this->createMock(ClientContract::class);
        $client->method('audio')->willReturn($audio);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client);
        $pipeline = new VoicePipeline($client, $agent);

        $result = $pipeline->run(__FILE__);
        $this->assertSame('audio-data', $result);
    }
}
