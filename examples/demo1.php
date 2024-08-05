<?php

    use Coco\simplePageDownloader\Downloader;
    use Coco\simplePageDownloader\Utils;
    use GuzzleHttp\Exception\RequestException;
    use Psr\Http\Message\ResponseInterface;

    require '../vendor/autoload.php';

    Downloader::init([
        "base_uri" => "https://www.nppa.gov.cn",
        'timeout'  => 60.0,
        'verify'   => false,
        'debug'    => !true,
    ]);

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

    $ins->setEnableCache(false);
    $ins->setMethod('post');
    $ins->setRawHeader($ins->headerStr);

    for ($i = 1; $i <= 7; $i++)
    {
        $postPrams = [
            "page" => $i,
            "size" => "100",
            "jgmc" => "",
            "kanh" => "G4",
            "leix" => "45",
        ];

        $ins->setSettings([
            //            'body' => "page={$i}&size=20&jgmc=&kanh=G4&leix=45",

            'query' => [
                'channelid' => '224453',
            ],

            "form_params" => $postPrams,
        ]);

        echo '采集列表：- ' . $i;
        echo PHP_EOL;

        print_r($postPrams);;;
        echo PHP_EOL;

        $ins->setUrl($url)
            ->setSuccessCallback(function(string $contents, Downloader $_this, ResponseInterface $response) use ($i) {
//            $contents = $_ins1::gbkToUtf8($contents);

                $json = json_decode($contents, true);

                echo '列表结果：- ' . count($json['data']);
                echo PHP_EOL;

                $_ins1 = Downloader::ins();

                $_ins1->setEnableCache(true);
                $_ins1->setMethod('get');

                $_ins1->setSettings([
                    'verify' => false,
                ]);

                $_ins1->setRawHeader(<<<BBB
Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7
Accept-Encoding: gzip, deflate, br, zstd
Accept-Language: zh-CN,zh;q=0.9
Cache-Control: no-cache
Connection: keep-alive
Cookie: Secure; __jsluid_s=61def6c6e15024f3d6d83495f4e70721; Hm_lvt_538de0f31b4f290a9d5c6245f79e2dbe=1721393649; _gscu_606539676=21393649urrgdc20; _gscbrs_606539676=1; _trs_uv=lyspc151_6098_hi5y; HMACCOUNT=7A4349C1762DF7EA; _trs_ua_s_1=lywo6wzm_6098_1seo; __jsl_clearance_s=1721633610.13|0|tvo1p%2FQQJPrt8mfD9I5MClmxbUY%3D; Hm_lpvt_538de0f31b4f290a9d5c6245f79e2dbe=1721633599; _gscs_606539676=t21633599uadh3e98|pv:2; Secure
Host: www.nppa.gov.cn
Pragma: no-cache
Referer: https://www.nppa.gov.cn/
Sec-Fetch-Dest: document
Sec-Fetch-Mode: navigate
Sec-Fetch-Site: same-origin
Sec-Fetch-User: ?1
Upgrade-Insecure-Requests: 1
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36
sec-ch-ua: "Not/A)Brand";v="8", "Chromium";v="126", "Google Chrome";v="126"
sec-ch-ua-mobile: ?0
sec-ch-ua-platform: "Windows"


BBB
                );

                foreach ($json['data'] as $k1 => $v1)
                {
                    $url = $v1['docpuburl'];

                    $_ins1->setUrl($url)->setSuccessCallback(function(string $contents, Downloader $_ins1,ResponseInterface $response) use ($i) {

                        echo '采集详细：' . $_ins1->url;
                        echo PHP_EOL;

                        preg_match_all('%<td width="184"[^<>]+>\s+(\S+)\s+</td>\s+<td align="left"[^<>]+>\s+(\S*)\s+</td>%im', $contents, $result, PREG_SET_ORDER);

                        $data = [
                            $result[0][2],
                            $result[1][2],
                            $result[2][2],
                            $result[3][2],
                            $result[4][2],
                            $result[5][2],
                            $result[6][2],
                            $_ins1->url,
                            strtr($result[7][2], ["," => "，",]),
                        ];

                        echo '详细结果：' . PHP_EOL;
                        print_r($data);

                        file_put_contents('result' . $i . '.csv', implode(',', $data) . PHP_EOL, 8);
                        echo PHP_EOL;

                    })->setErrorCallback(function(RequestException $e, Downloader $_ins1) {
                        echo PHP_EOL;
                        echo PHP_EOL;

                        echo $e->getMessage();

                        file_put_contents('getMessage.txt', $e->getMessage());

                        echo PHP_EOL;
                        echo PHP_EOL;

                    })->sendRequest();

                    if (!$_ins1->getIsByCache())
                    {
                        echo $i . ' - ' . $k1 . '等1秒...';
                        echo PHP_EOL;
                        echo PHP_EOL;
                        sleep(1);
                    }
                    else
                    {
                        echo $i . ' - ' . $k1 . 'by cache...';
                        echo PHP_EOL;
                        echo PHP_EOL;
                    }
                }

            })->setErrorCallback(function(RequestException $e, Downloader $_ins1) {
            echo PHP_EOL;
            echo PHP_EOL;

            echo $e->getMessage();
            echo PHP_EOL;
            echo PHP_EOL;

        })->sendRequest();

        echo '列表-等1秒...';
        echo PHP_EOL;
        echo PHP_EOL;
        sleep(1);
    }