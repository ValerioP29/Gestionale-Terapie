<?php

namespace Tests\Unit;

use App\Models\MessageLog;
use PHPUnit\Framework\TestCase;

class MessageLogNamingAliasTest extends TestCase
{
    public function test_pharmacy_id_accessor_reads_legacy_pharma_id(): void
    {
        $log = new MessageLog(['pharma_id' => 12]);

        $this->assertSame(12, $log->pharmacy_id);
    }

    public function test_setting_pharmacy_id_populates_legacy_column(): void
    {
        $log = new MessageLog();
        $log->pharmacy_id = 7;

        $this->assertSame(7, $log->pharma_id);
        $this->assertSame(7, $log->pharmacy_id);
    }
}
