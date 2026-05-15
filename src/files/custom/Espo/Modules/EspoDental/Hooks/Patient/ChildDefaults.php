<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Hooks\Patient;

use DateTimeImmutable;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\EspoDental\Entities\Patient;
use Espo\ORM\Entity;

class ChildDefaults
{
    public static int $order = 10;

    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function beforeSave(Entity $entity, array $options = []): void
    {
        if (!$entity instanceof Patient) {
            return;
        }

        $this->applyChildFlag($entity);
        $this->copyLinkedParentData($entity);
    }

    private function applyChildFlag(Patient $patient): void
    {
        $dateOfBirth = (string) ($patient->get('dateOfBirth') ?? '');
        if ($dateOfBirth === '') {
            return;
        }

        try {
            $birthDate = new DateTimeImmutable($dateOfBirth);
        } catch (\Throwable) {
            return;
        }

        $today = new DateTimeImmutable('today');
        if ($birthDate > $today) {
            return;
        }

        $age = $birthDate->diff($today)->y;
        if ($age <= 14) {
            $patient->set('isChild', true);
        }
    }

    private function copyLinkedParentData(Patient $patient): void
    {
        $parentPatientId = $patient->getParentPatientId();
        if (!$patient->isChild() || !$parentPatientId) {
            return;
        }

        /** @var Patient|null $parent */
        $parent = $this->entityManager->getEntityById(Patient::ENTITY_TYPE, $parentPatientId);
        if (!$parent) {
            return;
        }

        $patient->set('parentLastName', $parent->get('lastName'));
        $patient->set('parentFirstName', $parent->get('firstName'));
        $patient->set('parentMiddleName', $parent->get('middleName'));

        $parentPhoneData = $parent->get('phoneNumberData');
        if ($parentPhoneData) {
            $patient->set('parentPhoneNumberData', $parentPhoneData);
        }

        $parentPhone = $parent->get('phoneNumber');
        if ($parentPhone) {
            $patient->set('parentPhoneNumber', $parentPhone);
        }
    }
}
