<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/19
 * Time: 2:07
 */

namespace App\Exceptions;

class SchoolInfoException extends \Exception
{
    public $infoArray;
    public function __construct($message = null, $code = 0, $infoArray = null)
    {
        parent::__construct($message, $code);
        $this->infoArray = $infoArray;
    }
}
