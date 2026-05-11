<?php

declare(strict_types=1);

namespace EspoDental\Tests;

use PHPUnit\Framework\TestCase;

final class Phase8MetadataTest extends TestCase
{
    private const MODULE_ROOT = __DIR__ . '/../src/files/custom/Espo/Modules/EspoDental';
    private const LOCALES = ['ru_RU', 'en_US', 'es_ES'];

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data, "Invalid JSON: $path");
        return $data;
    }

    public function testNotificationLogScopeAndDefs(): void
    {
        $scope = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/scopes/NotificationLog.json');
        $this->assertTrue($scope['entity']);

        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/NotificationLog.json');
        foreach (['email', 'telegram', 'sms', 'internal'] as $ch) {
            $this->assertContains($ch, $def['fields']['channel']['options']);
        }
        foreach (['queued', 'sent', 'failed', 'skipped'] as $s) {
            $this->assertContains($s, $def['fields']['status']['options']);
        }
        $this->assertContains('appointment_reminder', $def['fields']['kind']['options']);
        $this->assertSame('belongsTo', $def['links']['patient']['type']);
        $this->assertSame('belongsTo', $def['links']['appointment']['type']);
    }

    public function testPatientHasTelegramChatIdAndReminderToggle(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Patient.json');
        $this->assertArrayHasKey('telegramChatId', $def['fields']);
        $this->assertArrayHasKey('remindersEnabled', $def['fields']);
        $this->assertArrayHasKey('notificationLogs', $def['links']);
        $this->assertSame('hasMany', $def['links']['notificationLogs']['type']);
    }

    public function testAppointmentHasNotificationLogsLink(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Appointment.json');
        $this->assertArrayHasKey('notificationLogs', $def['links']);
    }

    public function testSettingsParamsExist(): void
    {
        $def = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/entityDefs/Settings.json');
        foreach ([
            'espoDentalTelegramEnabled',
            'espoDentalTelegramBotToken',
            'espoDentalTelegramApiBase',
            'espoDentalReminderHoursBefore',
            'espoDentalReminderSecondHoursBefore',
            'espoDentalReminderWindowMinutes',
            'espoDentalDefaultCurrency',
        ] as $field) {
            $this->assertArrayHasKey($field, $def['fields']);
        }
        $this->assertSame('password', $def['fields']['espoDentalTelegramBotToken']['type']);

        $appSettings = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/settings.json');
        $this->assertTrue($appSettings['params']['espoDentalTelegramBotToken']['isSensitive']);
    }

    public function testAdminPanelRegistered(): void
    {
        $panel = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/adminPanel.json');
        $this->assertArrayHasKey('espoDental', $panel);
    }

    public function testToolsAndServicesExist(): void
    {
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/TelegramSender.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Tools/ReminderTemplate.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Services/ReminderService.php');
        $this->assertFileExists(self::MODULE_ROOT . '/Jobs/SendAppointmentReminders.php');

        $rs = (string) file_get_contents(self::MODULE_ROOT . '/Services/ReminderService.php');
        $this->assertStringContainsString('sendDueReminders', $rs);
        $this->assertStringContainsString('sendForAppointment', $rs);
        $this->assertStringContainsString('CHANNEL_TELEGRAM', $rs);
    }

    public function testReminderJobRegistered(): void
    {
        $jobs = $this->readJson(self::MODULE_ROOT . '/Resources/metadata/app/scheduledJobs.json');
        $this->assertArrayHasKey('EspoDentalSendAppointmentReminders', $jobs);
    }

    public function testGlobalScopeNamesIncludeNotificationLog(): void
    {
        foreach (self::LOCALES as $locale) {
            $global = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Global.json");
            $this->assertArrayHasKey('NotificationLog', $global['scopeNames']);
            $this->assertArrayHasKey('NotificationLog', $global['scopeNamesPlural']);
        }
    }

    public function testNotificationLogLocalesExist(): void
    {
        foreach (self::LOCALES as $locale) {
            $loc = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/NotificationLog.json");
            foreach (['channel', 'kind', 'status'] as $f) {
                $this->assertArrayHasKey($f, $loc['fields']);
            }
        }
    }

    public function testSettingsLocalesExist(): void
    {
        foreach (self::LOCALES as $locale) {
            $loc = $this->readJson(self::MODULE_ROOT . "/Resources/i18n/{$locale}/Settings.json");
            $this->assertArrayHasKey('espoDentalTelegramEnabled', $loc['fields']);
            $this->assertArrayHasKey('espoDentalReminderHoursBefore', $loc['fields']);
        }
    }

    public function testAfterInstallContainsNotificationLog(): void
    {
        $code = (string) file_get_contents(__DIR__ . '/../src/scripts/AfterInstall.php');
        $this->assertStringContainsString("'NotificationLog'", $code);
    }

    public function testReminderTemplateMultiLanguage(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/ReminderTemplate.php');
        $this->assertStringContainsString('buildRu', $code);
        $this->assertStringContainsString('buildEn', $code);
        $this->assertStringContainsString('buildEs', $code);
    }

    public function testTelegramSenderHasEnabledGuard(): void
    {
        $code = (string) file_get_contents(self::MODULE_ROOT . '/Tools/TelegramSender.php');
        $this->assertStringContainsString('isEnabled', $code);
        $this->assertStringContainsString('espoDentalTelegramBotToken', $code);
    }
}
