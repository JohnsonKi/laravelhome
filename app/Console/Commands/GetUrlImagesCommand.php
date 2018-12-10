<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Illuminate\Support\Facades\DB;

class GetUrlImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:geturlimages {baseurl?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $baseurl = $this->argument("baseurl");
        $destination_depth = 3;
        $client = new Client();
        define('MIN_IMG_HEIGHT', 200);

        if (empty($baseurl)) {

            $url_links = DB::select('select link, depth from link_urls where img_done_flg = ? order by depth', [0]);
            foreach($url_links as $link) {
                $crawler = $client->request('GET', $link->link);
                $this->getImages($crawler, $link->link);

                sleep(10);
            }
        } else {
            $crawler = $client->request('GET', $baseurl);
            $this->getImages($crawler, $baseurl);
        }
    }

    public function getImages($crawler, $url) {
        $patterns = array('img','input');
        $imgUrls = array();
        foreach($patterns as $pattern) {
            $tmp = $crawler->filter($pattern)->each(function ($node) {
                $type = $node->nodeName();
                $url = "";
                if ($type == 'img') {
                    $url1 = $node->attr('data-src');
                    $url2 = $node->attr('mydatasrc');
                    if (!empty($url1)) {
                        $url = $url1;
                    } else if (!empty($url2)) {
                        $url = $url2;
                    } else {
                        $url = $node->image()->getUri();
                        // error_log('before:<'.$node->image()->getUri().'>after<'.$node->attr('src').'>');
                    }
                } else if ($type == 'input') {
                    $url2 = $node->attr('data-src');
                    $url4 = $node->attr('mydatasrc');
                    if (!empty($url4)) {
                        $url = $url4;
                    } else if (!empty($url2)) {
                        $url = $url2;
                    }
                }
                $includeSuffixArray = array('jpg', 'jpeg', 'gif', 'bmp', 'png');
                if (!$this->checkURL($url)) {
                    return;
                };
                $easypath = explode("?", $url);
                // http://xxxx/sss/aaa.jpeg
                $first = current($easypath);
                $urlPath = parse_url($first, PHP_URL_PATH);
                $path = explode(".", $urlPath);
                $last = end($path);
                if ($urlPath !== '/' && in_array($last, $includeSuffixArray)) {
                    if (!empty($easypath[1])) {
                        list($imgW,$imgH) = getimagesize(substr($url, 0, strpos($url, "?")));
                    } else {
                        list($imgW,$imgH) = getimagesize($url);
                    }
                    // error_log('>>>>width:<'.$imgW.'> height:<'.$imgH.'>');
                    if ($imgH > MIN_IMG_HEIGHT) {
                        return $first;
                    }
                }
                if (!empty($easypath[1])) {
                    // http://xxxx/sss/aaa?wx_fmt=jpeg
                    $qtmp = explode("&", $easypath[1]);
                    foreach($qtmp as $k=>$v) {
                        $vv = explode("=", $v);
                        foreach($vv as $kkk=>$vvv) {
                            if (in_array($vvv, $includeSuffixArray)) {
                                list($imgW,$imgH) = getimagesize(substr($url, 0, strpos($url, "?")));
                                // error_log('>>>>width:<'.$imgW.'> height:<'.$imgH.'>');
                                if ($imgH > MIN_IMG_HEIGHT) {
                                    return $url;
                                }
                            }
                        }
                    }
                }

                sleep(10);
            });

            $tmp = array_filter($tmp, function($v, $k) {
                return !empty($v);
            }, ARRAY_FILTER_USE_BOTH);
            foreach($tmp as $key => $val) {
                $imgdata = file_get_contents($val);
                $suffixStr = explode(".", $val);
                $imgpath = './download/';
                if (file_exists($imgpath)) {
                    $imgname = $imgpath . date("YmdHis") . rand(10, 99) . "." . end($suffixStr);
                    file_put_contents($imgname, $imgdata);
                } else {
                    echo "$imgpath は存在しません";
                }
                $imgUrls[] = $val;
                sleep(10);
            }
        }
        DB::update('update link_urls set have_img_counts = ?, img_done_flg = ? where link = ?', [count($imgUrls), 1, $url]);
    }

    public function checkURL($url) {
        $response = @file_get_contents($url);
        if ($response === false) {
            $this->error("無効なリンクURL[ $url ]");
            return false;
        }
        return true;
    }
}
