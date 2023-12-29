<?php

    use Coco\simplePageDownloader\Downloader;
    use GuzzleHttp\Exception\RequestException;
    use Psr\Http\Message\ResponseInterface;

    require '../vendor/autoload.php';

    $client = new \GuzzleHttp\Client();

    $ins = Downloader::ins($client);

    $url = 'http://127.0.0.1:6025/new/coco-simplePageDownloader/examples/testTarget.php';
    $ins->savePath = './thn21-data/';

    $ins->setSettings([]);
    $ins->setEnableCache(true);
    $ins->setMethod('get');

    $ins->setUrl($url)->setSuccessCallback(function (string $contents, Downloader $_this) {
        $contents = $_this::gbkToUtf8($contents);

        echo '采集主页';
        echo PHP_EOL;

        echo $contents;

    })->setErrorCallback(function (RequestException $e, Downloader $_this) {
        echo PHP_EOL;
        echo PHP_EOL;

        echo $e->getMessage();
        echo PHP_EOL;
        echo PHP_EOL;

    })->sendRequest();

