<?php

    use Coco\simplePageDownloader\Downloader;
    use Coco\simplePageDownloader\Utils;
    use GuzzleHttp\Exception\RequestException;
    use Psr\Http\Message\ResponseInterface;

    require '../vendor/autoload.php';

    $client = new \GuzzleHttp\Client();

    $ins = Downloader::ins($client);

    $url           = 'https://www.thn21.com/Article/chang/25135.html';
    $ins->savePath = './thn21-data/';

    $ins->setSettings([]);
    $ins->setEnableCache(true);
    $ins->setMethod('get');

    $ins->setErrorCallback(function(RequestException $e, Downloader $_this) {
        $error = 'error:' . $_this->url . ':' . $e->getMessage() . PHP_EOL;
        file_put_contents('./log.txt', $error . PHP_EOL, 8);
        echo $error;
    });

    $ins->setClientErrorCallback(function(RequestException $e, Downloader $_this) {
        $error = 'client_error:' . $_this->url . ':' . $e->getMessage() . PHP_EOL;
        file_put_contents('./log.txt', $error . PHP_EOL, 8);
    });

    $ins->setUrl($url)->setSuccessCallback(function(string $contents, Downloader $_this) {
        $contents = $_this::gbkToUtf8($contents);

        echo '采集主页';
        echo PHP_EOL;

        preg_match_all('%href=[\'"]?(/Article/chang/weiren/mydoc\d+.htm)%ui', $contents, $result, PREG_PATTERN_ORDER);

//    [0] => /Article/chang/weiren/mydoc002.htm
        $urls = [];
        if (count($result[1]))
        {
            $urls = $result[1];
        }
        else
        {
            echo '没有列表';
            echo PHP_EOL;
        }

        foreach ($urls as $k => $url)
        {

            $url = 'https://www.thn21.com' . $url;
            echo '采集' . $url;
            echo PHP_EOL;

            $_this->setUrl($url)->setSuccessCallback(function(string $contents, Downloader $_this) use ($url, $k) {
                $contents = $_this::gbkToUtf8($contents);

                preg_match('/<div id=[\'"]?V[\'"]?>[\s\S]+?<div class=[\'"]?WW[\'"]?>/iu', $contents, $result);
                preg_match('%<div class=[\'"]?ti[\'"]?>[\s\S]+?<H1>([^<>]+)</H1>%i', $contents, $titleResult);

                $res   = '';
                $title = '-';

                if (isset($result[0]))
                {
                    $res = $result[0];
                }
                else
                {
                    echo '没有结果:' . $url;
                    echo PHP_EOL;
                }

                if (isset($titleResult[1]))
                {
                    $title = $titleResult[1];
                }
                else
                {
                    echo '没有标题:' . $url;
                    echo PHP_EOL;
                }

                $title = Utils::sanitizeFileName($title);

                $path = $_this->savePath . (($k + 1) . '-' . $title) . '.txt';
                is_dir(dirname($path)) or mkdir(dirname($path));

                $res = Utils::strToDBC($res);
                $res = preg_replace('%<style[^<>]*>[\S\s]*?</style>%imu', '', $res);
                $res = preg_replace('%<script[^<>]*>[\S\s]*?</script>%imu', '', $res);
                $res = preg_replace('%</?div[^<>]*>%imu', '', $res);
                $res = preg_replace('/<(p|center)>/im', '', $res);
                $res = preg_replace('%</p>%imu', "\r\n", $res);
                $res = preg_replace('%(<br */>)+%imu', "\r\n", $res);
                $res = preg_replace('%[ \t　 +]+%imu', " ", $res);
                $res = preg_replace('%[\r\n]+%imu', "\r\n", $res);
                $res = preg_replace('%<p align[\S\s]+%imu', "", $res);
                $res = html_entity_decode($res); // 将HTML实体转换为特殊字符


                file_put_contents($path, $res);

                echo '写入完成:' . $url;
                echo PHP_EOL;
                echo PHP_EOL;

//            sleep(1);

            })->sendRequest();
        }
    })->sendRequest();

