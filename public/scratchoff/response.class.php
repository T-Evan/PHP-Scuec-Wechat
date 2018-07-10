<?php
/**
 * This class is for the unity of API
 * User: itsl
 * Date: 18-5-30
 * Time: 下午1:45
 */

namespace Zixunminda\Scratchoff;

require_once __DIR__."/../vendor/autoload.php";

class Response
{
    const STATUS_SUCCESS = '200';
    const STATUS_FAIL_NETWORK = '404';
    const STATUS_FAIL_PARAM_INVALID = '-1000';
    const STATUS_FAIL_UNKNOWN = '2333';

    /**
     * Response when fail
     * @param string $msg
     * @param string $code
     * @return string
     */
    public static function fail(
        string $msg = '',
        string $code = self::STATUS_FAIL_UNKNOWN
    ) {
        return json_encode(array(
            'status' => $code,
            'msg' => $msg
        ));
    }

    /**
     * Response when success
     * @param array $data
     * @return string
     */
    public static function success(array $data = array())
    {
        $response = array('status' => self::STATUS_SUCCESS);
        foreach ($data as $key => $val) {
            $response[$key] = $val;
        }
        return json_encode($response);
    }
}
