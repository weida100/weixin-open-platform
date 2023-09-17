<?php
declare(strict_types=1);

/**
 * Author: Weida
 * Date: 2023/7/24 21:22
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Psr\Http\Message\ResponseInterface;
use Weida\WeixinCore\AbstractResponse;
use Weida\WeixinCore\Contract\MessageInterface;
use Weida\WeixinCore\Message as CoreMessage;
use Weida\WeixinOfficialAccount\Message\Message;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Psr\Http\Message\MessageInterface as PsrMessageInterface;


class Response extends AbstractResponse
{
    /**
     * @param callable|string|array|object $callback
     * @return $this
     * @author Weida
     */
    public function handleAuthorized(callable|string|array|object $callback):static{
        $this->addEventListener(CoreMessage::OPEN_AUTHORIZED,$callback);
        return $this;
    }

    /**
     * @param callable|string|array|object $callback
     * @return $this
     * @author Weida
     */
    public function handleAuthorizeUpdated(callable|string|array|object $callback):static{
        $this->addEventListener(CoreMessage::OPEN_UPDATEAUTHORIZED,$callback);
        return $this;
    }

    /**
     * @param callable|string|array|object $callback
     * @return $this
     * @author Weida
     */
    public function handleUnauthorized(callable|string|array|object $callback):static{
        $this->addEventListener(CoreMessage::OPEN_UNAUTHORIZED,$callback);
        return $this;
    }

    /**
     * @return PsrMessageInterface|ResponseInterface
     * @author Weida
     */
    public function response(): ResponseInterface
    {
        $resp = new Psr7Response(200,[],'success');
        if (!empty($this->params['echostr'])) {
            return $resp->withBody($this->createBody($this->params['echostr']));
        }
        $message = $this->getDecryptedMessage();
        $response = $this->middleware->handler($this,$message);
        if(empty($response)){
            return $resp;
        }
        if(is_string($response) || is_numeric($response)){
            $response = Message::Text((string)$response);
        }elseif (is_array($response)){
            if(!empty($response['msgtype']) || !empty($response['MsgType'])){
                $response = new Message($response);
            }
        }
        if ($response instanceof MessageInterface){
            $resp =$resp ->withHeader('Content-Type', 'application/xml;charset=utf-8');
            if(!($response instanceof Message)){
                $response = new Message($response->getAttributes());
            }
            $content = $response->toXmlReply($message,$this->encryptor);
            $resp = $resp->withBody($this->createBody($content));
        }
        return $resp;
    }



}
