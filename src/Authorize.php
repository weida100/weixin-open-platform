<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/7/25 23:20
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use Weida\WeixinCore\Contract\AccessTokenInterface;
use Weida\WeixinCore\Contract\AuthorizeInterface;
use Weida\WeixinCore\Contract\HttpClientInterface;
use Throwable;

class Authorize implements AuthorizeInterface
{
    private string $appId;
    private ?HttpClientInterface $httpClient=null;
    //这里是ComponentAccessToken 非AuthorizeAccessToken
    private ?AccessTokenInterface $accessToken;

    public function __construct(string $appId,?HttpClientInterface $httpClient=null,?AccessTokenInterface $accessToken=null)
    {
        $this->appId = $appId;
        $this->httpClient = $httpClient;
        $this->accessToken = $accessToken;
    }

    /**
     * @param string $redirect_uri
     * @return string
     * @throws Throwable
     * @author Weida
     */
    public function createPreAuthorizationUrl(string $redirect_uri):string{
        $params=[
            'pre_auth_code'=>$this->createPreAuthorizationCode()['pre_auth_code'],
            'redirect_uri'=>$redirect_uri,
            'auth_type'=>3
        ];
        return sprintf('https://mp.weixin.qq.com/cgi-bin/componentloginpage?%s',http_build_query($params));
    }


    /**
     * @return array
     * @throws Throwable
     * @author Weida
     */
    public function createPreAuthorizationCode():array{
        $resp = $this->httpClient->request( 'POST', '/cgi-bin/component/api_create_preauthcode', [
                'json' => [
                    'component_appid' => $this->appId,
                ],
            ]
        );
        if($resp->getStatusCode()!=200){
            throw new RuntimeException('Request api_create_preauthcode exception');
        }
        $arr = json_decode($resp->getBody()->getContents(),true);
        if (empty($arr['pre_auth_code'])) {
            throw new RuntimeException('Failed to get pre_auth_code: '.json_encode($arr, JSON_UNESCAPED_UNICODE));
        }
        return $arr;
    }

    /**
     * @param string $authorizerAppId
     * @param string $authorizerRefreshToken
     * @return array
     * @throws Throwable
     * @author Weida
     */
    public function refreshAuthorizerToken(string $authorizerAppId, string $authorizerRefreshToken):array{
        $resp = $this->httpClient->request( 'POST', '/cgi-bin/component/api_authorizer_token', [
            'json' => [
                'component_appid' => $this->appId,
                'authorizer_appid' => $authorizerAppId,
                'authorizer_refresh_token' => $authorizerRefreshToken,
                ]
            ]
        );
        if($resp->getStatusCode()!=200){
            throw new RuntimeException('Request api_authorizer_token exception');
        }
        $arr = json_decode($resp->getBody()->getContents(),true);
        if (empty($arr['authorizer_access_token'])) {
            throw new RuntimeException('Failed to get authorizer_access_token: '.json_encode($arr, JSON_UNESCAPED_UNICODE));
        }
        $this->setAccessTokenCache($authorizerAppId,$authorizerRefreshToken,strval($arr['authorizer_access_token']),intval($arr['expires_in']));
        return $arr;
    }

    /**
     * @param string $authorizationCode
     * @return array
     * @throws Throwable
     * @author Weida
     */
    public function getAuthorization(string $authorizationCode):array{
        $resp = $this->httpClient->request('POST',
            '/cgi-bin/component/api_query_auth?component_access_token='.$this->accessToken->getToken(),
            [
                'json' => [
                    'component_appid' => $this->appId,
                    'authorization_code' => $authorizationCode,
                    ],
            ]
        );
        if($resp->getStatusCode()!=200){
            throw new RuntimeException('Request api_query_auth exception');
        }
        $arr = json_decode($resp->getBody()->getContents(),true);
        if (empty($arr['authorization_info'])) {
            throw new RuntimeException('Failed to get authorization_info: '.json_encode($arr, JSON_UNESCAPED_UNICODE));
        }
        $this->setAccessTokenCache(
            strval($arr['authorization_info']['authorizer_appid']),
            strval($arr['authorization_info']['authorizer_refresh_token']),
            strval($arr['authorization_info']['authorizer_access_token']),
            intval($arr['authorization_info']['expires_in']),
        );
        return $arr;
    }

    /**
     * @param string $authorizerAppId
     * @param string $authorizerRefreshToken
     * @param string $accessToken
     * @param int $ttl
     * @return void
     * @throws InvalidArgumentException
     * @author Weida
     */
    private function setAccessTokenCache(string $authorizerAppId,string $authorizerRefreshToken,string $accessToken,int $ttl):void{
        //拿cache实例，也可以直接传进来，因为accessToken中有实例，可以直接用
        /**
         * @var CacheInterface $cache
         */
        $cache = $this->accessToken->getParams()['cache'];
        //new AuthorizeAccessToken 主要是为了拿缓存的key
        $authorizeAccessToken = new AuthorizeAccessToken($authorizerAppId, $authorizerRefreshToken);
        $cache->set($authorizeAccessToken->getCacheKey(),$accessToken,$ttl);
    }



}
