<?php

declare(strict_types=1);

namespace Espo\Modules\EspoDental\EntryPoints;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\File\Manager as FileManager;

class PatientPortal
{
    private const TEMPLATE_PATH = 'custom/Espo/Modules/EspoDental/Resources/templates/public/patientPortal.html.tpl';

    public function __construct(
        private readonly Config $config,
        private readonly FileManager $fileManager
    ) {
    }

    public function run(Request $request, Response $response): void
    {
        $template = (string) $this->fileManager->getContents(self::TEMPLATE_PATH);
        if ($template === '') {
            $template = '<!doctype html><meta charset="utf-8"><pre>Template missing</pre>';
        }

        $siteUrl = rtrim((string) $this->config->get('siteUrl'), '/');
        $html = strtr($template, [
            '{{apiBase}}' => $this->html($siteUrl . '/api/v1/EspoDental/Public/PatientPortal'),
            '{{title}}' => 'Patient Portal',
        ]);

        $response
            ->setHeader('Content-Type', 'text/html; charset=utf-8')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->writeBody($html);
    }

    private function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
