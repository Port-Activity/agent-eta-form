<?php
namespace SMA\PAA\TOOL;

use PHPUnit\Framework\TestCase;

final class DateToolsTest extends TestCase
{
    public function testIsValidIsoDateTime(): void
    {
        $tools = new DateTools();
        $this->assertTrue($tools->isValidIsoDateTime("2019-12-12T12:13:14+00:00"));
    }
    public function testFailsWhenNoDatePart(): void
    {
        $tools = new DateTools();
        $this->assertFalse($tools->isValidIsoDateTime("2019-12-12"));
    }
    public function testFailsWhenNoTimezone(): void
    {
        $tools = new DateTools();
        $this->assertFalse($tools->isValidIsoDateTime("2019-12-12T12:13:14"));
    }
    public function testFailsWhenEmptyInput(): void
    {
        $tools = new DateTools();
        $this->assertFalse($tools->isValidIsoDateTime(""));
    }
    public function testFailsWhenTimeZoneIsGivenAsZ(): void
    {
        $tools = new DateTools();
        $this->assertFalse($tools->isValidIsoDateTime("2019-12-12T12:13:14Z"));
    }
}
