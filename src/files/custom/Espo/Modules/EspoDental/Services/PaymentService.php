<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Services;

use DateTimeImmutable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\Modules\EspoDental\Entities\Invoice;
use Espo\Modules\EspoDental\Entities\Payment;

class PaymentService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly User $user,
        private readonly Config $config
    ) {
    }

    /**
     * @param array{
     *     patientId: string,
     *     invoiceId?: ?string,
     *     amount: float,
     *     method?: string,
     *     paidAt?: ?string,
     *     notes?: ?string,
     *     clinicId?: ?string
     * } $data
     * @return array{paymentId: string, number: string}
     */
    public function accept(array $data): array
    {
        if (empty($data['patientId']) || !isset($data['amount'])) {
            throw new BadRequest('patientId and amount are required');
        }
        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new BadRequest('Amount must be positive');
        }

        $clinicId = $data['clinicId'] ?? null;
        $invoice = null;
        if (!empty($data['invoiceId'])) {
            /** @var Invoice|null $invoice */
            $invoice = $this->entityManager->getEntityById(Invoice::ENTITY_TYPE, (string) $data['invoiceId']);
            if (!$invoice) {
                throw new NotFound('Invoice not found');
            }

            $this->assertInvoicePayable($invoice, (string) $data['patientId'], $clinicId, $amount);
            $clinicId = $invoice->get('clinicId');
        }
        if (!$clinicId) {
            throw new BadRequest('clinicId is required');
        }

        /** @var Payment $payment */
        $payment = $this->entityManager->getNewEntity(Payment::ENTITY_TYPE);
        $payment->set('patientId', $data['patientId']);
        $payment->set('clinicId', $clinicId);
        $payment->set('invoiceId', $data['invoiceId'] ?? null);
        $payment->set('amount', $amount);
        $method = (string) ($data['method'] ?? Payment::METHOD_CASH);
        if (!in_array($method, $this->getAllowedMethods(), true)) {
            throw new BadRequest('Invalid payment method');
        }

        $payment->set('method', $method);
        $payment->set('direction', Payment::DIRECTION_IN);
        $payment->set('status', Payment::STATUS_COMPLETED);
        $payment->set(
            'paidAt',
            $data['paidAt'] ?? (new DateTimeImmutable())->format('Y-m-d H:i:s')
        );
        $payment->set('receivedById', $this->user->getId());
        if (!empty($data['notes'])) {
            $payment->set('notes', (string) $data['notes']);
        }
        $this->entityManager->saveEntity($payment);

        return [
            'paymentId' => (string) $payment->getId(),
            'number' => (string) $payment->get('number'),
        ];
    }

    private function assertInvoicePayable(
        Invoice $invoice,
        string $patientId,
        ?string $clinicId,
        float $amount
    ): void {
        if (
            in_array($invoice->getStatus(), [
                Invoice::STATUS_DRAFT,
                Invoice::STATUS_PAID,
                Invoice::STATUS_STORNO,
                Invoice::STATUS_CANCELLED,
            ], true)
        ) {
            throw new Conflict('Invoice is not payable');
        }

        if ((string) $invoice->getPatientId() !== $patientId) {
            throw new BadRequest('patientId does not match invoice');
        }

        $invoiceClinicId = (string) ($invoice->get('clinicId') ?? '');
        if ($clinicId && $invoiceClinicId !== '' && $clinicId !== $invoiceClinicId) {
            throw new BadRequest('clinicId does not match invoice');
        }

        $balance = round($invoice->getBalance(), 2);
        if ($balance <= 0.0) {
            throw new Conflict('Invoice has no payable balance');
        }
        if (round($amount, 2) > $balance) {
            throw new BadRequest('Payment amount exceeds invoice balance');
        }
    }

    /**
     * @return array{refundPaymentId: string}
     */
    public function refund(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        /** @var Payment|null $source */
        $source = $this->entityManager->getEntityById(Payment::ENTITY_TYPE, $paymentId);
        if (!$source) {
            throw new NotFound('Payment not found');
        }
        if ($source->getStatus() !== Payment::STATUS_COMPLETED) {
            throw new Conflict('Only completed payments can be refunded');
        }
        if ($source->getDirection() !== Payment::DIRECTION_IN) {
            throw new Conflict('Only inbound payments can be refunded');
        }

        $refundAmount = $amount ?? $source->getAmount();
        if ($refundAmount <= 0) {
            throw new BadRequest('Refund amount must be positive');
        }
        if ($refundAmount > $source->getAmount()) {
            throw new BadRequest('Refund cannot exceed original payment');
        }

        /** @var Payment $refund */
        $refund = $this->entityManager->getNewEntity(Payment::ENTITY_TYPE);
        $refund->set('patientId', $source->getPatientId());
        $refund->set('clinicId', $source->get('clinicId'));
        $refund->set('invoiceId', $source->getInvoiceId());
        $refund->set('amount', $refundAmount);
        $refund->set('method', (string) $source->get('method'));
        $refund->set('direction', Payment::DIRECTION_OUT);
        $refund->set('status', Payment::STATUS_COMPLETED);
        $refund->set('paidAt', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
        $refund->set('receivedById', $this->user->getId());
        $refund->set('refundOfId', $source->getId());
        $refund->set('notes', $reason !== '' ? $reason : ('Refund of #' . (string) $source->get('number')));
        $this->entityManager->saveEntity($refund);

        $source->set('status', Payment::STATUS_REFUNDED);
        $this->entityManager->saveEntity($source);

        return ['refundPaymentId' => (string) $refund->getId()];
    }

    /**
     * @return list<string>
     */
    private function getAllowedMethods(): array
    {
        $configured = $this->config->get('espoDentalPaymentMethods', []);
        if (is_string($configured) && $configured !== '') {
            $decoded = json_decode($configured, true);
            $configured = is_array($decoded) ? $decoded : [];
        }

        $methods = [];
        if (is_array($configured)) {
            foreach ($configured as $item) {
                $id = '';
                if (is_string($item)) {
                    $id = $item;
                } elseif (is_array($item)) {
                    $id = (string) ($item['id'] ?? $item['value'] ?? $item['name'] ?? '');
                } elseif (is_object($item)) {
                    $id = (string) ($item->id ?? $item->value ?? $item->name ?? '');
                }

                $id = trim($id);
                if ($id !== '') {
                    $methods[] = $id;
                }
            }
        }

        if ($methods === []) {
            $methods = Payment::METHOD_LIST;
        }

        if (!in_array(Payment::METHOD_CASH, $methods, true)) {
            array_unshift($methods, Payment::METHOD_CASH);
        }

        return array_values(array_unique($methods));
    }
}
