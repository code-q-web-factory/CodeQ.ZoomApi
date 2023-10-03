<?php

namespace CodeQ\ZoomApi\Utility;

use DateTime;
use DateTimeImmutable;

class TimeUtility
{
    /**
     * @throws \Exception
     */
    public static function convertStringOrDateTimeToDateTimeImmutable(string|DateTime $dateTime): DateTimeImmutable
    {
        if (is_string($dateTime)) {
            return new DateTimeImmutable($dateTime);
        } else {
            return DateTimeImmutable::createFromMutable($dateTime);
        }
    }

    /**
     * @param DateTimeImmutable $from
     * @param DateTimeImmutable $to
     *
     * @return bool
     */
    public static function dateDifferenceIsBiggerThanOneMonth(DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        $dateDifference = $from->diff($to);
        $differenceInMonths = $dateDifference->y * 12 + $dateDifference->m;
        return $differenceInMonths > 0;
    }
}
