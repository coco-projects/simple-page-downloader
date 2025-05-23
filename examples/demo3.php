<?php

    use Coco\simplePageDownloader\Downloader;
    use Coco\simplePageDownloader\Utils;
    use GuzzleHttp\Exception\RequestException;
    use Psr\Http\Message\ResponseInterface;

    require '../vendor/autoload.php';

    Downloader::initClientConfig([
        'timeout' => 10.0,
        'verify'  => false,
        'debug'   => !true,
    ]);
    Downloader::setRedis('127.0.0.1');
    Downloader::initLogger('download_log', true, true);

    $urls = [
        'https://cdn84037269.blazingcdn.net/game/c/2025/04-28/89/620978211267454.jpg',
        'https://cdn84037269.blazingcdn.net/game/c/2025/04-28/b1/620964267941611.jpg',
        'https://cdn84037269.blazingcdn.net/game/c/2025/04-28/2e/620972081218388.jpg',
        'https://cdn84037269.blazingcdn.net/game/c/2025/04-28/37/620974878884094.jpg',
        'https://cdn84037269.blazingcdn.net/game/c/2025/04-28/3a/620968287738121.jpg',
        'https://cdn84037269.blazingcdn.net/game/c/2025/04-28/3a/620968287738121111.jpg',
        'https://cdn84037269.blazingcdn1.net/game/c/2025/04-28/3a/62096828773812123.jpg',
    ];

    $ins = Downloader::ins();

    $ins->setRetryTimes(6);
    $ins->setEnableCache(!false);
    $ins->setCachePath('../downloadCache');
    $ins->baseCacheStrategy();
    $ins->setConcurrency(3);

    foreach ($urls as $k => $url)
    {
        $ins->addBatchRequest($url, 'get', [
            "proxy" => 'http://192.168.0.111:1080',
        ]);
    }

    $ins->setSuccessCallback(function(string $contents, Downloader $_this, ResponseInterface $response, $index) {
        $requestInfo = $_this->getRequestInfoByIndex($index);

        $fileName = '../testData/' . md5($requestInfo['url']) . '.jpg';

        $_this->logInfo('保存：' . $fileName);

        is_dir(dirname($fileName)) or mkdir(dirname($fileName), 777, true);
        file_put_contents($fileName, $contents);
    });

    $ins->setErrorCallback(function(RequestException $e, Downloader $_this, $index) {
        $_this->logInfo('出错：' . $e->getMessage());
    });

    $ins->setOnDoneCallback(function(Downloader $_this) {
        $_this->logInfo('done');
    });
    $ins->send();