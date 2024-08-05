<?php

    declare(strict_types = 1);

    namespace Coco\simplePageDownloader;

    use Coco\magicAccess\MagicMethod;
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\ClientException;
    use GuzzleHttp\Exception\RequestException;
    use GuzzleHttp\Psr7\Response;
    use Psr\Http\Message\ResponseInterface;

class Downloader
{
    use MagicMethod;

    public static array $clientConfig = [];

    public ?Client $client              = null;
    public string  $url                 = '';
    public array   $settings            = [];
    public array   $rawHeader           = [];
    public $successCallback     = null;
    public $errorCallback       = null;
    public $clientErrorCallback = null;
    public bool    $enableCache         = false;
    public string  $cachePath           = './downloadCache/';
    public string  $method              = 'get';
    public bool    $isByCache           = false;

    public static function ins(): static
    {
        return new static();
    }

    public static function init(array $clientConfig): void
    {
        static::$clientConfig = $clientConfig;
    }

    public function __construct()
    {
        $this->client = new Client(static::$clientConfig);
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

    public function setSettings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function sendRequest(): void
    {
        $hash     = md5($this->url);
        $fileName = substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash . '.txt';

        $filePath = rtrim($this->cachePath, '\/\\') . '/' . $fileName;
        is_dir(dirname($filePath)) or mkdir(dirname($filePath), 777, true);

        if ($this->enableCache && is_file($filePath)) {
            $this->isByCache = true;

            $contents = file_get_contents($filePath);

            $response = new Response(304, [], $contents);

            call_user_func_array($this->successCallback, [
                $contents,
                $this,
                $response,
            ]);

            return;
        }

        if (count($this->rawHeader)) {
            $this->settings[\GuzzleHttp\RequestOptions::HEADERS] = $this->rawHeader;
        }

        $promise = $this->client->requestAsync($this->method, $this->url, $this->settings);

        $promise->then(function (ResponseInterface $response) use ($filePath) {
            $contents = (string)$response->getBody();

            if ($this->enableCache) {
                file_put_contents($filePath, $contents);
            }

            if (is_callable($this->successCallback)) {
                call_user_func_array($this->successCallback, [
                    $contents,
                    $this,
                    $response,
                ]);
            }
        }, function (RequestException $e) {
            if (is_callable($this->errorCallback)) {
                call_user_func_array($this->errorCallback, [
                    $e,
                    $this,
                ]);
            }
        });

        try {
            $promise->wait();
        } catch (\Exception $e) {
            if (is_callable($this->errorCallback)) {
                call_user_func_array($this->errorCallback, [
                    $e,
                    $this,
                ]);
            }
        }
    }
}
