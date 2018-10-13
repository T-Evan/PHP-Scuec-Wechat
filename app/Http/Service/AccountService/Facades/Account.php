<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 10/12/18
 * Time: 18:08
 */

namespace App\Http\Service\AccountService\Facades;


use Illuminate\Support\Facades\Facade;

class Account extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Account';
    }
}