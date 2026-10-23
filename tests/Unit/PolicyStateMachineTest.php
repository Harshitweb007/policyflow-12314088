<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PolicyState;
use App\Services\StateMachine\PolicyStateMachine;
use PHPUnit\Framework\TestCase;

final class PolicyStateMachineTest extends TestCase
{
    private PolicyStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new PolicyStateMachine;
    }

    public function test_legal_happy_path(): void
    {
        $this->assertTrue($this->machine->canTransition(PolicyState::Received, PolicyState::Validated));
        $this->assertTrue($this->machine->canTransition(PolicyState::Validated, PolicyState::Submitted));
        $this->assertTrue($this->machine->canTransition(PolicyState::Submitted, PolicyState::Issued));
    }

    public function test_issued_never_regresses(): void
    {
        foreach (PolicyState::cases() as $state) {
            $this->assertFalse($this->machine->canTransition(PolicyState::Issued, $state));
        }
    }

    public function test_failed_retry_only_to_submitted(): void
    {
        $this->assertTrue($this->machine->canTransition(PolicyState::Failed, PolicyState::Submitted));
        $this->assertFalse($this->machine->canTransition(PolicyState::Failed, PolicyState::Issued));
        $this->assertFalse($this->machine->canTransition(PolicyState::Failed, PolicyState::Validated));
    }

    public function test_reconciliation_targets(): void
    {
        $this->assertTrue($this->machine->canTransition(PolicyState::PendingConfirmation, PolicyState::Issued));
        $this->assertTrue($this->machine->canTransition(PolicyState::PendingConfirmation, PolicyState::Failed));
        $this->assertTrue($this->machine->canTransition(PolicyState::PendingConfirmation, PolicyState::NeedsReconciliation));
        $this->assertTrue($this->machine->canTransition(PolicyState::NeedsReconciliation, PolicyState::Issued));
        $this->assertTrue($this->machine->canTransition(PolicyState::NeedsReconciliation, PolicyState::PendingConfirmation));
        $this->assertFalse($this->machine->canTransition(PolicyState::NeedsReconciliation, PolicyState::Validated));
    }

    public function test_illegal_skip_from_received_to_issued(): void
    {
        $this->assertFalse($this->machine->canTransition(PolicyState::Received, PolicyState::Issued));
    }
}
