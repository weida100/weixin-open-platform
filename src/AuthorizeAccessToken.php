<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/8/19 22:17
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Psr\SimpleCache\CacheInterface;
use Weida\WeixinCore\Contract\AccessTokenInterface;
use Weida\WeixinCore\Contract\AuthorizeInterface;
use Weida\WeixinCore\Contract\HttpClientInterface;

class AuthorizeAccessToken implements AccessTokenInterface
{
    private string $authorizerAppId='';
    private string $authorizerRefreshToken='';
    private ?CacheInterface $cache=null;
    private ?AuthorizeInterface $authorize=null;
    private string $cacheKey='';
    //设置了accessToken
    private string $accessToken = "";

    public function __construct(
        string $authorizerAppId, string $authorizerRefreshToken, ?CacheInterface $cache=null,
        ?AuthorizeInterface $authorize=null
    )
    {
        $this->authorizerAppId = $authorizerAppId;
        $this->authorizerRefreshToken = $authorizerRefreshToken;
        $this->cache = $cache;
        $this->authorize = $authorize;
    }

    public function getToken(bool $isRefresh = false): string
    {
        if(!empty($this->accessToken)){
            return $this->accessToken;
        }
        if(!$isRefresh){
            $token = $this->cache->get($this->getCacheKey());
            if (!empty($token)) {
                return $token;
            }
        }
        $arr =  $this->authorize->refreshAuthorizerToken($this->authorizerAppId,$this->authorizerRefreshToken);
        return $arr['authorizer_access_token'];
    }

    /**
     * @param string $accessToken
     * @return $this
     * @author Weida
     */
    public function setToken(string $accessToken):static{
        $this->accessToken = $accessToken;
        return $this;
    }

    public function expiresTime(): int
    {
        return  $this->cache->ttl($this->getCacheKey());
    }

    public function getParams(): array
    {
        return [
            'authorizerAppId'=>$this->authorizerAppId,
            'authorizerRefreshToken'=>$this->authorizerRefreshToken,
            'cache'=>$this->cache,
            'authorize'=>$this->authorize
        ];
    }

    /**
     * @return string
     * @author Weida
     */
    public function getCacheKey(): string
    {
        if(empty($this->cacheKey)){
            $this->cacheKey = sprintf("open_platform:access_token:%s",$this->authorizerAppId);
        }
        return $this->cacheKey;
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

}
