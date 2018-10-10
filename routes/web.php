<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/test1', 'StudentsController@test');

Route::get('/students/create/{type}/{openid}', 'StudentsController@create')->name('students.create');
Route::post('/students', 'StudentsController@store')->name('students.store');


Route::any('/wechat', 'WeChatController@serve');

Route::get('/wechatArticleRedirect', 'WechatController@redirectWechatArticle');
