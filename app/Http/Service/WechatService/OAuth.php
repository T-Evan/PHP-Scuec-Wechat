<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 10/13/18
 * Time: 18:06
 */

namespace App\Http\Service\WechatService;


class OAuth
{
    const SCOPE_BASE        = 'snsapi_base';
    const SCOPE_USERINFO    = 'snsapi_userinfo';

    protected $targetUrl;

    protected $callbackUrl;

    protected $state;

    protected $scope;

    protected static $OAUTH_URL = 'https://open.weixin.qq.com/connect/oauth2/authorize?'
        .'appid=%s&redirect_uri=%s&response_type=code&scope=%s&state=%s#wechat_redirect';

    public function __construct($callbackUrl = '',
                                $state = '',
                                $scope = self::SCOPE_BASE)
    {
        $this->callbackUrl = $callbackUrl;
        $this->scope = $scope;
        $this->state = $state;
    }

    public function getRedirectUrl()
    {
        $this->targetUrl = sprintf(self::$OAUTH_URL,
            env('WECHAT_OFFICIAL_ACCOUNT_APPID'),
            urlencode($this->callbackUrl),
            $this->scope,
            $this->state);
        return $this->targetUrl;
    }

    /**
     * @param string $callbackUrl
     * @return OAuth
     */
    public function setCallbackUrl(string $callbackUrl)
    {
        $this->callbackUrl = $callbackUrl;
        return $this;
    }

    /**
     * @param string $state
     * @return OAuth
     */
    public function setState(string $state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @param string $scope
     * @return OAuth
     */
    public function setScope(string $scope)
    {
        $this->scope = $scope;
        return $this;
    }
}