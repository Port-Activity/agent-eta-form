<?php
namespace SMA\PAA\TOOL;

use PHPUnit\Framework\TestCase;

final class ImoToolsTest extends TestCase
{
    public function testImoCheckWithCorrectImoDoesntFail(): void
    {
        $tools = new ImoTools();
        $this->assertTrue($tools->isValidImo("1234567"));
    }
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage IMO number is not valid (automatic check calculation)
     */
    public function testImoCheckFailsWithWrongImo(): void
    {
        $tools = new ImoTools();
        $this->assertTrue($tools->isValidImo("1234568"));
    }
    /**
     * @expectedException TypeError
     * @expectedExceptionMessageRegExp /must be of the type int, string given/
     */
    public function testImoCheckFailsWithLetters(): void
    {
        $tools = new ImoTools();
        $this->assertTrue($tools->isValidImo("abcdef7"));
    }
}
