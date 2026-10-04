<?php

declare(strict_types=1);

class ExampleDevice extends IPSModuleStrict
{
    private const int STATUS_NO_ANSWER = 201;
    private const int STATUS_ACCESS_DENIED = 202;

    public function Create(): void
    {
        parent::Create();
        $this->RegisterTimer('Poll', 0, 'EXD_Poll($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $status = $this->ReadPropertyString('Host') === '' ? self::STATUS_NO_ANSWER : IS_ACTIVE;
        $this->SetStatus($status);
        if ($status !== IS_ACTIVE) {
            $this->LogMessage('Device does not answer, check the host.', KL_WARNING);
        }
        if ($this->ReadPropertyString('ApiToken') === 'x') {
            $this->SetStatus(202);
        }
    }

    public function SetBrightness(int $brightnessPercent): bool
    {
        return $brightnessPercent >= 0;
    }

    public function Poll(): void
    {
    }

    public function RunSelfTest(): string
    {
        return 'ok';
    }
}
