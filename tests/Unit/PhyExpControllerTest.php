<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\PhysicalExperimentController;
use Tests\TestCase;

class PhyExpControllerTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testHandler()
    {
        $phyExpController = new PhysicalExperimentController();
        $labInfoMessage = $phyExpController->handle();
        var_dump($labInfoMessage);
        $this->assertNotNull($labInfoMessage);
    }
}
