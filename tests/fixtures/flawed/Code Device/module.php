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
        // sets error states, but never writes the cause to the log
        $this->SetStatus($this->ReadPropertyString('Host') === '' ? self::STATUS_NO_ANSWER : self::STATUS_BUSY);
    }

    public function GetConfigurationForm(): string
    {
        return json_encode([
            'elements' => [
                ['name' => 'ApiToken', 'type' => 'ValidationTextBox', 'caption' => 'API token'],
            ],
            'actions'  => [
                // a hint that shows in the console, naming a function that no longer exists
                ['type' => 'Label', 'caption' => 'For scripts and AI assistants: FCD_OldFunction(int $InstanceID): bool was removed.'],
            ],
            'status'   => [
                ['code' => self::STATUS_NO_ANSWER, 'icon' => 'error', 'caption' => 'Device does not respond.'],
                ['code' => 203, 'icon' => 'error', 'caption' => 'Not translated in code.'],
            ]
        ]);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Output':
                $this->SwitchOutput((bool)$Value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'unknown ident ' . $Ident, 0);
        }
    }

    public function RunSelfTest(): string
    {
        return 'ok';
    }

    public function SwitchOutput(bool $Value): void
    {
    }

    public function SetPower(int $power): void
    {
    }

    private function Login(string $token): void
    {
        $this->SendDebug('Login', 'token: ' . $token, 0);
    }
}

class FlawedCodeDeviceHelper
{
    public function parse(string $data): array
    {
        return [];
    }
}
