<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/7/20 00:09
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Psr\SimpleCache\CacheInterface;
use Weida\WeixinCore\Contract\AccessTokenInterface;
use Weida\WeixinCore\Contract\AuthorizeInterface;
use Weida\WeixinCore\Contract\ResponseInterface;
use Weida\WeixinCore\AbstractApplication;
use Weida\WeixinCore\Contract\VerifyTicketInterface;
use Closure;
use Weida\WeixinOfficialAccount\Application as OfficialAccountApplication;
use Weida\WeixinMiniProgram\Application as MiniProgramApplication;

class Application extends AbstractApplication
{
    //开放平台
    protected string $appType='openPlatform';
    protected ?VerifyTicketInterface $verifyTicket=null;
    protected ?AuthorizeInterface $authorize=null;

    /**
     * @return VerifyTicketInterface
     * @author Weida
     */
    public function getVerifyTicket():VerifyTicketInterface {
        if (empty($this->verifyTicket)){
            return new VerifyTicket(
                $this->getAccount()->getAppId(),
                $this->getCache()
            );
        }
        return $this->verifyTicket;
    }

    /**
     * @param VerifyTicketInterface $verifyTicket
     * @return $this
     * @author Weida
     */
    public function setVerifyTicket(VerifyTicketInterface $verifyTicket):static {
        $this->verifyTicket = $verifyTicket;
        return $this;
    }

    /**
     * @return ResponseInterface
     * @author Weida
     */
    public function getResponse(): ResponseInterface
    {
        if(empty($this->response)){
            $this->response = new Response(
                $this->getRequest(),
                $this->getEncryptor(),
                $this->getAppType()
            );
        }
        //特殊情况下，优先加入处理事件，拿到实现后，通过复写getResponseAfter 加进去
        $this->getResponseAfter();
        return $this->response;
    }

    /**
     * @return void
     * @author Weida
     */
    protected function getResponseAfter(): void
    {
        if ($this->response instanceof ResponseInterface) {
            $this->response->with( function ($message, Closure $next): mixed {
                if(isset($message['InfoType']) && $message['InfoType']=='component_verify_ticket'){
                    $this->getVerifyTicket()->setTicket($message['ComponentVerifyTicket']??'');
                }
                return $next($message);
            });
        }
    }

    /**
     * @return AccessTokenInterface
     * @author Weida
     */
    public function getComponentAccessToken():AccessTokenInterface{
        if(empty($this->accessToken)){
            $this->accessToken = new ComponentAccessToken(
                $this->getAccount()->getAppId(),
                $this->getAccount()->getSecret(),
                $this->getVerifyTicket(),
                $this->getCache(),
                $this->getHttpClient()
            );
        }
        return $this->accessToken;
    }

    /**
     * @param AccessTokenInterface $componentAccessToken
     * @return $this
     * @author Weida
     */
    public function setComponentAccessToken(AccessTokenInterface $componentAccessToken):static {
        $this->accessToken = $componentAccessToken;
        return $this;
    }

    /**
     * 复写AbstractApplication中 getAccessToken
     * @return AccessTokenInterface
     * @author Weida
     */
    public function getAccessToken(): AccessTokenInterface
    {
       return $this->getComponentAccessToken();
    }

    /**
     * @return AuthorizeInterface
     * @author Weida
     */
    public function getAuthorize():AuthorizeInterface{
        if(empty($this->authorize)){
            $this->authorize = new Authorize(
                $this->getAccount()->getAppId(),
                $this->getHttpClient(),
                $this->getComponentAccessToken()
            );
        }
        return $this->authorize;
    }

    /**
     * @param AuthorizeInterface $authorize
     * @return $this
     * @author Weida
     */
    public function setAuthorize(AuthorizeInterface $authorize):static{
        $this->authorize = $authorize;
        return $this;
    }

    /**
     * 为了兼容,可以直接拿getAuthorize完成业务功能
     * @param string $redirect_uri
     * @return string
     * @author Weida
     */
    public function createPreAuthorizationUrl(string $redirect_uri):string{
        return $this->getAuthorize()->createPreAuthorizationUrl($redirect_uri);
    }

    /**
     * @param string $authorizerAppId
     * @param string $authorizerRefreshToken
     * @return array
     * @author Weida
     */
    public function refreshAuthorizerToken(string $authorizerAppId, string $authorizerRefreshToken):array{
        return $this->getAuthorize()->refreshAuthorizerToken($authorizerAppId,$authorizerRefreshToken);
    }

