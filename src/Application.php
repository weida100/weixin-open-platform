<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/7/20 00:09
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Weida\WeixinCore\Contract\AccessTokenInterface;
use Weida\WeixinCore\Contract\AuthorizeInterface;
use Weida\WeixinCore\Contract\ResponseInterface;
use Weida\WeixinCore\AbstractApplication;
use Weida\WeixinCore\Contract\VerifyTicketInterface;
use Closure;

class Application extends AbstractApplication
{
    //开放平台
    protected string $appType='openPlatform';
    protected ?VerifyTicketInterface $verifyTicket=null;
    protected AuthorizeInterface $authorize;

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
    protected function getResponseAfter(){
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
        if($this->authorize){
            $this->authorize = new Authorize(
                $this->getAccount()->getAppId(),
                $this->getClient()
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
     * @param string $authorizationCode
     * @return array
     * @author Weida
     */
    public function getAuthorization(string $authorizationCode):array{
        return $this->getAuthorize()->getAuthorization($authorizationCode);
    }

}
