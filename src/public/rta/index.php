<?php
namespace SMA\PAA\AGENT;

require_once __DIR__ . "/../../lib/init.php";

use Exception;
use DateTimeImmutable;
use SMA\PAA\CURL\Api;
use SMA\PAA\CURL\CurlRequest;
use SMA\PAA\RESULTPOSTER\ResultPoster;
use SMA\PAA\SERVICE\JwtService;
use SMA\PAA\TOOL\EmailTools;
use SMA\PAA\TOOL\ImoTools;
use SMA\PAA\TOOL\DateTools;
use SMA\PAA\AINO\AinoClient;

const STATUS_REQUESTED = "requested";
const STATUS_NO_NOMINATION = "no_nomination";
const STATUS_NO_FREE_SLOT = "no_free_slot";
const STATUS_OFFERED = "offered";
const STATUS_ACCEPTED = "accepted";
const STATUS_UPDATED = "updated";
const STATUS_CANCELLED_BY_VESSEL = "cancelled_by_vessel";
const STATUS_CANCELLED_BY_PORT = "cancelled_by_port";
const STATUS_COMPLETED = "completed";

$dateTools = new DateTools();

$timeZone = getenv("ETA_FORM_TIMEZONE");
$apiKey = getenv("API_KEY");
$baseUrl = getenv("API_URL");
$slotReservationsUrl = $baseUrl . "agent/rest/slot-reservations";
$apiPublicKey = json_decode(getenv("API_PUBLIC_KEY_JSON"));
$mainFormUrl = getenv("MAIN_FORM_URL");
$rtaFormUrl = getenv("RTA_FORM_URL");
$portName = getenv("TARGET_PORT_NAME");
$ainoKey = getenv("AINO_API_KEY");
$ainoTimestamp = gmdate("Y-m-d\TH:i:s\Z");
$aino = null;
if ($ainoKey) {
    $toApplication = parse_url($slotReservationsUrl, PHP_URL_HOST);
    $aino = new AinoClient($ainoKey, "JIT Web Form 1", "WebForm");
}

$token = isset($_GET['token']) ? $_GET['token'] : null;
$link = $rtaFormUrl . "?token=" . $token;

$button_action = isset($_POST['button_action']) ? $_POST['button_action'] : null;
$laytimeh = isset($_POST['laytimeh']) ? $_POST['laytimeh'] : null;
$laytimem = isset($_POST['laytimem']) ? $_POST['laytimem'] : null;
$jit_eta = isset($_POST['jit_eta']) ? $_POST['jit_eta'] : null;
$form_sent = isset($_POST['form_sent']) ? $_POST['form_sent'] : null;
$post_rta_window_start = isset($_POST['post_rta_window_start']) ? $_POST['post_rta_window_start'] : null;
$post_rta_window_end = isset($_POST['post_rta_window_end']) ? $_POST['post_rta_window_end'] : null;
$post_max_laytime = isset($_POST['post_max_laytime']) ? $_POST['post_max_laytime'] : null;

$errors = "";
$laytime = null;

if ($form_sent === "true") {
    if ($jit_eta !== null && !$dateTools->isValidIsoDateTimeWithoutTimeZone($jit_eta)) {
        $errors .= "JIT ETA format was not correct. ";
    }

    $laytimeErrors = false;
    if ($laytimeh !== null && (!ctype_digit($laytimeh) || intval($laytimeh) < 0 || intval($laytimeh) > 99999)) {
        $laytimeErrors = true;
        $errors .= "Laytime hours format was not correct. ";
    }

    if ($laytimem !== null && (!ctype_digit($laytimem) || intval($laytimem) < 0 || intval($laytimem) > 59)) {
        $laytimeErrors = true;
        $errors .= "Laytime minutes format was not correct. ";
    }

    if (!$laytimeErrors) {
        $laytime = $laytimeh . ":" . $laytimem;
    }

    if ($laytime !== null && !$dateTools->isValidInterval($laytime)) {
        $errors .= "Laytime format was not correct. ";
    }

    $compareStart = new DateTimeImmutable($dateTools->isoDateToTimeZone($post_rta_window_start, $timeZone));
    $compareEnd = new DateTimeImmutable($dateTools->isoDateToTimeZone($post_rta_window_end, $timeZone));
    $compareValue = new DateTimeImmutable($dateTools->isoDateToTimeZone($jit_eta, $timeZone));

    if ($compareValue < $compareStart || $compareValue > $compareEnd) {
        $errors .= "JIT ETA was not within RTA window. ";
    }
}
?>

