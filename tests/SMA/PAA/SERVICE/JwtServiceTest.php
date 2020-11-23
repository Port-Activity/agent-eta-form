<?php

namespace SMA\PAA\RESULTPOSTER;

use PHPUnit\Framework\TestCase;

use SMA\PAA\FAKECURL\FakeCurlRequest;
use SMA\PAA\AGENT\ApiConfig;
use SMA\PAA\SERVICE\JwtService;

final class JwtServiceTest extends TestCase
{
    private function privateKey()
    {
        return file_get_contents(__DIR__ . "/test-key.pem");
    }
    private function publicKey()
    {
        return file_get_contents(__DIR__ . "/test-key.pub");
    }
    private function service($time = null)
    {
        return new JwtService($this->privateKey(), $this->publicKey(), $time);
    }
    public function testCreatingToken(): void
    {
        $time = 1577743188;
        $service = $this->service($time);
        $this->assertEquals(file_get_contents(__DIR__ . "/res.txt"), $service->encode(["foo" => "bar"], 60));
    }
    public function testDecodingToken(): void
    {
        $service = $this->service(time());
        $this->assertEquals(
            ["foo" => "bar"],
            $service->decodeAndVerifyValidity(
                $service->encode(["foo" => "bar"], 60)
            )
        );
    }
    /**
     * @expectedException Firebase\JWT\ExpiredException
     * @expectedExceptionMessage Expired token
     */
    public function testDecodingTokenFailsWhenTokenIsExpired(): void
    {
        $service = $this->service(time() - 60);
        $this->assertEquals(
            ["foo" => "bar"],
            $service->decodeAndVerifyValidity(
                $service->encode(["foo" => "bar"], 30)
            )
        );
    }
    /**
     * @expectedException Firebase\JWT\BeforeValidException
     * @expectedExceptionMessageRegExp /^Cannot handle token prior to/
     */
    public function testDecodingTokenFailsWhenTokenIsNotYetValid(): void
    {
        $service = $this->service(time() + 60);
        $this->assertEquals(
            ["foo" => "bar"],
            $service->decodeAndVerifyValidity(
                $service->encode(["foo" => "bar"], 30)
            )
        );
    }
}
