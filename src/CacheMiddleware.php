<?php

    declare(strict_types = 1);

    namespace Coco\simplePageDownloader;

    use GuzzleHttp\Exception\RequestException;
    use GuzzleHttp\Psr7\Response;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;

    class CacheMiddleware
    {
        private            $readCacheFunction;
        private            $writeCacheFunction;
        private Downloader $downloader;

        public function __construct(callable $readCacheFunction, callable $writeCacheFunction, Downloader $downloader)
        {
            $this->readCacheFunction  = $readCacheFunction;
            $this->writeCacheFunction = $writeCacheFunction;
            $this->downloader         = $downloader;
        }

        public function __invoke(callable $handler)
        {
            return function(RequestInterface $request, array $options) use ($handler) {
                // 获取请求的URL
                $url = (string)$request->getUri();

                // 通过cacheFunction读取缓存
                $cachedResponse = ($this->readCacheFunction)($url);

                if (is_array($cachedResponse))
                {
                    $cacheContents = $cachedResponse;

                    $contents = $cacheContents['contents'];
                    $code     = $cacheContents['code'];
                    $method   = $cacheContents['method'];
                    $url      = $cacheContents['url'];

                    return \GuzzleHttp\Promise\Create::promiseFor(new Response($code, [], $contents));
                }

                $onFulfilledCallback = function(ResponseInterface $response) use ($url) {

                    // 这里可以设置缓存逻辑，比如存储响应以便下次使用
                    ($this->writeCacheFunction)($url, $response);

                    return $response;
                };

                $onRejectedCallback = function($reason) use ($url) {
                    $contents = $reason->getMessage();

                    if ($reason instanceof RequestException)
                    {
                        // 如果失败，记录失败的原因
                        $response = new Response($reason->getCode(), [], $contents);

                        // 这里可以设置缓存逻辑，比如存储响应以便下次使用
                        ($this->writeCacheFunction)($url, $response);
                    }

                    return \GuzzleHttp\Promise\Create::rejectionFor($reason);
                };

                $this->downloader->logInfo('请求：' . $url);

                // 缓存不存在，继续发送请求
                return $handler($request, $options)->then($onFulfilledCallback, $onRejectedCallback);
            };
        }
    }
