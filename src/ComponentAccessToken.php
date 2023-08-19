<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/7/25 21:53
 * Email: sgenmi@gmail.com
 */

namespace Weida\WeixinOpenPlatform;

use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Weida\WeixinCore\Contract\AccessTokenInterface;
use Weida\WeixinCore\Contract\HttpClientInterface;
use Weida\WeixinCore\Contract\VerifyTicketInterface;

class ComponentAccessToken implements AccessTokenInterface
{
    private string $appId='';
    private string $secret='';
    private ?CacheInterface $cache=null;
    private ?HttpClientInterface $httpClient=null;
    private string $cacheKey='';
    private VerifyTicketInterface $verifyTicket;
    //这里可以直传一个Account实例，为了兼容，暂这样
    public function __construct(
        string $appId, string $secret, VerifyTicketInterface $verifyTicket,
        ?CacheInterface $cache=null, ?HttpClientInterface $httpClient=null
    )
    {
        $this->appId = $appId;
        $this->secret = $secret;
        $this->verifyTicket = $verifyTicket;
        $this->cache = $cache;
        $this->httpClient = $httpClient;
    }

    public function getToken(bool $isRefresh = false): string
    {
        if(!$isRefresh){
            $token = $this->cache->get($this->getCacheKey());
            if (!empty($token)) {
                return $token;
            }
        }
        return $this->refresh();
    }

    /**
     * @param string $accessToken
     * @return $this
     * @author Weida
     */
    public function setToken(string $accessToken): static
    {
        return $this;
    }

    protected function refresh(){
        $apiUrl = '/component/api_component_token';
        $params=[
            'json' => [
                'component_appid' => $this->appId,
                'component_appsecret' => $this->secret,
                'component_verify_ticket'=>$this->verifyTicket->getTicket()
            ],
        ];

        $resp = $this->httpClient->request('POST', $apiUrl,$params);
        if($resp->getStatusCode()!=200){
            throw new RuntimeException('Request component_access_token exception');
        }
        $arr = json_decode($resp->getBody()->getContents(),true);

        if (empty($arr['component_access_token'])) {
            throw new RuntimeException('Failed to get component_access_token: ' . json_encode($arr, JSON_UNESCAPED_UNICODE));
        }
        $this->cache->set($this->getCacheKey(), $arr['component_access_token'], intval($arr['expires_in']));
        return $arr['component_access_token'];
    }

    public function expiresTime(): int
    {
        return  $this->cache->ttl($this->getCacheKey());
    }

    /**
     * @return array
     * @author Weida
     */
    public function getParams(): array
    {
        return [
            'component_appid'=>$this->appId,
            'component_appsecret'=>$this->secret,
            'component_verify_ticket'=>$this->verifyTicket->getTicket(),
            'cache'=>$this->cache,
            'httpClient'=>$this->httpClient,
        ];
    }

    public function getCacheKey(): string
    {
        if(empty($this->cacheKey)){
            $this->cacheKey = sprintf("open_platform:component_access_token:%s:%s",$this->appId);
        }
        return $this->cacheKey;
    }

    public function setCacheKey(string $key): static
    {
        $this->cacheKey = $key;
        return $this;
    }

}
