<?php

namespace OpenAI {
    class OpenAI {
        public static $factory;
        public static function factory() {
            return static::$factory;
        }
    }
}

namespace {

use OpenAI\Contracts\ChatContract;
use OpenAI\Contracts\AudioContract;
use OpenAI\Contracts\ClientContract;
use Aerobit\OpenaiAgents\AgentManager;
use PHPUnit\Framework\TestCase;

class AgentManagerTest extends TestCase
{
    public function test_agent_merges_default_config()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->once())
            ->method('create')
            ->with($this->callback(function(array $p) {
                return $p['model'] === 'gpt-4' &&
                       $p['temperature'] === 0.5 &&
                       $p['top_p'] === 0.9;
            }))
            ->willReturn(['choices' => [['message' => ['content' => 'ok']]]]);

        $client = new class($chat) implements ClientContract {
            private $chat;
            public function __construct($chat) { $this->chat = $chat; }
            public function chat(): ChatContract { return $this->chat; }
            public function audio(): AudioContract { throw new \Exception('audio'); }
        };

        \OpenAI\OpenAI::$factory = new class($client) {
            private $client;
            public function __construct($client) { $this->client = $client; }
            public function withApiKey($key) { return $this; }
            public function make() { return $this->client; }
        };

        $config = [
            'api_key' => 'key',
            'default' => [
                'model' => 'gpt-4',
                'temperature' => 0.1,
                'top_p' => 0.9,
            ],
        ];

        $manager = new AgentManager($config);
        $agent = $manager->agent(['temperature' => 0.5]);
        $reply = $agent->chat('hello');

        $this->assertSame('ok', $reply);
    }

    public function test_agent_overrides_multiple_options()
    {
        $chat = $this->createMock(ChatContract::class);
        $chat->expects($this->once())
            ->method('create')
            ->with($this->callback(function(array $p) {
                return $p['model'] === 'gpt-3.5' &&
                       $p['temperature'] === 0.1 &&
                       $p['top_p'] === 0.8;
            }))
            ->willReturn(['choices' => [['message' => ['content' => 'ok']]]]);

        $client = new class($chat) implements ClientContract {
            private $chat;
            public function __construct($chat) { $this->chat = $chat; }
            public function chat(): ChatContract { return $this->chat; }
            public function audio(): AudioContract { throw new \Exception('audio'); }
        };

        \OpenAI\OpenAI::$factory = new class($client) {
            private $client;
            public function __construct($client) { $this->client = $client; }
            public function withApiKey($key) { return $this; }
            public function make() { return $this->client; }
        };

        $config = [
            'api_key' => 'key',
            'default' => [
                'model' => 'gpt-4',
                'temperature' => 0.1,
                'top_p' => 0.9,
            ],
        ];

        $manager = new AgentManager($config);
        $agent = $manager->agent([
            'model' => 'gpt-3.5',
            'top_p' => 0.8,
        ]);
        $reply = $agent->chat('hello');

        $this->assertSame('ok', $reply);
    }
}

}
