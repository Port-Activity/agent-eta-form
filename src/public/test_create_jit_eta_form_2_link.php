<?php
namespace SMA\PAA\AGENT;

require_once __DIR__ . "/../lib/init.php";

use Exception;
use SMA\PAA\SERVICE\JwtService;
use SMA\PAA\TOOL\DateTools;

// This assumes that you have the API private key in project root with filename private.pem
$privateKey = file_get_contents(__DIR__ . "/../../private.pem");
$rtaFormUrl = getenv("RTA_FORM_URL");

$dateTools = new DateTools();
$now = $dateTools->now();

$jwtService = new JwtService($privateKey, "");
$expiresIn = 6*60*60;
$token = $jwtService->encode(["slot_request_id" => 1], $expiresIn);
$link = $rtaFormUrl . "?token=" . $token;

echo $link;
