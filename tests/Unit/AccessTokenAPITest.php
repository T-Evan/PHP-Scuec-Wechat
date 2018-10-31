<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccessTokenAPITest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testGetToken()
    {
        $ts = time();
        $appid = 'ZTk5MTA5MmY3ZjQ1';
        $token = 'NmVmOWJkYjhhODI5YmJiMDUxZWRjZTcy';
        $sign = sha1($appid.$ts.$token);
        $response = $this->call(
            'GET',
            '/api/students/account/accessToken',
            [
                'appid' => $appid,
                'ts'    => $ts,
                'sign'  => $sign,
            ]
        )->content();
        var_dump($response);
    }
}
