<?php
declare(strict_types=1);

/**
 * Author: Weida
 * Date: 2023/7/24 21:22
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Weida\WeixinCore\Message;

class Response extends \Weida\WeixinCore\Response
{
    /**
     * @param callable|string|array|object $callback
     * @return $this
     * @author Weida
     */
    public function handleAuthorized(callable|string|array|object $callback):static{
        $this->addEventListener(Message::OPEN_AUTHORIZED,$callback);
        return $this;
    }

    /**
     * @param callable|string|array|object $callback
     * @return $this
     * @author Weida
     */
    public function handleAuthorizeUpdated(callable|string|array|object $callback):static{
        $this->addEventListener(Message::OPEN_UPDATEAUTHORIZED,$callback);
        return $this;
    }

    /**
     * @param callable|string|array|object $callback
     * @return $this
     * @author Weida
     */
    public function handleUnauthorized(callable|string|array|object $callback):static{
        $this->addEventListener(Message::OPEN_UNAUTHORIZED,$callback);
        return $this;
    }
}