<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Just-In-Time Web Form</title>
  <link rel="stylesheet" type="text/css" href="../css/styles.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>

<body>
  <div id="wrapper">
    <header>
      <h1>Just-In-Time Web Form</h1>
      <h2>Send JIT ETA to outer port area of <?php echo $portName?> according to given RTA window. 
      Update laytime if needed. Use Cancel to cancel your request. </h2>
    </header>

<?php

$service = new JwtService("", $apiPublicKey);
$tokenData = [];
try {
    $tokenData = $service->decodeAndVerifyValidity($token);
    if (isset($aino)) {
        $aino->succeeded($ainoTimestamp, "JIT Web Form 2 succeeded", "Decode", "token", [], []);
    }
} catch (\Exception $e) {
    echo "<p class=\"fail\">Invalid link. If problem persists, please contact port directly.</p>";
    echo "</div></body></html>";
    if (isset($aino)) {
        $aino->failure($ainoTimestamp, "JIT Web Form 2 failed", "Decode", "token", [], []);
    }
    exit(0);
}

$storedFields = [
    "slot_request_id"
];

$storedData = [];
foreach ($storedFields as $storedField) {
    $storedData[$storedField] = isset($tokenData[$storedField]) ? $tokenData[$storedField] : null;
}

$slotRequestId = (int)$storedData["slot_request_id"];

if ($form_sent === "true" && $errors === "") {
    if ($button_action === "update") {
        $apiParameters = [
          "id",
          "laytime",
          "jit_eta"
        ];
        $apiConfig = new ApiConfig($apiKey, $slotReservationsUrl, $apiParameters);
        $jitEtaResult = [
          "id" => $slotRequestId,
          "laytime" => $laytime,
          "jit_eta" => $dateTools->isoDateFromTimeZone($jit_eta, $timeZone)
        ];

        $resultPoster = new ResultPoster(new CurlRequest());

        $ainoJitEtaFlowId = $resultPoster->resultChecksum($apiConfig, $jitEtaResult);

        try {
            $resultPoster->putResult($apiConfig, $jitEtaResult);
            echo "<p class=\"success\">Information successfully sent to {$portName}.</p>";
            if (isset($aino)) {
                $aino->succeeded(
                    $ainoTimestamp,
                    "JIT Web Form 2 succeeded",
                    "Update",
                    "jit_eta",
                    ["slot_reservation_id" => $slotRequestId],
                    [],
                    $ainoJitEtaFlowId
                );
            }
        } catch (\Exception $e) {
            $log["message"] = $e->getMessage();
            $log["stack"] = $e->getTraceAsString();
            $log["debug_trace"] = debug_backtrace();
            error_log(json_encode($log));
            echo "<p class=\"fail\">Failed to send information to {$portName}. ";
            echo "Please try again in few minutes. If problem persists, please contact port directly.</p>";
            if (isset($aino)) {
                $aino->failure(
                    $ainoTimestamp,
                    "JIT Web Form 2 failed",
                    "Post",
                    "jit_eta",
                    ["slot_reservation_id" => $slotRequestId],
                    [],
                    $ainoJitEtaFlowId
                );
            }
        }
    } elseif ($button_action === "cancel") {
        $api = new Api($baseUrl);
        $res = $api->deleteWithAuthorizationKey(
            "ApiKey " . $apiKey,
            "agent/rest/slot-reservations",
            ["id" => $slotRequestId]
        );

        if ($res === false || $res === null) {
            echo "<p class=\"fail\">Failed to cancel your request. If problem persists, please contact port directly. ";
            echo "Please press cancel again retry.</p>";
            if (isset($aino)) {
                $aino->failure(
                    $ainoTimestamp,
                    "JIT Web Form 2 failed",
                    "Cancel",
                    "slot_request",
                    ["id" => $slotRequestId],
                    []
                );
            }
        }
    }
}

