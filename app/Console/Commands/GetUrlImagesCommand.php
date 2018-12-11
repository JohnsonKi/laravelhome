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
        
        define('MIN_IMG_HEIGHT', 200);
        define('MIN_IMG_WIDTH', 200);

        if (empty($baseurl)) {

            $url_links = DB::select('select link, depth from link_urls where img_done_flg = ? order by depth', [0]);
            foreach($url_links as $link) {
                $this->info(" --------- Start Spider URL:[ $link->link ] --------- ");
                $this->getImages($link->link);

                sleep(10);
            }
        } else {
            $this->getImages($baseurl);
        }
    }

    public function getImages($baseurl) {

        $client = new Client();
        $crawler = $client->request('GET', $baseurl);

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

                if (!$this->checkImgURL($url)) {
                    return;
                } else {
                    sleep(3);
                };

                $imageData = $this->getImageBaseInfo($url);
                if ($this->isExcludeImages($imageData, $url)) {
                    return;
                };

                return $url;
            });

            $tmp = array_filter($tmp, function($v, $k) {
                return !empty($v);
            }, ARRAY_FILTER_USE_BOTH);
            foreach($tmp as $key => $val) {
                $this->saveImages($val, $baseurl);
                $imgUrls[] = $val;

                sleep(10);
            }
        }
        DB::update('update link_urls set have_img_counts = ?, img_done_flg = ? where link = ?', [count($imgUrls), 1, $url]);
    }

    public function isExcludeImages($imageData, $url) {

        if (empty($imageData)) {
            return true;
        }

        $width = imagesx($imageData);
        if ($width <= MIN_IMG_WIDTH) {
            // $this->info("exclude image:[ $url ] [ width=$width ]");
            return true;
        }

        $height = imagesy($imageData);
        if ($height <= MIN_IMG_HEIGHT) {
            // $this->info("exclude image:[ $url ] [ height=$height ]");
            return true;
        }

        return false;
    }

    public function checkImgURL($url) {
        $response = @file_get_contents($url, stream_context_create(array('http' => array('timeout' => 160))));
        if ($response === false) {
            $this->error("Not a Valid URL[ $url ]");
            return false;
        }

        $easypath = explode("?", $url);
        $first = current($easypath);
        $urlPath = parse_url($first, PHP_URL_PATH);
        $path = explode(".", $urlPath);
        $last = mb_strtolower(end($path));

        $includeSuffixArray = array('jpg', 'jpeg', 'gif', 'bmp', 'png');

        // http://xxxx/sss/aaa.jpeg
        if ($urlPath !== '/' && in_array($last, $includeSuffixArray)) {
            return true;
        }

        if (!empty($easypath[1])) {
            // http://xxxx/sss/aaa?wx_fmt=jpeg
            $qtmp = explode("&", $easypath[1]);
            foreach($qtmp as $k=>$v) {
                $vv = explode("=", $v);
                foreach($vv as $kkk=>$vvv) {
                    if (in_array($vvv, $includeSuffixArray)) {
                        return true;
                    }
                }
            }
        }

        $this->error("Not a Vaild Image URL [ $url ]");
        return false;
    }

    public function getImageBaseInfo($baseurl) {

        $curl = curl_init($baseurl);
        curl_setopt($curl, CURLOPT_TIMEOUT, 800);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        
        $data = curl_exec($curl);
        $error_number = curl_errno($curl);
        $error_message = curl_error($curl);
        curl_close($curl);

        if ($data === FALSE || empty($data) || !empty($error_message)) {
            $this->error("image data check error [ $baseurl ] [ $error_number ] [ $error_message ]");
            return;
        }

        $image = imagecreatefromstring($data);

        return $image;
    }

    public function saveImages($imgurl, $baseurl) {

        $imageData = $this->getImageBaseInfo($imgurl);
        if (empty($imageData)) {
            $this->error("image data check error [ $imgurl ]");
            return;
        }

        $imgType = exif_imagetype($imgurl);
        $folder_name = parse_url($baseurl, PHP_URL_HOST);

        $imgpath = './download/'.$folder_name;
        if (!file_exists($imgpath)) {
            mkdir($imgpath, 0777, true);
        }

        $imgname = $imgpath . '/' . date("YmdHis") . rand(10, 99) . image_type_to_extension($imgType);
        imagetruecolortopalette($imageData, false, 255);

        switch ($imgType) {
            case IMAGETYPE_GIF:
                imagegif($imageData, $imgname);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($imageData, $imgname, 100);
                break;
            case IMAGETYPE_PNG:
                imagepng($imageData, $imgname, 0);
                break;
            case IMAGETYPE_BMP:
                imagewbmp($imageData, $imgname);
                break;
            default:
                $this->info("exclude image suffix:[ image_type_to_extension($imgType) ]");
        }
    }
}
