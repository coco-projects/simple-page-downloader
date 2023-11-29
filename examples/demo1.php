<?php

    use Coco\simplePageDownloader\Downloader;
    use GuzzleHttp\Exception\RequestException;
    use Psr\Http\Message\ResponseInterface;

    require '../vendor/autoload.php';

    $client = new \GuzzleHttp\Client();
    $ins    = Downloader::ins($client);

    $ins->savePath = './data/';

    $ins->setSettings([]);
    $ins->setEnableCache(true);
    $ins->setMethod('get');

    $url = 'https://www.258zw.com/html/249463/';

    $ins->setUrl($url)->setSuccessCallback(function(string $contents, Downloader $_this) {
        $contents = $_this::gbkToUtf8($contents);

        echo '采集主页';
        echo PHP_EOL;

        preg_match_all('%href="(/html/\d+/\d+\.html)">([^<>]+)<%', $contents, $result, PREG_SET_ORDER);

        foreach ($result as $k => $v)
        {
            $_this->title = $v[2];
            $url          = 'https://www.258zw.com' . $v[1];

            $_this->dataFilePath = $_this->savePath . ($k + 1) . '-' . $_this->title;

            if (is_file($_this->dataFilePath))
            {
                echo $_this->dataFilePath . '------存在，跳过';
                echo PHP_EOL;
                continue;
            }

            $_this->setUrl($url)->setSuccessCallback(function(string $contents, Downloader $_this) {
                $contents = $_this::gbkToUtf8($contents);

                echo '采集 -- ' . $_this->title;
                echo PHP_EOL;

                preg_match_all('%<div id="chapterContent">[\s\S]+?</div>%iu', $contents, $result, PREG_SET_ORDER);

                if (isset($result[0][0]))
                {
                    $res = $result[0][0];

                    $res = preg_replace('%<style[^<>]*>[\S\s]+?</style>%im', '', $res);
                    $res = preg_replace('%<script[^<>]*>[\S\s]+?</script>%im', '', $res);
                    $res = preg_replace('%</?div[^<>]*>%im', '', $res);
                    $res = preg_replace('/<(p|center)>/im', '', $res);
                    $res = preg_replace('%</p>%im', "\r\n", $res);
                    $res = preg_replace('%(<br */>)+%im', "\r\n", $res);
                    $res = preg_replace('% +%imu', " ", $res);
                    $res = preg_replace('%[ \t]+%im', " ", $res);
                    $res = preg_replace('%[\r\n]+%im', "\r\n", $res);
                    $res = html_entity_decode($res); // 将HTML实体转换为特殊字符

                    is_dir(dirname($_this->dataFilePath)) or mkdir(dirname($_this->dataFilePath), 0777, 1);

                    file_put_contents($_this->dataFilePath, trim($res));
                    echo '采集成功';
                    echo PHP_EOL;
                }
                else
                {
                    echo '采集失败';
                    echo PHP_EOL;
                }
                echo PHP_EOL;

            })->sendRequest();

            echo '等2秒';
            echo PHP_EOL;
            sleep(2);
        }

    })->sendRequest();

