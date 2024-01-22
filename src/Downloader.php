<?php

    declare(strict_types = 1);

    namespace Coco\simplePageDownloader;

    use Coco\magicAccess\MagicMethod;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\ClientException;
    use GuzzleHttp\Exception\RequestException;
    use Psr\Http\Message\ResponseInterface;

    class Downloader
    {
        use MagicMethod;

        public ?Client $client              = null;
        public string  $url                 = '';
        public array   $settings            = [];
        public $successCallback     = null;
        public $errorCallback       = null;
        public $clientErrorCallback = null;
        public bool    $enableCache         = false;
        public string  $cachePath           = './downloadCache/';
        public string  $method              = 'get';

        public static function ins(): static
        {
            return new static(...func_get_args());
        }

        public function __construct($client)
        {
            $this->client = $client;
        }

        public function setMethod(string $method): static
        {
            $this->method = $method;

            return $this;
        }

        public function setSuccessCallback($successCallback): static
        {
            $this->successCallback = $successCallback;

            return $this;
        }

        public function setClientErrorCallback($clientErrorCallback): static
        {
            $this->clientErrorCallback = $clientErrorCallback;

            return $this;
        }

        public static function gbkToUtf8($contents): string
        {
//        return iconv("GBK", "UTF-8", $contents);
            return mb_convert_encoding($contents, 'UTF-8', 'GBK');
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

        public function setSettings(array $settings): static
        {
            $this->settings = $settings;

            return $this;
        }

        public function sendRequest(): void
        {
            $hash = md5($this->url);
            $fileName = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '.txt';

            $filePath = rtrim($this->cachePath, '\/\\') . '/' . $fileName;
            is_dir(dirname($filePath)) or mkdir(dirname($filePath), 777, true);

            if ($this->enableCache && is_file($filePath)) {
                $contents = file_get_contents($filePath);

                call_user_func_array($this->successCallback, [
                    $contents,
                    $this,
                    304
                ]);

                return;
            }

            $promise = $this->client->getAsync($this->url, $this->settings);

            $promise->then(function (ResponseInterface $result) use ($filePath) {
                $contents = (string)$result->getBody();

                if ($this->enableCache) {
                    file_put_contents($filePath, $contents);
                }

                call_user_func_array($this->successCallback, [
                    $contents,
                    $this,
                    $result->getStatusCode(),
                ]);
            }, function (RequestException $e) {
                call_user_func_array($this->errorCallback, [
                    $e,
                    $this,
                ]);
            });

            try {
                $promise->wait();
            } catch (\Exception $e) {
                call_user_func_array($this->clientErrorCallback, [
                    $e,
                    $this,
                ]);
            }
        }
    }
