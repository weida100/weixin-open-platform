<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/7/25 21:44
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Weida\WeixinCore\Contract\VerifyTicketInterface;

class VerifyTicket implements VerifyTicketInterface
{
    protected string $appId;
    protected ?CacheInterface $cache=null;
    protected string $cacheKey='';
    public function __construct(string $appId,?CacheInterface $cache=null)
    {
        $this->appId = $appId;
        $this->cache = $cache;
    }

    /**
     * @return string
     * @throws InvalidArgumentException
     * @author Weida
     */
    public function getTicket(): string
    {
        return strval($this->cache->get($this->getCacheKey()));
    }

    /**
     * @param string $ticket
     * @param int $ttl
     * @return $this
     * @throws InvalidArgumentException
     * @author Weida
     */
    public function setTicket(string $ticket, int $ttl = 43000): static
    {
        $this->cache->set($this->getCacheKey(),$ticket,$ttl);
        return $this;
    }

    /**
     * @param string $key
     * @return $this
     * @author Weida
     */
    public function setCacheKey(string $key): static
    {
        $this->cacheKey = $key;
        return $this;
    }

    /**
     * @return string
     * @author Weida
     */
    public function getCacheKey(): string
    {
        if(empty($this->cacheKey)){
            $this->cacheKey = sprintf("open_platform:component_verify_ticket:%s",$this->appId);
        }
        return $this->cacheKey;
    }

}
