<?php

namespace Tests\Unit;

use App\Http\Service\KuaiDiApiService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KuaidiTest extends TestCase
{
    public function danhaoProvider()
    {
        return [
            ['802252429969581554'],
            ['78468786464646'],
        ];
    }

    /**
     * @dataProvider danhaoProvider
     * @return void
     */
    public function testGetKuaidi(string $number)
    {
        $kuaidiHelper = new KuaiDiApiService($number);
        $result = $kuaidiHelper->kuaiDi();
        var_dump($result);
        $this->assertNotEmpty($result);
    }
}
