<?php

    declare(strict_types = 1);

    namespace Coco\simplePageDownloader;

    use Coco\logger\Logger;
    use Coco\magicAccess\MagicMethod;
    use Coco\timer\Timer;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\RequestException;
    use GuzzleHttp\HandlerStack;
    use GuzzleHttp\Middleware;
    use GuzzleHttp\Psr7\Response;
    use Psr\Http\Message\RequestInterface;
    use Psr\Http\Message\ResponseInterface;
    use GuzzleHttp\Pool;

    /**
     * @link https://docs.guzzlephp.org/en/stable/quickstart.html
     */
    class Downloader
    {
        use MagicMethod;
        use Logger;

        public static array $clientConfig = [];

        public ?Client $client              = null;
        public ?Timer  $timer               = null;
        public int     $concurrency         = 5;
        public string  $url                 = '';
        public array   $urls                = [];
        public array   $batchRequests       = [];
        public array   $options             = [];
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
            $this->timer  = new Timer();

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
            $this->addCacheCallback(function(Downloader $_this, RequestInterface $request, ResponseInterface $response) {

                $contents   = (string)$response->getBody();
                $statusCode = $response->getStatusCode();
                $method     = $request->getMethod();

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

        public function addBatchRequest(string $url, $method = 'get', array $options = []): static
        {
            $this->batchRequests[] = [
                "url"     => $url,
                "method"  => $method,
                "options" => $options,
            ];

            return $this;
        }

        public function getRequestInfoByIndex(int $index): ?array
        {
            return $this->batchRequests[$index] ?? null;
        }

        /*-----------------------------------------------------------------------------------*/

        public function send(): void
        {
            if (count($this->rawHeader))
            {
                $this->options[\GuzzleHttp\RequestOptions::HEADERS] = $this->rawHeader;
            }

            $stack = HandlerStack::create();

            $cacheMiddleware = new CacheMiddleware($this->readCacheFunction(), $this->writeCacheFunction(), $this);
            $stack->push($cacheMiddleware);

            $stack->push(Middleware::retry(function($retries, RequestInterface $request, ?ResponseInterface $response, $exception) {

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
                return 1;
            }));

            $this->options['handler'] = $stack;

            if (count($this->batchRequests))
            {
                $requests = function() {
                    foreach ($this->batchRequests as $k => $requestInfo)
                    {
                        yield function() use ($requestInfo) {
                            return $this->client->requestAsync($requestInfo['method'], $requestInfo['url'], static::recursive_array_merge($this->options, $requestInfo['options']));
                        };
                    }
                };

                $pool = new Pool($this->client, $requests(), [
                    'options'     => $this->options,
                    'concurrency' => $this->concurrency,
                    'fulfilled'   => function(Response $response, $index) {

                        $requestInfo = $this->batchRequests[$index];
                        $contents    = (string)$response->getBody();

                        $msg = "successCallback:[{$requestInfo['url']}]";
                        $this->logInfo($msg);

                        $this->onSuccess($contents, $response, $index);
                    },
                    'rejected'    => function(\Throwable $reason, $index) {

                        $requestInfo = $this->batchRequests[$index];
                        $contents    = implode('', [
                            "{$reason->getFile()}",
                            "[{$reason->getLine()}],",
                            $reason->getMessage(),
                        ]);

                        $msg = "errorCallback:[{$requestInfo['url']}]";
                        $this->logInfo($msg);

                        if ($reason instanceof RequestException)
                        {
                            // 如果失败，记录失败的原因
                            $this->onError($reason, $index);
                        }
                        else
                        {
                            // 处理其他异常情况
                            $this->onError(new RequestException($contents, new \GuzzleHttp\Psr7\Request($requestInfo['method'], new \GuzzleHttp\Psr7\Uri($requestInfo['url']))), $index);
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
//                    $msg = "wait Exception:[{$e->getMessage()}]";
//                    $this->logInfo($msg);
                }
            }

            $this->batchRequests = [];
            if (is_callable($this->onDoneCallback))
            {
                $msg = "onDoneCallback";
                $this->logInfo($msg);
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
                call_user_func_array($this->successCallback, [
                    $contents,
                    $this,
                    $response,
                    $index,
                ]);
            }
        }

        protected function isNeedCache($request, $response): bool
        {
            $isNeedCache = false;
            foreach ($this->cacheCallback as $k => $v)
            {
                $res = call_user_func_array($v, [
                    $this,
                    $request,
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
                $hash . '.txt',
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
            return function(RequestInterface $request) {
                $url = (string)$request->getUri();

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
            return function(RequestInterface $request, ResponseInterface $response) {
                $url = (string)$request->getUri();

                $contents = (string)$response->getBody();

                $code = $response->getStatusCode();

                $fileName = static::makeCacheFileNameByUrl($url);
                $filePath = $this->makeCacheFileFullPathNameByUrl($fileName);

                if ($this->enableCache && $this->isNeedCache($request, $response))
                {
                    $msg = "写入缓存:[{$code}][{$url}]";
                    $this->logInfo($msg);

                    static::putCache($filePath, $request->getMethod(), $code, $url, $contents);
                }
            };
        }

        protected static function putCache(string $filename, string $method, int $code, string $url, string $contents): bool|int
        {
            $data = gzencode(json_encode([
                "code"     => $code,
                "method"   => $method,
                "url"      => $url,
                "contents" => base64_encode(gzencode($contents)),
            ], JSON_UNESCAPED_UNICODE));

            return file_put_contents($filename, $data);
        }

        protected static function getCache(string $filename)
        {
            $contents = json_decode(gzdecode(file_get_contents($filename)), true);

            $contents['contents'] = gzdecode(base64_decode($contents['contents']));

            return $contents;
        }

        protected static function recursive_array_merge($array1, $array2)
        {
            foreach ($array2 as $key => $value)
            {
                if (is_array($value) && isset($array1[$key]) && is_array($array1[$key]))
                {
                    // 如果值是数组，并且两个数组都包含这个键，递归合并
                    $array1[$key] = static::recursive_array_merge($array1[$key], $value);
                }
                else
                {
                    // 否则直接覆盖
                    $array1[$key] = $value;
                }
            }

            return $array1;
        }

    }
