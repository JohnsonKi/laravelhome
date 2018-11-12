<?php

namespace App\Http\Controllers;

use Goutte\Client;
use Illuminate\Http\Request;

class HtmlAnalysisController extends Controller
{
    public function parserLinks(Request $req) {
        $base_url = $req->input('base_url');
        $client = new Client();
        $suburls = $this->getSubUrls($client, $base_url);
        $data = ['test'=>$suburls];
        return view('link', $data);
    }

    public function parserImages(Request $req) {
        $base_url = $req->input('base_url');
        $client = new Client();
        $crawler = $client->request('GET', $base_url);
        $imgUrls = $this->getImages($crawler, $base_url);

        // 深さ1まで掘出
//         $subUrls = $this->getSubUrls($client, $base_url);
//         foreach($subUrls as $subUrl) {
//             $crawler = $client->request('GET', $subUrl);
//             $dep1s = $this->getImages($crawler);
//             $imgUrls = array_merge($imgUrls, $dep1s);
//         }

        $data = ['test'=>$imgUrls];
        return view('img', $data);
    }

    public function getSubUrls($client, $url) {
        $patterns = array('p > a', 'h1 > a', 'h3 > a', 'h4 > a', 'h5 > a', 'li > a', 'div > a', 'font > a', 'span > a');
        $crawler = $client->request('GET', $url);
        $subUrls = array();
        foreach($patterns as $pattern) {
            $tmp = $crawler->filter($pattern)->each(function ($node) {
                // 文字数制限
                $strSize = mb_strlen(trim($node->text()));
                // 隙間時間を置く
                // sleep(1);
                if ($strSize > 6) {
                    $doUrl = $node->link()->getUri();
                    // home pageだけなら除外する
                    $urlPath = parse_url($doUrl, PHP_URL_PATH);
                    // linkでなくファイルの場合除外
                    $path = explode(".", $urlPath);
                    $last = end($path);
                    $excludeSuffixArray = array('js', 'css', 'jpg', 'jpeg', 'gif', 'bmp', 'png', 'txt');
                    if ($urlPath !== '/' && !in_array($last, $excludeSuffixArray)) {
                        return array('text'=>$node->text(), 'url'=>$node->link()->getUri());
                    }
                }
            });
            $tmp = array_filter($tmp, function($v, $k) {
                return !empty($v);
            }, ARRAY_FILTER_USE_BOTH);
            foreach($tmp as $key => $val) {
                $subUrls[$val['text']] = $val['url'];
            }
        }
        return $subUrls;
    }

    public function getImages($crawler, $url) {
        $urlPath = parse_url(trim($url));
        $urlHome = $urlPath['scheme'] . '://' . $urlPath['host'];

        $patterns = array('a > img','div > img','p > img','div > input','p > input','pre > img');
        $imgUrls = array();
        foreach($patterns as $pattern) {
            $tmp = $crawler->filter($pattern)->each(function ($node) {
                $type = $node->nodeName();
                $url = "";
                if ($type == 'img') {
                    $url1 = $node->image()->getUri();
                    error_log('url1->'.$url1);
                    $url3 = $node->attr('src');
                    error_log('url3->'.$url3);
                    if (!empty($url3)) {
                        $url = $url3;
                    } else {
                        $url = $url1;
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
                $path = explode("?", $url);
                $first = current($path);
                $urlPath = parse_url($first, PHP_URL_PATH);
                $path = explode(".", $urlPath);
                $last = end($path);
                $includeSuffixArray = array('jpg', 'jpeg', 'gif', 'bmp', 'png');
                if ($urlPath !== '/' && in_array($last, $includeSuffixArray)) {
                    return $first;
                }
            });
            $tmp = array_filter($tmp, function($v, $k) {
                return !empty($v);
            }, ARRAY_FILTER_USE_BOTH);
            foreach($tmp as $key => $val) {
                $imgUrls[] = $val;
            }
        }
        foreach($imgUrls as $k=>$v) {
            $tmpHost = parse_url($v, PHP_URL_HOST);
            if (empty($tmpHost)) {
                $imgUrls[$k] = $urlHome.'/'.$v;
            }
        }
        return $imgUrls;
    }
}
