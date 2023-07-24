<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/7/20 00:09
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Weida\WeixinCore\Contract\ResponseInterface;
use Weida\WeixinCore\AbstractApplication;
use Weida\WeixinCore\Contract\VerifyTicketInterface;
use Closure;

class Application extends AbstractApplication
{
    //开放平台
    protected string $appType='openPlatform';
    protected ?VerifyTicketInterface $verifyTicket=null;

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

}
