<?php

    declare(strict_types = 1);

    namespace Coco\simplePageDownloader;

    use Coco\magicAccess\MagicMethod;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\RequestException;
    use Psr\Http\Message\ResponseInterface;

class Downloader
{
    use MagicMethod;

    public ?Client $client          = null;
    public string  $url             = '';
    public array   $settings        = [];
    public $successCallback = null;
    public $errorCallback   = null;
    public bool    $enableCache     = false;
    public string  $cachePath       = './downloadCache/';
    public string  $method          = 'get';

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

    public static function gbkToUtf8($contents): string
    {
        return iconv("GBK", "UTF-8", $contents);
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
        $fileName = md5($this->url) . '.txt';
        is_dir($this->cachePath) or mkdir($this->cachePath, 777, true);
        $filePath = rtrim($this->cachePath, '\/\\') . '/' . $fileName;

        if ($this->enableCache && is_file($filePath)) {
            $contents = file_get_contents($filePath);

            call_user_func_array($this->successCallback, [
                $contents,
                $this,
            ]);

            return;
        }

        $promise = $this->client->getAsync($this->url, $this->settings);
        $promise->then(function (ResponseInterface $result) use ($filePath) {
            $contents = (string)$result->getBody();

            if ($this->enableCache) {
                is_dir($this->cachePath) or mkdir($this->cachePath, 777, true);
                file_put_contents($filePath, $contents);
            }

            call_user_func_array($this->successCallback, [
                $contents,
                $this,
            ]);
        }, function (RequestException $e) {
            call_user_func_array($this->errorCallback, [
                $e,
                $this,
            ]);
        });

        $promise->wait();
    }
}