if ($form_sent === "true" && $errors !== "") {
    echo "<p class=\"fail\">Entered data contained following errors: " . $errors;
}

$api = new Api($baseUrl);
$res = $api->getWithAuthorizationKey("ApiKey " . $apiKey, "agent/rest/slot-reservation-by-id?id=" . $slotRequestId);

if ($res === false || $res === null) {
    echo "<p class=\"fail\">Failed to get data from server. If problem persists, please contact port directly. ";
    echo "Please reload the page or use the link below to retry.</p>";
    echo "<a href=\"" . $link . "\">JIT ETA form</a>";
    echo "</div></body></html>";
    if (isset($aino)) {
        $aino->failure(
            $ainoTimestamp,
            "JIT Web Form 2 failed",
            "Fetch",
            "slot_request",
            ["id" => $slotRequestId],
            []
        );
    }
    exit(0);
}

$status = $res["slot_reservation_status"];

// This should not happen
if ($status === STATUS_REQUESTED) {
    echo "<p class=\"fail\">Your ETA information has not yet been processed. ";
    echo "Please wait 1 minute and reload the page or use the link below to retry. ";
    echo "If problem persists, please contact port directly.</p>";
    echo "<a href=\"" . $link . "\">JIT ETA form</a>";
    echo "</div></body></html>";
    exit(0);
}

if ($status === STATUS_NO_NOMINATION) {
    echo "<p class=\"fail\">Cannot find nomination for your vessel for the given ETA. ";
    echo "Please contact your agent and request them to create valid nomination for you. ";
    echo "After valid nomination has been created please reload the page or use the link below to retry.</p>";
    echo "<a href=\"" . $link . "\">JIT ETA form</a>";
    echo "</div></body></html>";
    exit(0);
}

if ($status === STATUS_NO_FREE_SLOT) {
    echo "<p class=\"fail\">Port cannot offer you free slot. ";
    echo "Please wait 1 minute and reload the page or use the link below to retry. ";
    echo "If problem persists, please contact port directly.</p>";
    echo "<a href=\"" . $link . "\">JIT ETA form</a>";
    echo "</div></body></html>";
    exit(0);
}

if ($status === STATUS_CANCELLED_BY_VESSEL) {
    echo "<p class=\"fail\">Your JIT ETA request has been cancelled by your request. ";
    echo "If you wish to request a new JIT ETA please re-enter your arrival information using the link below.</p>";
    echo "<a href=\"" . $mainFormUrl . "\">ETA form</a>";
    echo "</div></body></html>";
    exit(0);
}

if ($status === STATUS_CANCELLED_BY_PORT) {
    echo "<p class=\"fail\">Your JIT ETA request has been cancelled by port. ";
    echo "If you wish to request a new JIT ETA please re-enter your arrival information using the link below.</p>";
    echo "<a href=\"" . $mainFormUrl . "\">ETA form</a>";
    echo "</div></body></html>";
    exit(0);
}

if ($status === STATUS_COMPLETED) {
    echo "<p class=\"fail\">This JIT ETA request has been completed and cannot be altered. ";
    echo "If you wish to request a new JIT ETA please enter your arrival information using the link below.</p>";
    echo "<a href=\"" . $mainFormUrl . "\">ETA form</a>";
    echo "</div></body></html>";
    exit(0);
}

if ($status === STATUS_UPDATED) {
    echo "<p class=\"fail\">Port has updated your RTA window and JIT ETA. Please check new values from below form. ";
    echo "If you need to update your JIT ETA or laytime please update the values and press UPDATE. ";
    echo "JIT ETA must be within the given RTA window. ";
    echo "If the new parameters are not acceptable please press CANCEL to cancel your JIT ETA request.</p>";
}

if ($status === STATUS_ACCEPTED) {
    echo "<p class=\"success\">Your JIT ETA has been confirmed. Please check values from below form. ";
    echo "If you need to update your JIT ETA or laytime please update the values and press UPDATE. ";
    echo "JIT ETA must be within the given RTA window. ";
    echo "If parameters are not acceptable please press CANCEL to cancel your JIT ETA request.</p>";
}

