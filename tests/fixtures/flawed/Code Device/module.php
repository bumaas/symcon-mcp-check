<?php

declare(strict_types=1);

/** Module with a form built in code, flawed in every rule. */
class FlawedCodeDevice extends IPSModuleStrict
{
    private const STATUS_NO_ANSWER = 201;
    private const STATUS_BUSY      = 206;

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetStatus($this->ReadPropertyString('Host') === '' ? self::STATUS_NO_ANSWER : self::STATUS_BUSY);
    }

    public function GetConfigurationForm(): string
    {
        return json_encode([
            'elements' => [
                ['name' => 'ApiToken', 'type' => 'ValidationTextBox', 'caption' => 'API token'],
            ],
            'actions'  => [],
            'status'   => [
                ['code' => self::STATUS_NO_ANSWER, 'icon' => 'error', 'caption' => 'Device does not respond.'],
                ['code' => 203, 'icon' => 'error', 'caption' => 'Not translated in code.'],
            ]
        ]);
    }

    public function RunSelfTest(): string
    {
        return 'ok';
    }

    public function SwitchOutput(bool $Value): void
    {
    }
}

class FlawedCodeDeviceHelper
{
    public function parse(string $data): array
    {
        return [];
    }
}
