<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\PhysicalExperiment\LabInfoSpider;
use App\Models\StudentInfo;
use Tests\TestCase;

class LabInfoTest extends TestCase
{
    /**
     * @return array
     */
    public function userDataProvider()
    {
        return [
            ['oULq3utesttesttesttesttest12']
        ];
    }

    /**
     * @dataProvider userDataProvider
     * @param string $openid
     * @return void
     */
    public function testGetLabCookie(string $openid)
    {
        $studentInfo = StudentInfo::where('openid', $openid)->first();
        $labInfoRetriever = new LabInfoSpider($studentInfo);
        $result = $labInfoRetriever->getCookie();
        self::assertEquals($result['status'], 200);
    }

    /**
     * @dataProvider userDataProvider
     * @param string $openid
     * @throws \App\Http\Controllers\Api\AccountManager\Exceptions\AccountNotBoundException
     * @throws \App\Http\Controllers\Api\AccountManager\Exceptions\AccountValidationFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testGetRawTable(string $openid)
    {
        $studentInfo = StudentInfo::where('openid', $openid)->first();
        $labInfoRetriever = new LabInfoSpider($studentInfo);
        $result = $labInfoRetriever->getRawTable();
//        var_dump($result);
        self::assertEquals($result['status'], 200);
    }

    /**
     * @dataProvider userDataProvider
     * @param string $openid
     */
    public function testGetMessage(string $openid)
    {
        $studentInfo = StudentInfo::where('openid', $openid)->first();
        $labInfoRetriever = new LabInfoSpider($studentInfo);
        $result = $labInfoRetriever->getMessage();
        var_dump($result);
        self::assertEquals($result['status'], 200);
    }
}
