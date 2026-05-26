<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\Tools;

use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\ORM\Entity;

class QuestionnaireSchemaProvider
{
    public const SUPPORTED_LANGUAGES = ['ru_RU', 'en_US', 'es_ES'];

    private const SCHEMA_PATH = 'custom/Espo/Modules/EspoDental/Resources/metadata/dental/questionnaireSchema.json';

    /** @var array<string, mixed>|null */
    private ?array $raw = null;

    public function __construct(private readonly FileManager $fileManager)
    {
    }

    /**
     * Returns a flat schema localised to the given language.
     *
     * @return array{version: int, groups: array<int, array<string, mixed>>}
     */
    public function get(string $language, ?Entity $subject = null): array
    {
        $language = in_array($language, self::SUPPORTED_LANGUAGES, true) ? $language : 'ru_RU';
        $raw = $this->loadRaw();

        $groups = [];
        foreach ($raw['groups'] ?? [] as $group) {
            $items = [];
            foreach ($group['items'] ?? [] as $item) {
                $items[] = [
                    'id' => $item['id'],
                    'type' => $item['type'] ?? 'bool',
                    'alert' => (bool) ($item['alert'] ?? false),
                    'required' => ($item['type'] ?? 'bool') === 'bool',
                    'label' => $item['labels'][$language] ?? $item['labels']['en_US'] ?? $item['id'],
                ];
            }
            $groups[] = [
                'id' => $group['id'],
                'label' => $group['labels'][$language] ?? $group['labels']['en_US'] ?? $group['id'],
                'conditional' => $group['conditional'] ?? null,
                'items' => $items,
            ];
        }

        return [
            'version' => (int) ($raw['version'] ?? 1),
            'templateType' => $this->getTemplateType($subject),
            'pdfLanguageMode' => 'es_ru',
            'groups' => $groups,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAll(?Entity $subject = null): array
    {
        $schemas = [];
        foreach (self::SUPPORTED_LANGUAGES as $language) {
            $schemas[$language] = $this->get($language, $subject);
        }

        return $schemas;
    }

    public function getTemplateType(?Entity $subject): string
    {
        if (!$subject) {
            return 'adult';
        }

        if ((bool) $subject->get('isChild')) {
            return 'child';
        }

        $birthDate = (string) ($subject->get('dateOfBirth') ?? '');
        if ($birthDate === '') {
            return 'adult';
        }

        try {
            $age = (new \DateTimeImmutable($birthDate))->diff(new \DateTimeImmutable())->y;
        } catch (\Exception) {
            return 'adult';
        }

        return $age < 18 ? 'child' : 'adult';
    }

    /**
     * @return array<int, string>
     */
    public function getAlertItemIds(): array
    {
        $raw = $this->loadRaw();
        $ids = [];
        foreach ($raw['groups'] ?? [] as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (!empty($item['alert'])) {
                    $ids[] = $item['id'];
                }
            }
        }
        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadRaw(): array
    {
        if ($this->raw !== null) {
            return $this->raw;
        }
        $contents = $this->fileManager->getContents(self::SCHEMA_PATH);
        if ($contents === false || $contents === '') {
            throw new Error('questionnaireSchema.json not found');
        }
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new Error('Invalid questionnaireSchema.json');
        }
        $this->raw = $decoded;
        return $decoded;
    }
}
