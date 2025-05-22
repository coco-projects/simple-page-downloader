<?php

    declare(strict_types = 1);

    namespace Coco\simplePageDownloader;

    use Coco\logger\Logger;
    use Coco\magicAccess\MagicMethod;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\RequestException;
    use GuzzleHttp\HandlerStack;
    use GuzzleHttp\Middleware;
    use GuzzleHttp\Psr7\Response;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;

    use GuzzleHttp\Pool;
    use GuzzleHttp\Psr7\Request;

    class Downloader
    {
        use MagicMethod;
        use Logger;

        public static array $clientConfig = [];

        public ?Client $client              = null;
        public int     $concurrency         = 5;
        public string  $url                 = '';
        public array   $urls                = [];
        public array   $settings            = [];
        public array   $rawHeader           = [];
        public array   $cacheCallback       = [];
        public bool    $enableCache         = false;
        public string  $cachePath           = './downloadCache/';
        public string  $method              = 'get';
        public bool    $isByCache           = false;
        public         $successCallback     = null;
        public         $errorCallback       = null;
        public         $clientErrorCallback = null;
        public         $onDoneCallback      = null;
        public int     $retryTimes          = 5;

        protected static string $redisHost     = '127.0.0.1';
        protected static string $redisPassword = '';
        protected static int    $redisPort     = 6379;
        protected static int    $redisDb       = 14;

        protected static string $logNamespace   = 'download-log';
        protected static bool   $enableEchoLog  = false;
        protected static bool   $enableRedisLog = false;

        public static function initClientConfig(array $clientConfig): void
        {
            static::$clientConfig = $clientConfig;
        }

        public static function initLogger(string $logNamespace, bool $enableEchoLog = false, bool $enableRedisLog = false): void
        {
            static::$logNamespace   = $logNamespace;
            static::$enableEchoLog  = $enableEchoLog;
            static::$enableRedisLog = $enableRedisLog;
        }

        public static function setRedis(string $redisHost = '127.0.0.1', int $redisPort = 6379, string $password = '', int $db = 10): void
        {
            static::$redisHost     = $redisHost;
            static::$redisPort     = $redisPort;
            static::$redisPassword = $password;
            static::$redisDb       = $db;
        }

        public static function ins(): static
        {
            return new static();
        }

        protected function __construct()
        {
            $this->client = new Client(static::$clientConfig);

            $this->setStandardLogger(static::$logNamespace);

            if (static::$enableRedisLog)
            {
                $this->addRedisHandler(redisHost: static::$redisHost, redisPort: static::$redisPort, password: static::$redisPassword, db: static::$redisDb, logName: static::$logNamespace, callback: static::getStandardFormatter());
            }

            if (static::$enableEchoLog)
            {
                $this->addStdoutHandler(static::getStandardFormatter());
            }
        }

        public function baseCacheStrategy(): static
        {
            $this->addCacheCallback(function(string $contents, Downloader $_this, ResponseInterface $response) {

                $statusCode = $response->getStatusCode();
                $method     = $_this->getMethod();

                return (strtolower($method) == 'get') && in_array($statusCode, [
                        200,
                        404,
                    ]);
            });

            return $this;
        }

        public function setRetryTimes(int $retryTimes): static
        {
            $this->retryTimes = $retryTimes;

            return $this;
        }

        public function setConcurrency(int $concurrency): static
        {
            $this->concurrency = $concurrency;

            return $this;
        }

        public function addCacheCallback(callable $cacheCallback): static
        {
            $this->cacheCallback[] = $cacheCallback;

            return $this;
        }

        public function setCachePath(string $cachePath): static
        {
            $this->cachePath = rtrim($cachePath, '\/\\') . '/';

            return $this;
        }

        public function getIsByCache(): bool
        {
            return $this->isByCache;
        }

        public function setMethod(string $method): static
        {
            $this->method = $method;

            return $this;
        }

        public function getMethod(): string
        {
            return $this->method;
        }

        public function setRawHeader(string $rawHeader): static
        {
            $headers = Utils::parseHeaders($rawHeader);
            unset($headers['host']);

            $this->rawHeader = $headers;

            return $this;
        }

        public function setSuccessCallback($successCallback): static
        {
            $this->successCallback = $successCallback;

            return $this;
        }

        public function setOnDoneCallback($onDoneCallback): static
        {
            $this->onDoneCallback = $onDoneCallback;

            return $this;
        }

        public function setClientErrorCallback($clientErrorCallback): static
        {
            $this->clientErrorCallback = $clientErrorCallback;

            return $this;
        }

        public function setErrorCallback($errorCallback): static
        {
            $this->errorCallback = $errorCallback;

            return $this;
        }

        public function setClient($client): static
        {
            $this->client = $client;

            return $this;
        }

        public function setEnableCache(bool $enableCache): static
        {
            $this->enableCache = $enableCache;

            return $this;
        }

        public function getClient(): Client
        {
            return $this->client;
        }

        public function setUrl(string $url): static
        {
            $this->url = $url;

            return $this;
        }

        public function addUrls(array $urls): static
        {
            $this->urls = array_merge($this->urls, $urls);

            return $this;
        }

        public function setSettings(array $settings): static
        {
            $this->settings = $settings;

            return $this;
        }

        /*-----------------------------------------------------------------------------------*/

        public function sendRequest(): void
        {
            $cacheMiddleware = new CacheMiddleware($this->readCacheFunction(), $this->writeCacheFunction(), $this);

            $stack = HandlerStack::create();
            $stack->push($cacheMiddleware);

            $this->settings['handler'] = $stack;
            if (count($this->rawHeader))
            {
                $this->settings[\GuzzleHttp\RequestOptions::HEADERS] = $this->rawHeader;
            }

            $promise = $this->client->requestAsync($this->method, $this->url, $this->settings);

            $onFulfilledCallback = function(ResponseInterface $response) {
                $contents = (string)$response->getBody();
                $this->onSuccess($contents, $response, 1);
            };

            $onRejectedCallback = function(RequestException $e) {
                $this->onError($e, 1);
            };

            $promise->then($onFulfilledCallback, $onRejectedCallback);

            try
            {
                $promise->wait();
            }
            catch (\Exception $e)
            {
//                $msg = "wait Exception:[{$e->getMessage()}]";
//                $this->logInfo($msg);
            }

            $this->url = '';
            if (is_callable($this->onDoneCallback))
            {
                call_user_func_array($this->onDoneCallback, [
                    $this,
                ]);
            }
        }

        public function sendBatchRequest(): void
        {
            if (count($this->rawHeader))
            {
                $this->settings[\GuzzleHttp\RequestOptions::HEADERS] = $this->rawHeader;
            }

            $cacheMiddleware = new CacheMiddleware($this->readCacheFunction(), $this->writeCacheFunction(), $this);

            $stack = HandlerStack::create();
            $stack->push($cacheMiddleware);

            $stack->push(Middleware::retry(function($retries, RequestInterface $request, $response, $exception) {

                $isRetry = $retries < $this->retryTimes && !is_null($exception);
                if ($isRetry)
                {
                    $msg = implode('', [
                        '重试：' . $retries + 1 . ',',
                        '地址：' . (string)$request->getUri() . ',',
                        '错误：' . $exception->getMessage(),
                    ]);
                    $this->logInfo($msg);
                }

                return $isRetry;

            }, function($retries) {
                // 延迟时间：指数退避，每次延迟时间加倍
                return pow(2, $retries);
            }));

            $this->settings['handler'] = $stack;

            if (count($this->urls))
            {
                $requests = function() {
                    foreach ($this->urls as $k => $uri)
                    {
                        yield new Request($this->method, $uri);
                    }
                };

                $pool = new Pool($this->client, $requests(), [
                    'handler'     => $stack,
                    'options'     => $this->settings,
                    'concurrency' => $this->concurrency,
                    'fulfilled'   => function(Response $response, $index) {
                        $this->url = $this->urls[$index];
                        $contents  = (string)$response->getBody();

                        $this->onSuccess($contents, $response, $index);
                    },
                    'rejected'    => function(\Throwable $reason, $index) {
                        $this->url = $this->urls[$index];
                        $contents  = $reason->getMessage();

                        if ($reason instanceof RequestException)
                        {
                            // 如果失败，记录失败的原因
                            $this->onError($reason, $index);
                        }
                        else
                        {
                            // 处理其他异常情况
                            $this->onError(new RequestException($contents, new \GuzzleHttp\Psr7\Request($this->method, new \GuzzleHttp\Psr7\Uri($this->url))), $index);
                        }
                    },
                ]);

                $promise = $pool->promise();

                try
                {
                    $promise->wait();
                }
                catch (\Exception $e)
                {
                    $msg = "wait Exception:[{$e->getMessage()}]";
                    $this->logInfo($msg);
                }
            }

            $this->urls = [];
            if (is_callable($this->onDoneCallback))
            {
                call_user_func_array($this->onDoneCallback, [
                    $this,
                ]);
            }
        }

        /*-----------------------------------------------------------------------------------*/

        protected function onError(\Exception $e, int $index): void
        {
            if (is_callable($this->errorCallback))
            {
                $msg = "errorCallback:[{$this->url}]";
                $this->logInfo($msg);

                call_user_func_array($this->errorCallback, [
                    $e,
                    $this,
                    $index,
                ]);
            }
        }

        protected function onSuccess(string $contents, ResponseInterface $response, int $index): void
        {
            if (is_callable($this->successCallback))
            {
                $msg = "successCallback:[{$this->url}]";
                $this->logInfo($msg);

                call_user_func_array($this->successCallback, [
                    $contents,
                    $this,
                    $response,
                    $index,
                ]);
            }
        }

        protected function isNeedCache($contents, $response): bool
        {
            $isNeedCache = false;
            foreach ($this->cacheCallback as $k => $v)
            {
                $res = call_user_func_array($v, [
                    $contents,
                    $this,
                    $response,
                ]);

                if ($res)
                {
                    $isNeedCache = true;
                    break;
                }
            }

            return $isNeedCache;
        }

        protected static function makeCacheFileNameByUrl(string $url): string
        {
            $hash = md5($url);

            return implode('', [
                substr($hash, 0, 2) . DIRECTORY_SEPARATOR,
                substr($hash, 2, 2) . DIRECTORY_SEPARATOR,
                substr($hash, 4, 2) . DIRECTORY_SEPARATOR,
                substr($hash, 6, 2) . DIRECTORY_SEPARATOR,
                $hash . '.json',
            ]);
        }

        protected function makeCacheFileFullPathNameByUrl(string $fileName): string
        {
            $filePath = rtrim($this->cachePath, '\/\\') . '/' . $fileName;
            is_dir(dirname($filePath)) or mkdir(dirname($filePath), 777, true);

            return $filePath;
        }

        protected function readCacheFunction(): \Closure
        {
            return function(string $url) {

                $fileName = static::makeCacheFileNameByUrl($url);
                $filePath = $this->makeCacheFileFullPathNameByUrl($fileName);

                if (is_file($filePath))
                {
                    if ($this->enableCache)
                    {
                        $msg = "读缓存:[{$url}]";
                        $this->logInfo($msg);

                        return static::getCache($filePath);
                    }
                    else
                    {
                        @unlink($filePath);
                    }
                }

                return null;
            };

        }

        protected function writeCacheFunction(): \Closure
        {
            return function(string $url, $response) {

                $contents = (string)$response->getBody();

                $code = $response->getStatusCode();

                $fileName = static::makeCacheFileNameByUrl($url);
                $filePath = $this->makeCacheFileFullPathNameByUrl($fileName);

                if ($this->enableCache && $this->isNeedCache($contents, $response))
                {
                    $msg = "写入缓存:[{$code}][{$url}]";
                    $this->logInfo($msg);

                    static::putCache($filePath, $this->method, $code, $url, $contents);
                }
            };
        }

        protected static function putCache(string $filename, string $method, int $code, string $url, string $contents): bool|int
        {
            $data = [
                "code"     => $code,
                "method"   => $method,
                "url"      => $url,
                "contents" => base64_encode(gzencode($contents)),
            ];

            return file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        protected static function getCache(string $filename)
        {
            $contents = json_decode(file_get_contents($filename), true);

            $contents['contents'] = gzdecode(base64_decode($contents['contents']));

            return $contents;
        }
    }
