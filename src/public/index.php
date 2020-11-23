<?php
namespace SMA\PAA\AGENT;

require_once __DIR__ . "/../lib/init.php";

use Exception;
use SMA\PAA\TOOL\DateTools;
use SMA\PAA\TOOL\EmailTools;
use SMA\PAA\TOOL\ImoTools;
use SMA\PAA\CURL\CurlRequest;
use SMA\PAA\AINO\AinoClient;
use SMA\PAA\RESULTPOSTER\ResultPoster;

$timeZone = getenv("ETA_FORM_TIMEZONE");
$secretCode = getenv("ETA_FORM_CODE");
$apiKey = getenv("API_KEY");
$baseUrl = getenv("API_URL");
$slotReservationsUrl = $baseUrl . "agent/rest/slot-reservations";
$portName = getenv("TARGET_PORT_NAME");
$ainoKey = getenv("AINO_API_KEY");
$ainoTimestamp = gmdate("Y-m-d\TH:i:s\Z");
$aino = null;
if ($ainoKey) {
    $toApplication = parse_url($slotReservationsUrl, PHP_URL_HOST);
    $aino = new AinoClient($ainoKey, "JIT Web Form 1", $toApplication);
}

$code = isset($_POST['code']) ? $_POST['code'] : null;
$email = isset($_POST['email']) ? $_POST['email'] : null;
$imo = isset($_POST['imo']) ? $_POST['imo'] : null;
$imo_confirm = isset($_POST['imo_confirm']) ? $_POST['imo_confirm'] : null;
$vessel_name = isset($_POST['vessel_name']) ? $_POST['vessel_name'] : null;
$loa = isset($_POST['loa']) ? $_POST['loa'] : null;
$beam = isset($_POST['beam']) ? $_POST['beam'] : null;
$draft = isset($_POST['draft']) ? $_POST['draft'] : null;
$eta = isset($_POST['eta']) ? $_POST['eta'] : null;
$laytimeh = isset($_POST['laytimeh']) ? $_POST['laytimeh'] : null;
$laytimem = isset($_POST['laytimem']) ? $_POST['laytimem'] : null;
$form_sent = isset($_POST['form_sent']) ? $_POST['form_sent'] : null;

$errors = [];
$laytime = null;

if ($form_sent === "true") {
    if ($code !== $secretCode) {
        $errors["code"] = true;
    }

    $tools = new EmailTools();
    if (!$tools->isValid($email)) {
        $errors["email"] = true;
    }

    $imoTools = new ImoTools();
    try {
        $imoTools->isValidImo((int)$imo);
    } catch (\Exception $e) {
        $errors["imo"] = true;
    }

    if (empty($vessel_name)) {
        $errors["vessel_name"] = true;
    }

    if ($imo !== $imo_confirm) {
        $errors["imo_confirm"] = true;
    }

    if (!is_numeric($loa)) {
        $errors["loa"] = true;
    }

    if (!is_numeric($beam)) {
        $errors["beam"] = true;
    }

    if (!is_numeric($draft)) {
        $errors["draft"] = true;
    }

    $dateTools = new DateTools();

    if ($eta !== null && !$dateTools->isValidIsoDateTimeWithoutTimeZone($eta)) {
        $errors["eta"] = true;
    }

    if ($laytimeh !== null && (!ctype_digit($laytimeh) || intval($laytimeh) < 0 || intval($laytimeh) > 99999)) {
        $errors["laytimeh"] = true;
    }

    if ($laytimem !== null && (!ctype_digit($laytimem) || intval($laytimem) < 0 || intval($laytimem) > 59)) {
        $errors["laytimem"] = true;
    }

    if (!(isset($errors["laytimeh"]) || isset($errors["laytimem"]))) {
        $laytime = $laytimeh . ":" . $laytimem;

        if (!$dateTools->isValidInterval($laytime)) {
            $errors["laytime"] = true;
        }
    }
}
?>

<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Just-In-Time Web Form</title>
  <link rel="stylesheet" type="text/css" href="css/styles.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>

<body>
  <div id="wrapper">
    <header>
      <h1>Just-In-Time Web Form</h1>
      <h2>Send ETA to outer port area of <?php echo $portName?> to start Just-In-Time request process</h2>
    </header>

