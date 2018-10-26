<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\PhysicalExperiment\LabInfoSpider;
use Tests\TestCase;

class LabInfoTest extends TestCase
{
    /**
     * @return array
     */
    public function userDataProvider()
    {
        return [
            ['201621094040', '.dl151713']
        ];
    }

    /**
     * @dataProvider userDataProvider
     * @param string $username
     * @param string $password
     * @return void
     */
    public function testGetLabCookie(string $username, string $password)
    {
        $labInfoRetriever = new LabInfoSpider($username, $password);
        $result = $labInfoRetriever->getCookie();
        var_dump($result);
        self::assertEquals($result['status'], 200);
    }

    /**
     * @dataProvider userDataProvider
     * @param string $username
     * @param string $password
     */
    public function testGetRawTable(string $username, string $password)
    {
        $labInfoRetriever = new LabInfoSpider($username, $password);
        $result = $labInfoRetriever->getRawTable();
        var_dump($result);
        self::assertEquals($result['status'], 200);
    }

    /**
     * @dataProvider userDataProvider
     * @param string $username
     * @param string $password
     */
    public function testGetMessage(string $username, string $password)
    {
        $labInfoRetriever = new LabInfoSpider($username, $password);
        $result = $labInfoRetriever->getMessage();
        var_dump($result);
        self::assertEquals($result['status'], 200);
    }
}
