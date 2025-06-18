<?php
// Minimal stubs of OpenAI client interfaces for testing without the package.
namespace OpenAI\Contracts {
    interface ChatContract {
        public function create(array $parameters);
    }

    interface AudioContract {
        public function speech(array $parameters);
    }

    interface ClientContract {
        public function chat(): ChatContract;
        public function audio(): AudioContract;
    }
}

namespace {
    require_once __DIR__ . '/../src/Agent.php';
    require_once __DIR__ . '/../src/Runner.php';
    require_once __DIR__ . '/../src/Guardrails/GuardrailException.php';
    require_once __DIR__ . '/../src/Guardrails/InputGuardrailException.php';
    require_once __DIR__ . '/../src/Guardrails/OutputGuardrailException.php';
    require_once __DIR__ . '/../src/Guardrails/InputGuardrail.php';
    require_once __DIR__ . '/../src/Guardrails/OutputGuardrail.php';
    require_once __DIR__ . '/../src/Tracing/Tracing.php';
}