<?php
if ($form_sent === "true" && sizeof($errors) === 0 && $code === $secretCode) {
    $apiParameters = [
        "code",
        "email",
        "imo",
        "vessel_name",
        "loa",
        "beam",
        "draft",
        "eta",
        "laytime"
    ];
    $apiConfig = new ApiConfig($apiKey, $slotReservationsUrl, $apiParameters);

    $etaResult = [
        "code" => (int)$code,
        "email" => $email,
        "imo" => (int)$imo,
        "vessel_name" => $vessel_name,
        "loa" => $loa,
        "beam" => $beam,
        "draft" => $draft,
        "eta" => $dateTools->isoDateFromTimeZone($eta, $timeZone),
        "laytime" => $laytime,
    ];

    $resultPoster = new ResultPoster(new CurlRequest());
    $dateTools = new DateTools();

    $ainoEtaFlowId = $resultPoster->resultChecksum($apiConfig, $etaResult);

    try {
        $resultPoster->postResult($apiConfig, $etaResult);
        echo "<p class=\"success\">Information successfully sent to {$portName}. Please check your e-mail.</p>";
        echo "</div></body></html>";
        if (isset($aino)) {
            $aino->succeeded(
                $ainoTimestamp,
                "JIT Web Form 1 succeeded",
                "Post",
                "slotrequest",
                ["imo" => (int)$imo],
                [],
                $ainoEtaFlowId
            );
        }
        exit(0);
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
                "JIT Web Form 1 failed",
                "Post",
                "slotrequest",
                ["imo" => (int)$imo],
                [],
                $ainoEtaFlowId
            );
        }
    }
}
?>

  <p>Please fill this form and press SEND. You will receive an e-mail to the address given below.</p>
    <form autocomplete="off" method="post">
      <p>
        <label for="code">Verification code (available from port)</label>
        <input type="text" name="code" value="<?php echo $code?>" />
        <span><?php echo isset($errors["code"]) ? "Invalid code!" : "" ?></span>
      </p>
      <p>
        <label for="email">E-mail address of person in charge</label>
        <input type="text" name="email" value="<?php echo $email?>" />
        <span><?php echo isset($errors["email"]) ? "Invalid email!" : "" ?></span>
      </p>
      <p>
        <label for="imo">IMO number of vessel</label>
        <input type="text" name="imo" value="<?php echo $imo?>" />
        <span><?php echo isset($errors["imo"]) ? "Invalid IMO number!" : "" ?></span>
      </p>
      <p>
        <label for="imo_confirm">Confirm IMO number of vessel</label>
        <input type="text" name="imo_confirm" value="<?php echo $imo_confirm?>" />
        <span><?php echo isset($errors["imo_confirm"]) ? "IMO numbers do not match!" : "" ?></span>
      </p>
      <fieldset>
        <legend>Vessel information</legend>
        <p>
          <label for="vessel_name">Vessel name</label>
          <input type="text" name="vessel_name" value="<?php echo $vessel_name?>" />
          <span><?php echo isset($errors["vessel_name"]) ? "Name is empty!" : "" ?></span>
        </p>
        <p>
          <label for="loa">LOA</label>
          <input type="text" name="loa" value="<?php echo $loa?>" />
          <span><?php echo isset($errors["loa"]) ? "Not a numerical value!" : "" ?></span>
        </p>
        <p>
          <label for="beam">Beam</label>
          <input type="text" name="beam" value="<?php echo $beam?>" />
          <span><?php echo isset($errors["beam"]) ? "Not a numerical value!" : "" ?></span>
        </p>
        <p>
          <label for="draft">Draft</label>
          <input type="text" name="draft" value="<?php echo $draft?>" />
          <span><?php echo isset($errors["draft"]) ? "Not a numerical value!" : "" ?></span>
        </p>
      </fieldset>
      <p>
        <label for="eta">ETA to outer port area (Sweden local time, YYYY-MM-DD HH:mm)</label>
        <input type="text" id="eta" name="eta" readonly value="<?php echo $eta?>" />
        <span><?php echo isset($errors["eta"]) ? "Invalid ETA!" : ""?></span>
      </p>
      <p>
        <label for="laytimeh">Estimated laytime (HH:mm)</label>
        <input class="duration" maxlength="5" size="5" type="text" name="laytimeh" value="<?php echo $laytimeh?>" />
        :
        <input class="duration" maxlength="2" size="2" type="text" name="laytimem" value="<?php echo $laytimem?>" />
        <span>
            <?php echo isset($errors["laytimeh"]) ? "Invalid laytime hours!" : "" ?>
            <?php echo isset($errors["laytimem"]) ? "Invalid laytime minutes!" : "" ?>
        </span>
      </p>
      <input type="hidden" name="form_sent" value="true">
      <button>Send</button>
    </form>
    <script>
      flatpickr("#eta", {
        enableTime: true,
        time_24hr: true,
        dateFormat: "Y-m-d\\TH:i:S",
        altInput: true,
        altFormat: "Y-m-d H:i",
        minDate: "today",
        locale: {
          firstDayOfWeek: 1
        }
      });
    </script>
  </div>
</body>
</html>