    /**
     * 真实使用这个方法并不多，采用匿名类返回
     * 授权的返回的code
     * @param string $authorizationCode
     * @return object
     * @author Weida
     */
    public function getAuthorization(string $authorizationCode): object
    {
        $arr= $this->getAuthorize()->getAuthorization($authorizationCode);
        return new class($arr,$this->getCache(),$this->getAuthorize()){
            private array $arr=[];
            private CacheInterface $cache;
            private AuthorizeInterface $authorize;
            public function __construct($arr,CacheInterface $cache,AuthorizeInterface $authorize){
                $this->arr = $arr;
                $this->cache = $cache;
                $this->authorize = $authorize;
            }
            public function getAppId():string{
                return (string)($this->arr['authorization_info']['authorizer_appid']??'');
            }
            public function getAccessToken():AccessTokenInterface{
                return new AuthorizeAccessToken(
                    $this->getAppId(),$this->getRefreshToken(),$this->cache,$this->authorize
                );
            }
            public function getRefreshToken():string {
                return (string) $this->attributes['authorization_info']['authorizer_refresh_token'] ?? '';
            }
            public function getAttributes():array{
                return $this->arr;
            }
        };
    }

    /**
     * 网页/app 登录授权
     * @return void
     * @author Weida
     */
    public function getOAuth(){

    }

    /**
     * @param AccessTokenInterface $accessToken
     * @param array $config
     * @return OfficialAccountApplication
     * @author Weida
     */
    public function getOfficialAccount(AccessTokenInterface $accessToken,array $config=[]):OfficialAccountApplication {
        $app =  new OfficialAccountApplication(
            array_merge($config,[
                'app_id'=>$accessToken->getParams()['authorizerAppId'],
            ]));
        //这里重新设置 AccessTokenInterface，传实例，全局监控accessToken过期 可以自动重新获取
        //如果直接给直实的accessToken,我们在使用过程中 发现会过期(发大量模板消息时)。还要自已重新拿一次新的token，在重发,
        //如果是实例，则自动获取
        $app->setEncryptor($this->getEncryptor());
        $app->setAccessToken($accessToken);
        $app->setCache($this->getCache());
        $app->setHttpClient($this->getHttpClient());
        return $app;
    }

    /**
     * @param string $appId
     * @param string $refreshToken
     * @param array $config
     * @return OfficialAccountApplication
     * @author Weida
     */
    public function getOfficialAccountWithRefreshToken(string $appId, string $refreshToken, array $config = []):OfficialAccountApplication {
        return $this->getOfficialAccount(
            new AuthorizeAccessToken($appId,$refreshToken,$this->getCache(),$this->getAuthorize()),
            $config
        );
    }

    /**
     * @param string $appId
     * @param string $accessToken
     * @param array $config
     * @return OfficialAccountApplication
     * @author Weida
     */
    public function getOfficialAccountWithAccessToken(string $appId, string $accessToken, array $config = []): OfficialAccountApplication {
        $authorizeAccessToken = new AuthorizeAccessToken($appId,'',$this->getCache(),$this->getAuthorize());
        $authorizeAccessToken->setToken($accessToken);
        return $this->getOfficialAccount( $authorizeAccessToken, $config);
    }

    /**
     * @param AccessTokenInterface $accessToken
     * @param array $config
     * @return MiniProgramApplication
     * @author Weida
     */
    public function getMiniApp(AccessTokenInterface $accessToken,array $config=[]):MiniProgramApplication{
        $app =  new MiniProgramApplication(
            array_merge($config,[
                'app_id'=>$accessToken->getParams()['authorizerAppId'],
            ]));
        $app->setEncryptor($this->getEncryptor());
        $app->setAccessToken($accessToken);
        $app->setCache($this->getCache());
        $app->setHttpClient($this->getHttpClient());
        return $app;
    }

    /**
     * @param string $appId
     * @param string $accessToken
     * @param array $config
     * @return MiniProgramApplication
     * @author Weida
     */
    public function getMiniAppWithAccessToken(string $appId, string $accessToken, array $config = []):MiniProgramApplication{
        $authorizeAccessToken = new AuthorizeAccessToken($appId,'',$this->getCache(),$this->getAuthorize());
        $authorizeAccessToken->setToken($accessToken);
        return $this->getMiniApp( $authorizeAccessToken, $config);
    }

    /**
     * @param string $appId
     * @param string $refreshToken
     * @param array $config
     * @return MiniProgramApplication
     * @author Weida
     */
    public function getMiniAppWithRefreshToken( string $appId, string $refreshToken, array $config = []):MiniProgramApplication {
        return $this->getMiniApp(
            new AuthorizeAccessToken($appId,$refreshToken,$this->getCache(),$this->getAuthorize()),
            $config
        );
    }



}
