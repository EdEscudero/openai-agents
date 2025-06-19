<?php

use OpenAI\Contracts\ChatContract;
use OpenAI\Contracts\ClientContract;
use Aerobit\OpenaiAgents\Agent;
use Aerobit\OpenaiAgents\Runner;
use Aerobit\OpenaiAgents\Guardrails\InputGuardrail;
use Aerobit\OpenaiAgents\Guardrails\OutputGuardrail;
use Aerobit\OpenaiAgents\Guardrails\OutputGuardrailException;
use Aerobit\OpenaiAgents\Guardrails\InputGuardrailException;
use PHPUnit\Framework\TestCase;

class GuardrailTest extends TestCase
{
    public function test_input_guardrail_modifies_input()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->once())
            ->method('create')
            ->with($this->callback(fn(array $p) => $p['messages'][0]['content'] === 'HELLO'))
            ->willReturn(['choices' => [['message' => ['content' => 'Hi']]]]);

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client);
        $runner = new Runner($agent);
        $runner->addInputGuardrail(new class extends InputGuardrail {
            public function validate(string $content): string
            {
                return strtoupper($content);
            }
        });

        $this->assertSame('Hi', $runner->run('hello'));
    }

    public function test_output_guardrail_exception_is_thrown()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->once())
            ->method('create')
            ->willReturn(['choices' => [['message' => ['content' => 'bad']]]]);

        $client = $this->createMock(ClientContract::class);
        $client->method('chat')->willReturn($chat);

        $agent = new Agent($client);
        $runner = new Runner($agent);
        $runner->addOutputGuardrail(new class extends OutputGuardrail {
            public function validate(string $content): string
            {
                throw new OutputGuardrailException('Disallowed');
            }
        });

        $this->expectException(OutputGuardrailException::class);
        $runner->run('start');
    }
}
