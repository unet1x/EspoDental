<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase29CashDeskCorrectionsTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';
    private const MODULE_ROOT = self::ROOT . '/src/files/custom/Espo/Modules/EspoDental';

    public function testRefundCreatesCorrectionPaymentWithoutMutatingSource(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/PaymentService.php');

        $this->assertStringContainsString('sumExistingRefunds', $code);
        $this->assertStringContainsString('Payment has no refundable amount remaining', $code);
        $this->assertStringContainsString('Refund cannot exceed remaining refundable amount', $code);
        $this->assertStringContainsString("'refundOfId' => \$source->getId()", $code);
        $this->assertStringNotContainsString("\$source->set('status', Payment::STATUS_REFUNDED)", $code);
        $this->assertStringNotContainsString('$this->entityManager->saveEntity($source)', $code);
    }

    public function testPaidInvoicesMustBeRefundedBeforeStorno(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Services/InvoiceService.php');

        $this->assertStringContainsString('$this->calculator->recalculate($invoice);', $code);
        $this->assertStringContainsString('getPaidAmount()', $code);
        $this->assertStringContainsString('Refund invoice payments before storno', $code);
        $this->assertStringContainsString('Invoice cannot be storno-ed from current status', $code);
    }

    public function testPostedPaymentGuardExists(): void
    {
        $path = self::MODULE_ROOT . '/Hooks/Payment/PreventPostedMutation.php';
        $this->assertFileExists($path);

        $code = (string) file_get_contents($path);
        $this->assertStringContainsString('beforeSave', $code);
        $this->assertStringContainsString('beforeRemove', $code);
        $this->assertStringContainsString('$entity->isNew()', $code);
        $this->assertStringContainsString('espodentalAllowPaymentMutation', $code);
        $this->assertStringContainsString('Payment::STATUS_COMPLETED', $code);
        $this->assertStringContainsString('Payment::STATUS_REFUNDED', $code);
        $this->assertStringContainsString('Posted payments are immutable; create a refund payment', $code);
    }

    public function testDocsRecordCorrectionWorkflow(): void
    {
        $currentState = (string) file_get_contents(self::ROOT . '/docs/current-state.md');
        $roadmap = (string) file_get_contents(self::ROOT . '/docs/roadmap.md');
        $releaseNotes = (string) file_get_contents(self::ROOT . '/docs/release-notes.md');
        $acceptance = (string) file_get_contents(self::ROOT . '/docs/acceptance-checklist.md');

        foreach ([$currentState, $releaseNotes, $acceptance] as $doc) {
            $this->assertStringContainsString('refund payment', $doc);
            $this->assertStringContainsString('Refund invoice payments before storno', $doc);
        }

        $this->assertStringContainsString('write-off and reversal/storno flows', $roadmap);
        $this->assertStringContainsString('explicit correction workflow', $currentState);
    }
}
