<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 10/13/18
 * Time: 19:27
 */

namespace App\Http\Service\WechatService;


use App\Http\Service\WechatService\Exceptions\NetworkException;
use App\Http\Service\WechatService\Exceptions\WechatAuthException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class RetrieveUserInfo
{
    protected static $ACCESS_TOKEN_API = 'https://api.weixin.qq.com/sns/oauth2/access_token'
        .'?appid=%s&secret=%s&code=%s&grant_type=authorization_code';

    protected $code;

    protected $accessTokenData = [];
    
    public function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * return is like this:
     * {
     *      "access_token":"ACCESS_TOKEN",
     *      "expires_in":7200,
     *      "refresh_token":"REFRESH_TOKEN",
     *      "openid":"OPENID",
     *      "scope":"SCOPE"
     * }
     *
     * @return array
     * @throws NetworkException
     * @throws WechatAuthException
     */
    public function echAccessToken()
    {
        if ($this->accessTokenData != []) {
            return $this->accessTokenData;
        }
        $targetUrl = sprintf(
            self::$ACCESS_TOKEN_API,
            env('WECHAT_OFFICIAL_ACCOUNT_APPID'),
            env('WECHAT_OFFICIAL_ACCOUNT_SECRET'),
            $this->code
        );
        $client = new Client();
        try {
            $response = $client->request('GET', $targetUrl);
        } catch (GuzzleException $e) {
            Log::error($e->getMessage(), $e->getTrace());
            throw new NetworkException($e->getMessage(), $e->getCode());
        }
        if ($response->getStatusCode() != 200) {
            throw new NetworkException('Network Error', $response->getStatusCode());
        }
        $accessTokenData = json_decode($response->getBody()->getContents(), true);
        if (in_array('errcode', $accessTokenData)) {
            Log::error("wechat access token retrieved failed."
                ." errcode: {$accessTokenData['errcode']}."
                ." errmsg: {$accessTokenData['errmsg']}"
            );
            throw new WechatAuthException($accessTokenData['errmsg'], $accessTokenData['errcode']);
        }
        $this->accessTokenData = $accessTokenData;
        return $this->accessTokenData;
    }


}
