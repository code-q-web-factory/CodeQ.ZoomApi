<?php

namespace CodeQ\Tests\Unit;

use CodeQ\ZoomApi\Utility\TimeUtility;
use DateTimeImmutable;
use Exception;
use Neos\Flow\Tests\UnitTestCase;

class TimeUtilityTest extends UnitTestCase
{
    public static function dateDifferenceResultSets(): array
    {
        return [
            [new DateTimeImmutable('1980-01-01'), new DateTimeImmutable('2022-01-01'), true],
            [new DateTimeImmutable('1980-01-01'), new DateTimeImmutable('1980-02-01'), true],
            [new DateTimeImmutable('1980-01-01'), new DateTimeImmutable('1980-01-31'), false],
            [new DateTimeImmutable('1980-01-01'), new DateTimeImmutable('1980-01-03'), false]
        ];
    }

    /**
     * @test
     * @dataProvider dateDifferenceResultSets
     *
     * @param $a
     * @param $b
     * @param $c
     *
     * @return void
     */
    public function dateDifferenceIsBiggerThanOneMonthWillReturnCorrectResult($a, $b, $c): void
    {
        $dateDifferenceIsBiggerThanOneMonth = TimeUtility::dateDifferenceIsBiggerThanOneMonth($a, $b);


        $this->assertEquals($c, $dateDifferenceIsBiggerThanOneMonth);
    }

    public static function validDateTimeInputs(): array
    {
        return [
            [new \DateTime('1980-01-01'), '1980-01-01'],
            ['1980-01-01', '1980-01-01'],
        ];
    }

    /**
     * @test
     * @dataProvider validDateTimeInputs
     */
    public function convertStringOrDateTimeToDateTimeImmutableWillConvertValidInputData($dateTime, $dateTimeString): void
    {
        $dateTimeImmutable = TimeUtility::convertStringOrDateTimeToDateTimeImmutable($dateTime);


        $this->assertEquals($dateTimeString, $dateTimeImmutable->format('Y-m-d'));
        $this->assertInstanceOf(DateTimeImmutable::class, $dateTimeImmutable);
    }

    public static function invalidDateTimeInputs(): array
    {
        return [
            ['I am a teapot.'],
        ];
    }

    /**
     * @test
     * @dataProvider invalidDateTimeInputs
     * @param $dateTime
     *
     * @return void
     */
    public function convertStringOrDateTimeToDateTimeImmutableThrowsExceptionOnInvalidInputData($dateTime): void
    {
        $this->expectException(Exception::class);


        TimeUtility::convertStringOrDateTimeToDateTimeImmutable($dateTime);
    }
}
