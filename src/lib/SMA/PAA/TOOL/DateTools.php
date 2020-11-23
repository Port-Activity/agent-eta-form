<?php
namespace SMA\PAA\TOOL;

use DateTime;
use DateTimeZone;

class DateTools
{
    public function now()
    {
        return $this->isoDateFromTimeZone("", "UTC");
    }
    public function isoDateToTimeZone(string $dateTime, string $timeZone, string $format = null): string
    {
        if ($format === null) {
            $format = "Y-m-d\TH:i:sP";
        }
        $time = new DateTime($dateTime, new DateTimeZone("UTC"));
        if (isset($time)) {
            $time->setTimeZone(new DateTimeZone($timeZone));
            return $time->format($format);
        }
        return null;
    }
    public function isoDateFromTimeZone(string $dateTime, string $timeZone, string $format = null): string
    {
        if ($format === null) {
            $format = "Y-m-d\TH:i:sP";
        }
        $time = new DateTime($dateTime, new DateTimeZone($timeZone));
        if (isset($time)) {
            $time->setTimeZone(new DateTimeZone("UTC"));
            return $time->format($format);
        }
        return null;
    }
    public function isValidIsoDateTime(string $date)
    {
        $dateTime = DateTime::createFromFormat(DateTime::ATOM, $date);
        return $dateTime instanceof DateTime && $dateTime->format(DateTime::ATOM) === $date;
    }
    public function isValidIsoDateTimeWithoutTimeZone(string $date)
    {
        $date = $date . "+00:00";
        return $this->isValidIsoDateTime($date);
    }
    public function isValidInterval(string $interval): bool
    {
        $parts = explode(":", $interval);
        if (count($parts) !== 2) {
            return false;
        }
        if (!is_numeric($parts[0]) || !is_numeric($parts[1])) {
            return false;
        }
        if ($parts[0] < 0 || $parts[1] < 0) {
            return false;
        }
        if ($parts[1] > 59) {
            return false;
        }

        return true;
    }
    public function intervalInMinutes(string $interval): int
    {
        $res = 0;
        $parts = explode(":", $interval);
        $res = 60 * $parts[0];
        $res += $parts[1];

        return $res;
    }
}
