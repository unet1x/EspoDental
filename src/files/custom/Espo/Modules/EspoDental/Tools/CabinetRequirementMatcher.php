<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\ORM\Entity;
use stdClass;

class CabinetRequirementMatcher
{
    public function matches(?Entity $service, Entity $cabinet): bool
    {
        if (!$service) {
            return true;
        }

        $requirements = $this->normalizeRequirements($service->get('cabinetRequirements'));

        if ($requirements === []) {
            return true;
        }

        $cabinetId = (string) $cabinet->getId();
        $cabinetCode = mb_strtolower(trim((string) ($cabinet->get('code') ?? '')));
        $cabinetText = mb_strtolower(trim(implode(' ', array_filter([
            (string) ($cabinet->get('name') ?? ''),
            (string) ($cabinet->get('code') ?? ''),
            (string) ($cabinet->get('equipment') ?? ''),
            (string) ($cabinet->get('description') ?? ''),
        ]))));

        $cabinetIds = $this->listValues($requirements, ['cabinetIds', 'cabinets']);
        if ($cabinetIds !== [] && !in_array($cabinetId, $cabinetIds, true)) {
            return false;
        }

        $cabinetCodes = array_map(
            static fn (string $value): string => mb_strtolower($value),
            $this->listValues($requirements, ['cabinetCodes', 'codes'])
        );
        if ($cabinetCodes !== [] && !in_array($cabinetCode, $cabinetCodes, true)) {
            return false;
        }

        foreach ($this->listValues($requirements, ['equipmentAll', 'requiredEquipment', 'all']) as $token) {
            if (!$this->textContainsToken($cabinetText, $token)) {
                return false;
            }
        }

        $anyTokens = $this->listValues($requirements, ['equipmentAny', 'equipment', 'procedureTypes', 'procedures', 'any']);
        if ($anyTokens !== [] && !$this->textContainsAnyToken($cabinetText, $anyTokens)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRequirements(mixed $raw): array
    {
        if ($raw instanceof stdClass) {
            $raw = get_object_vars($raw);
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $raw = trim($raw);

            if ($raw === '') {
                return [];
            }

            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            return ['equipmentAny' => [$raw]];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $requirements
     * @param list<string> $keys
     * @return list<string>
     */
    private function listValues(array $requirements, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $requirements)) {
                continue;
            }

            $values = array_merge($values, $this->normalizeList($requirements[$key]));
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (string $value): string => trim($value), $values),
            static fn (string $value): bool => $value !== ''
        )));
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $out[] = (string) $item;
                }
            }

            return $out;
        }

        if (is_scalar($value)) {
            return [(string) $value];
        }

        return [];
    }

    private function textContainsAnyToken(string $text, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($this->textContainsToken($text, $token)) {
                return true;
            }
        }

        return false;
    }

    private function textContainsToken(string $text, string $token): bool
    {
        $token = mb_strtolower(trim($token));

        return $token === '' || str_contains($text, $token);
    }
}
