<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Goutte\Client;
use Illuminate\Support\Facades\DB;

class GetSubUrlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:getsuburl {baseurl?}';

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

        if (empty($baseurl)) {

            $url_links = DB::select('select link, depth from link_urls where done_flg = ? order by depth', [0]);
            foreach($url_links as $link) {
                $depth = $link->depth;
                $client = new Client();

                // 深さnまで掘出
                while ($destination_depth > 0) {
                    $this->info("深さ[${depth}] 掘出 開始");

                    $url_links = DB::select('select link, text from link_urls where depth = ? and done_flg = ?', [$depth++, 0]);
                    foreach($url_links as $link) {
                        $this->getSubUrls($client, $link->link, $depth);
                    }

                    $this->info("掘出 終了");
                    sleep(10);

                    $destination_depth--;
                }
            }
        }
        

        $this->parserLinks($baseurl, $destination_depth);
        $this->info("処理完了");
        //
    }

    public function parserLinks($base_url, $destination_depth) {

        $depth = 0;
        DB::insert('INSERT INTO link_urls(link, depth) VALUES (?, ?)', [$base_url, $depth++]);

        $client = new Client();
        $suburls = $this->getSubUrls($client, $base_url, $depth);

        // 深さnまで掘出
        while (!empty($suburls) && $destination_depth > 0) {
            $this->info("深さ[${depth}] 掘出 開始");

            $url_links = DB::select('select link, text from link_urls where depth = ? and done_flg = ?', [$depth++, 0]);
            foreach($url_links as $link) {
                $this->getSubUrls($client, $link->link, $depth);
            }

            $this->info("掘出 終了");
            sleep(10);

            $destination_depth--;
        }
    }

    public function getSubUrls($client, $url, $depth) {
        $patterns = array('a');

        $response = @file_get_contents($url);
        if ($response === false) {
            $this->error("無効なリンクURL[ $url ]");
            DB::update('update link_urls set done_flg = ? where link = ?', [1, $url]);
            return null;
        }
        
        sleep(10);

        $crawler = $client->request('GET', $url);
        $subUrls = array();
        foreach($patterns as $pattern) {
            $tmp = $crawler->filter($pattern)->each(function ($node) {
                // 文字数制限
                $strSize = mb_strlen(trim($node->text()));
                // 隙間時間を置く
                // sleep(1);
                if ($strSize > 4) {
                    $doUrl = $node->link()->getUri();
                    $excludeStrArray = array('mailto:', 'tel:', 'javascript:');
                    foreach($excludeStrArray as $value) {
                        if (strpos($doUrl, $value) !== false) {
                            return;
                        }
                    }

                    // error_log('before:<'.$node->link()->getUri().'>after<'.$node->attr('href').'>');
                    // home pageだけなら除外する
                    $urlPath = parse_url($doUrl, PHP_URL_PATH);
                    // linkでなくファイルの場合除外
                    $path = explode(".", $urlPath);
                    $last = end($path);
                    $excludeSuffixArray = array('js', 'css', 'jpg', 'jpeg', 'gif', 'bmp', 'png', 'txt');
                    if ($urlPath !== '/' && !in_array($last, $excludeSuffixArray) && strpos($doUrl, 'mailto:') === false && strpos($doUrl, 'tel:') === false && strpos($doUrl, 'javascript:') === false) {
                        return array('text'=>$node->text(), 'url'=>$doUrl);
                    }
                }
            });
            $tmp = array_filter($tmp, function($v, $k) {
                return !empty($v);
            }, ARRAY_FILTER_USE_BOTH);

            $sleep_count = 0;
            foreach($tmp as $key => $val) {
                $subUrls[$val['text']] = $val['url'];
                $url_exist = DB::select('select count(link) as exist from link_urls where link = ?', [$val['url']]);

                if (empty($url_exist[0]->exist)) {
                    DB::insert('INSERT INTO link_urls(link, text, depth) VALUES (?, ?, ?)', [$val['url'], $val['text'], $depth]);

                    $sleep_count++;
                    if ($sleep_count>50) {
                        $sleep_count = 0;
                        sleep(3);
                    }
                } 
            }
            DB::update('update link_urls set have_link_counts = ?, done_flg = ? where link = ? and depth = ?', [count($subUrls), 1, $url, $depth-1]);
        }
        return $subUrls;
    }
}
