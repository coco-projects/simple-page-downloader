<?php

    use Coco\simplePageDownloader\Downloader;
    use Coco\simplePageDownloader\Utils;
    use GuzzleHttp\Exception\RequestException;
    use Psr\Http\Message\ResponseInterface;

    require '../vendor/autoload.php';

    Downloader::initClientConfig([
        'timeout' => 60.0,
        'verify'  => false,
        'debug'   => !true,
    ]);
    Downloader::initLogger('download_log', true, true);

    $url = 'https://cdn84037269.blazingcdn.net/game/c/2025/04-28/89/620978211267454.jpg';
    $ins = Downloader::ins();

    $ins->setEnableCache(!false);
    $ins->setCachePath('../downloadCache');
    $ins->baseCacheStrategy();

    $ins->setMethod('get');
    $ins->setSettings([
        "proxy" => 'http://192.168.0.111:1080',
        'query' => [
            'channelid' => '224453',
        ],
    ]);

    $ins->setSuccessCallback(function(string $contents, Downloader $_this, ResponseInterface $response) {
        $fileName = '../testData/' . md5($_this->url) . '.jpg';
        $_this->logInfo('保存：' . $fileName);

        is_dir(dirname($fileName)) or mkdir(dirname($fileName), 777, true);

        file_put_contents($fileName, $contents);
    });

    $ins->setErrorCallback(function(RequestException $e, Downloader $_ins1) {
        $_ins1->logInfo('出错：' . $e->getMessage());

    });

    $ins->setUrl($url);
    $ins->sendRequest();