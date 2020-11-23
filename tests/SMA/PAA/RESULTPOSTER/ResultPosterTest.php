<?php

namespace SMA\PAA\RESULTPOSTER;

use PHPUnit\Framework\TestCase;

use SMA\PAA\FAKECURL\FakeCurlRequest;
use SMA\PAA\AGENT\ApiConfig;

final class ResultPosterTest extends TestCase
{
    public function testConstructor(): void
    {
        # Comply with PSR-1 2.3 Side Effects Rule
        require_once(__DIR__."/../FAKECURL/FakeCurlRequest.php");
        $resultPoster = new ResultPoster(new FakeCurlRequest());
        $this->assertEquals(isset($resultPoster), true);
    }

    public function testPostResultAllGood(): void
    {
        $curlRequest = new FakeCurlRequest();
        $curlRequest->getInfoReturn = ["http_code" => 200];
        $resultPoster = new ResultPoster($curlRequest);
        $curlRequest->executeReturn = json_encode(["result" => "OK"]);
        $resultPoster->postResult(
            new ApiConfig(
                "apikey",
                "http://url/foo",
                [
                    "time",
                    "external_id",
                    "checkpoint",
                    "direction",
                    "front_license_plates",
                    "rear_license_plates",
                    "containers"
                ]
            ),
            json_decode(file_get_contents(__DIR__."/ValidResultData.json"), true)
        );
        $this->assertEquals(
            $curlRequest->optArray,
            json_decode(file_get_contents(__DIR__."/ValidCurlOptData.json"), true)
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage missing from input array.
     */
    public function testPostResultMissingApiParameter(): void
    {
        $resultPoster = new ResultPoster(new FakeCurlRequest());
        $resultPoster->postResult(
            new ApiConfig("apikey", "http://url/foo", ["par"]),
            json_decode(file_get_contents(__DIR__."/MissingDirectionResultData.json"), true)
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid parameter
     */
    public function testPostResultExtraParameter(): void
    {
        $resultPoster = new ResultPoster(new FakeCurlRequest());
        $resultPoster->postResult(
            new ApiConfig("apikey", "http://url/foo", []),
            json_decode(file_get_contents(__DIR__."/ExtraParameterResultData.json"), true)
        );
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Error occured during curl exec
     */
    public function testPostResultsCurlResponseFalse(): void
    {
        $curlRequest = new FakeCurlRequest();
        $resultPoster = new ResultPoster($curlRequest);
        $curlRequest->executeReturn = false;
        $resultPoster->postResult(
            new ApiConfig(
                "apikey",
                "http://url/foo",
                [
                    "time",
                    "external_id",
                    "checkpoint",
                    "direction",
                    "front_license_plates",
                    "rear_license_plates",
                    "containers"
                ]
            ),
            json_decode(file_get_contents(__DIR__."/ValidResultData.json"), true)
        );
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Error response from server
     */
    public function testPostResultsErrorSet(): void
    {
        $curlRequest = new FakeCurlRequest();
        $resultPoster = new ResultPoster($curlRequest);
        $curlRequest->executeReturn = json_encode(["error" => "Dummy error"]);
        $resultPoster->postResult(
            new ApiConfig(
                "apikey",
                "http://url/foo",
                [
                    "time",
                    "external_id",
                    "checkpoint",
                    "direction",
                    "front_license_plates",
                    "rear_license_plates",
                    "containers"
                ]
            ),
            json_decode(file_get_contents(__DIR__."/ValidResultData.json"), true)
        );
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Error result from server
     */
    public function testPostResultsResultError(): void
    {
        $curlRequest = new FakeCurlRequest();
        $resultPoster = new ResultPoster($curlRequest);
        $curlRequest->executeReturn = json_encode(["result" => "ERROR"]);
        $resultPoster->postResult(
            new ApiConfig(
                "apikey",
                "http://url/foo",
                [
                    "time",
                    "external_id",
                    "checkpoint",
                    "direction",
                    "front_license_plates",
                    "rear_license_plates",
                    "containers"
                ]
            ),
            json_decode(file_get_contents(__DIR__."/ValidResultData.json"), true)
        );
    }
}
