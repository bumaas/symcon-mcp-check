<?php

declare(strict_types=1);

/** Module whose configuration form is built in code (no form.json) — like Roborock. */
class CodeDevice extends IPSModuleStrict
{
    private const STATUS_NO_ANSWER     = 201;
    private const STATUS_TOKEN_INVALID = 205;

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetStatus($this->ReadPropertyString('Host') === '' ? self::STATUS_NO_ANSWER : self::STATUS_TOKEN_INVALID);
    }

    public function GetConfigurationForm(): string
    {
        return json_encode([
            'elements' => [
                ['type' => 'PasswordTextBox', 'name' => 'Password', 'caption' => 'Password'],
            ],
            'actions'  => [
                [
                    'type'    => 'Label',
                    'visible' => false,
                    'caption' => 'For scripts and AI assistants: CFD_SetLevel(int $InstanceID, int $levelPercent): bool sets the level.'
                ],
            ],
            'status'   => $this->FormStatus()
        ]);
    }

    public function SetLevel(int $levelPercent): bool
    {
        return $levelPercent >= 0;
    }

    public function RunSelfTest(): string
    {
        return 'ok';
    }

    public function __destruct()
    {
    }

    private function FormStatus(): array
    {
        return [
            [
                'code'    => self::STATUS_NO_ANSWER,
                'icon'    => 'error',
                'caption' => 'Device does not respond.'
            ],
            [
                'caption' => 'Token is not valid.',
                'icon'    => 'error',
                'code'    => self::STATUS_TOKEN_INVALID
            ]
        ];
    }
}

/** Helper class in the same file: its public methods are not module functions. */
class CodeDeviceApiHelper
{
    public static function getUrl(int $x): string
    {
        return 'https://example.com/' . $x;
    }
}
