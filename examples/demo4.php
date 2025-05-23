<?php

    use Coco\simplePageDownloader\Downloader;
    use Coco\simplePageDownloader\Utils;
    use GuzzleHttp\Exception\RequestException;
    use Psr\Http\Message\ResponseInterface;

    require '../vendor/autoload.php';

    Downloader::initClientConfig([
        "base_uri" => "https://www.nppa.gov.cn",
        'timeout'  => 6.0,
        'verify'   => false,
        'debug'    => !true,
    ]);
    Downloader::initLogger('download_log', true, true);

    $headerStr = <<<AAA
Accept-Encoding: gzip, deflate, br, zstd
Accept-Language: zh-CN,zh;q=0.9
Cache-Control: no-cache
Connection: keep-alive
Content-Length: 36
Content-Type: application/x-www-form-urlencoded; charset=UTF-8
Cookie: Secure; JSESSIONID=C200CDA15628840B08BD12C2B58F83C9; __jsluid_s=61def6c6e15024f3d6d83495f4e70721; Hm_lvt_538de0f31b4f290a9d5c6245f79e2dbe=1721393649; _gscu_606539676=21393649urrgdc20; _gscbrs_606539676=1; _trs_uv=lyspc151_6098_hi5y; HMACCOUNT=7A4349C1762DF7EA; _trs_ua_s_1=lywo6wzm_6098_1seo; __jsl_clearance_s=1721633610.13|0|tvo1p%2FQQJPrt8mfD9I5MClmxbUY%3D; Hm_lpvt_538de0f31b4f290a9d5c6245f79e2dbe=1721633599; _gscs_606539676=t21633599uadh3e98|pv:2; Secure
Host: www.nppa.gov.cn
Origin: https://www.nppa.gov.cn
Pragma: no-cache
Referer: https://www.nppa.gov.cn/
Sec-Fetch-Dest: empty
Sec-Fetch-Mode: cors
Sec-Fetch-Site: same-origin
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36
X-Requested-With: XMLHttpRequest
sec-ch-ua: "Not/A)Brand";v="8", "Chromium";v="126", "Google Chrome";v="126"
sec-ch-ua-mobile: ?0
sec-ch-ua-platform: "Windows"
AAA;

    $url = '/was5/web/search';

    $ins            = Downloader::ins();
    $ins->savePath  = './nppa/';
    $ins->headerStr = $headerStr;
    $ins->initLogger('nppa', true, true);

    $ins->setEnableCache(true);
    $ins->setCachePath('../downloadCache');
    $ins->baseCacheStrategy();
    $ins->setRawHeader($ins->headerStr);
    $ins->logInfo('采集列表');

    for ($i = 1; $i <= 7; $i++)
    {
        $postPrams = [
            "page" => $i,
            "size" => "100",
            "jgmc" => "",
            "kanh" => "G4",
            "leix" => "45",
        ];
        $ins->logInfo('列表-' . $i);

        $ins->addBatchRequest($url, 'post', [
            //            'body' => "page={$i}&size=20&jgmc=&kanh=G4&leix=45",
            'query'       => [
                'channelid' => '224453',
            ],
            "form_params" => $postPrams,
        ]);

        $ins->setOnDoneCallback(function(Downloader $_this) {
            $_this->logInfo('done');
        });

        $ins->setSuccessCallback(function(string $contents, Downloader $_this, ResponseInterface $response) use ($i) {
            $json = json_decode($contents, true);
            $_this->logInfo('保存：' . '列表结果：- ' . count($json['data']));

        })->setErrorCallback(function(RequestException $e, Downloader $_this) {
            $_this->logInfo('出错：' . $e->getMessage());

        })->send();

        $ins->logInfo('等1秒...');

        sleep(1);
    }