<?php
// Minimal stubs of OpenAI client interfaces for testing without the package.
namespace OpenAI\Contracts {
    interface ChatContract {
        public function create(array $parameters);
    }

    interface ClientContract {
        public function chat(): ChatContract;
    }
}

namespace {
    require_once __DIR__ . '/../src/Agent.php';
}