$email = $res["email"];
$imo = $res["imo"];
$vessel_name = $res["vessel_name"];
$laytime = $res["laytime"];
$laytimePieces = explode(":", $laytime);
$laytimeh = isset($laytimePieces[0]) ? $laytimePieces[0] : "00";
$laytimem = isset($laytimePieces[1]) ? $laytimePieces[1] : "00";
$max_laytime = $res["max_laytime"];
$rta_window_start = $dateTools->isoDateToTimeZone($res["rta_window_start"], $timeZone, "Y-m-d\TH:i:s");
$rta_window_end = $dateTools->isoDateToTimeZone($res["rta_window_end"], $timeZone, "Y-m-d\TH:i:s");
$formattedRtaWindowStart = $dateTools->isoDateToTimeZone($res["rta_window_start"], $timeZone, "Y-m-d H:i");
$formattedRtaWindowEnd = $dateTools->isoDateToTimeZone($res["rta_window_end"], $timeZone, "Y-m-d H:i");
if (!empty($res["jit_eta"])) {
    $jit_eta = $dateTools->isoDateToTimeZone($res["jit_eta"], $timeZone);
}
?>

    <form autocomplete="off" method="post">
      <p>
          <label for="rta_window_start">RTA window start</label>
          <input type="text" name="rta_window_start" value="<?php echo $formattedRtaWindowStart?>" disabled/>
          <label for="rta_window_end">RTA window end</label>
          <input type="text" name="rta_window_end" value="<?php echo $formattedRtaWindowEnd?>" disabled/>
      </p>
      <p>
        <label for="jit_eta">
          JIT ETA to outer port area according to RTA window (Sweden local time, YYYY-MM-DD HH:mm)
        </label>
        <input type="text" id="jit_eta" name="jit_eta" readonly value="<?php echo $jit_eta?>" />
      </p>
      <p>
        <label for="laytimeh">Estimated laytime (HH:mm)</label>
        <input class="duration" maxlength="5" size="5" type="text" name="laytimeh" value="<?php echo $laytimeh?>" />
        :
        <input class="duration" maxlength="2" size="2" type="text" name="laytimem" value="<?php echo $laytimem?>" />
      </p>
      <fieldset>
        <legend>Vessel information</legend>
        <p>
          <label for="email">E-mail address of person in charge</label>
          <input type="text" name="email" value="<?php echo $email?>" disabled/>
        </p>
        <p>
          <label for="vessel_name">Vessel name</label>
          <input type="text" name="vessel_name" value="<?php echo $vessel_name?>" disabled/>
        </p>
        <p>
          <label for="imo">IMO number of vessel</label>
          <input type="text" name="imo" value="<?php echo $imo?>" disabled/>
        </p>
      </fieldset>
      <input type="hidden" name="form_sent" value="true">
      <input type="hidden" name="post_rta_window_start" value="<?php echo $rta_window_start?>">
      <input type="hidden" name="post_rta_window_end" value="<?php echo $rta_window_end?>">
      <input type="hidden" name="post_max_laytime" value="<?php echo $max_laytime?>">

        <?php
        if ($status === STATUS_OFFERED) {
            echo "<button name=\"button_action\" value=\"update\">Send</button>";
        }
        if ($status === STATUS_ACCEPTED || $status === STATUS_UPDATED) {
            echo "<button name=\"button_action\" value=\"update\">Update</button>";
        }
        ?>

      <button class="button_cancel" name="button_action" value="cancel">Cancel</button>
    </form>
    <script>
      flatpickr("#jit_eta", {
        defaultDate: "<?php echo $jit_eta ?>",
        enableTime: true,
        time_24hr: true,
        dateFormat: "Y-m-d\\TH:i:S",
        altInput: true,
        altFormat: "Y-m-d H:i",
        minDate: "<?php echo $rta_window_start?>",
        maxDate: "<?php echo $rta_window_end?>",
        locale: {
          firstDayOfWeek: 1
        }
      });
    </script>
  </div>
</body>
</html>
