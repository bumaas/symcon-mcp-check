<?php

declare(strict_types=1);

class FlawedDevice extends IPSModuleStrict
{
    private const int STATUS_BUSY = 205;

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $ret = self::STATUS_BUSY;
        $this->SetStatus($ret);
        $this->SetStatus(204);
    }

    public function SetMode(int $Value): void
    {
    }

    // A button caption "Refresh" is no hint for FLD_Refresh: only the full name counts.
    public function Refresh(): void
    {
    }

    public function RunSelfTest(bool $verbose): bool
    {
        return true;
    }
}
