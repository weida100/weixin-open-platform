<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/7/25 23:20
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Weida\WeixinCore\Contract\HttpClientInterface;
use Throwable;

class Authorize implements AuthorizeInterface
{
    private string $appId;
    private ?HttpClientInterface $httpClient=null;

    public function __construct(string $appId,?HttpClientInterface $httpClient=null)
    {
        $this->appId = $appId;
        $this->httpClient = $httpClient;
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
        return $arr;
    }

    /**
     * @param string $authorizationCode
     * @return array
     * @throws Throwable
     * @author Weida
     */
    public function getAuthorization(string $authorizationCode):array{
        $resp = $this->httpClient->request('POST', '/cgi-bin/component/api_query_auth', [
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
            throw new RuntimeException('Failed to get authorization_info: '.json_encode($response, JSON_UNESCAPED_UNICODE));
        }
        return $arr;
    }



}
