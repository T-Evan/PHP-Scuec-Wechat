<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use Dingo\Api\Routing\Router;

$api = app('Dingo\Api\Routing\Router');

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

$api->version('v1', function (\Dingo\Api\Routing\Router $api) {
    $api->group(['prefix' => 'students'], function(\Dingo\Api\Routing\Router $api) {
        $api->post('ssfw', 'App\Http\Controllers\Api\AccountInfoController@judgeAccount');
        $api->get('ssfw/timetable/{openid}', 'App\Http\Controllers\Api\AccountInfoController@tableApi');
        $api->post('ssfw/score/{openid}', 'App\Http\Controllers\Api\AccountInfoController@scoreApi');
        $api->post('lib', 'App\Http\Controllers\Api\LibInfoController@judgeAccount');
        $api->post('lib/booklist', 'App\Http\Controllers\Api\LibInfoController@getBookList');

        $api->post('labAccount', 'App\Http\Controllers\Api\PhysicalExperimentController@labAccount');
        $api->post('lab/verifyState', 'App\Http\Controllers\Api\PhysicalExperimentController@verifyState');

        $api->group(['prefix' => 'account', 'middleware' => 'public_api_auth'], function(Router $api) {
            $api->get('isBind', 'App\Http\Controllers\Api\AccountInfoController@isBind');
        });
    });
});