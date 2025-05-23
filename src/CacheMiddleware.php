<?php

    declare(strict_types = 1);

    namespace Coco\simplePageDownloader;

    use GuzzleHttp\Exception\RequestException;
    use GuzzleHttp\Promise\Create;
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
                $this->downloader->logInfo('请求：' . $url);
                $this->downloader->timer->start();

                // 通过cacheFunction读取缓存
                $cachedResponse = ($this->readCacheFunction)($request);

                if (is_array($cachedResponse))
                {
                    $cacheContents = $cachedResponse;

                    $contents = $cacheContents['contents'];
                    $code     = $cacheContents['code'];
                    $method   = $cacheContents['method'];
                    $url      = $cacheContents['url'];

                    $this->downloader->logInfo('请求耗时：' . $this->downloader->timer->totalTime() . " S [$url]");

                    return Create::promiseFor(new Response($code, [], $contents));
                }

                $onFulfilledCallback = function(ResponseInterface $response) use ($request, $url) {

                    // 这里可以设置缓存逻辑，比如存储响应以便下次使用
                    ($this->writeCacheFunction)($request, $response);

                    $this->downloader->logInfo('请求耗时：' . $this->downloader->timer->totalTime() . " S [$url]");

                    return $response;
                };

                $onRejectedCallback = function($reason) use ($request, $url) {
                    $contents = $reason->getMessage();

                    if ($reason instanceof RequestException)
                    {
                        // 如果失败，记录失败的原因
                        $response = new Response($reason->getCode(), [], $contents);

                        // 这里可以设置缓存逻辑，比如存储响应以便下次使用
                        ($this->writeCacheFunction)($request, $response);
                    }

                    $this->downloader->logInfo('请求耗时：' . $this->downloader->timer->totalTime() . " S [$url]");

                    return Create::rejectionFor($reason);
                };

                // 缓存不存在，继续发送请求
                $res = $handler($request, $options)->then($onFulfilledCallback, $onRejectedCallback);

                return $res;
            };
        }
    }
