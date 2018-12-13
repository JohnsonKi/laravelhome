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

        if (empty($baseurl)) {

            $url_links = DB::select('select link, depth from link_urls where done_flg = ? and invalid_flg = ? order by depth', [0, 0]);
            foreach($url_links as $link) {

                $link_depth = $link->depth;
                $link_url = $link->link;

                $this->info(" --------- Start Spider URL:[ $link_url ] --------- ");
                $this->getSubUrls($link_url, $link_depth);
            }
        } else {

            $this->info(" --------- Start Spider URL:[ $baseurl ] --------- ");
            $this->getSubUrls($baseurl, 0);
        }
    }

    public function getSubUrls($url, $depth) {

        if (!$this->checkURLValid($url)) {
            DB::update('update link_urls set done_flg = ?, invalid_flg = ? where link = ?', [1, 1, $url]);
            return;
        }

        $client = new Client();
        $patterns = array('a');
        $crawler = $client->request('GET', $url);

        $subUrls = array();
        foreach($patterns as $pattern) {
            $tmp = $crawler->filter($pattern)->each(function ($node) {
                
                $link_text = $node->text();
                $link_url = $node->link()->getUri();

                if (!$this->isExcludeURL($link_text, $link_url)) {
                    return array('text'=>$link_text, 'url'=>$link_url);
                };

            });

            $tmp = array_filter($tmp, function($v, $k) {
                return !empty($v);
            }, ARRAY_FILTER_USE_BOTH);

            foreach($tmp as $key => $val) {

                $subUrls[$val['text']] = $val['url'];

                if (!$this->isDBExistURL($val['url'])) {
                    DB::insert('INSERT INTO link_urls(link, text, depth) VALUES (?, ?, ?)', [$val['url'], $val['text'], $depth+1]);
                };
            }
            
            DB::update('update link_urls set have_link_counts = ?, done_flg = ? where link = ? and depth = ?', [count($subUrls), 1, $url, $depth]);
        }
    }

    public function checkURLValid($baseurl) {
        $response = @file_get_contents($baseurl);
        if ($response === false) {
            $this->error("Not a Valid URL[ $baseurl ]");
            return false;
        }
        
        sleep(10);

        return true;
    }

    public function isExcludeURL($link_text, $link_url) {

        // 文字数制限
        $strSize = mb_strlen(trim($link_text));
        if ($strSize < 5) {
            $this->info('Link Text Character limit Over:[' . $link_text .'] [' . $link_url . ']');
            return true;
        }

        // 特殊リンク制限
        $excludeStrArray = array('mailto:', 'tel:', 'javascript:');
        foreach($excludeStrArray as $value) {
            if (strpos($link_url, $value) !== false) {
                $this->info('Link URL Type inValid:[' . $value .'] [' . $link_url . ']');
                return true;
            }
        }

        // ホームページ除外
        $urlPath = parse_url($link_url, PHP_URL_PATH);
        if ($urlPath === '/') {
            $this->info('home Link URL:[' . $urlPath .'] [' . $link_url . ']');
            return true;
        }

        // linkでなくファイルの場合除外
        $path = explode(".", $urlPath);
        $last = end($path);
        $excludeSuffixArray = array('js', 'css', 'jpg', 'jpeg', 'gif', 'bmp', 'png', 'txt');
        if (in_array($last, $excludeSuffixArray)) {
            $this->info('Is File URL:[' . $last .'] [' . $link_url . ']');
            return true;
        }

        return false;
    }

    public function isDBExistURL($link_url) {
        $url_exist = DB::select('select count(link) as exist from link_urls where link = ?', [$link_url]);
        if (!empty($url_exist[0]->exist)) {
            return true;
        }
        return false;
    }
}
