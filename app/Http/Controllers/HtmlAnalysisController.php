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

        // 深さ1まで掘出
        foreach($suburls as $subUrl) {
            $dep1s = $this->getSubUrls($client, $subUrl);
            $suburls = array_merge($suburls, $dep1s);
        }

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
        $patterns = array('a');
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
                    // error_log('before:<'.$node->link()->getUri().'>after<'.$node->attr('href').'>');
                    // home pageだけなら除外する
                    $urlPath = parse_url($doUrl, PHP_URL_PATH);
                    // linkでなくファイルの場合除外
                    $path = explode(".", $urlPath);
                    $last = end($path);
                    $excludeSuffixArray = array('js', 'css', 'jpg', 'jpeg', 'gif', 'bmp', 'png', 'txt');
                    if ($urlPath !== '/' && !in_array($last, $excludeSuffixArray)) {
                        return array('text'=>$node->text(), 'url'=>$doUrl);
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
        define('MIN_IMG_HEIGHT', 100);
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
                $path = explode("?", $url);
                if (!empty($path[1])) {
                    // http://xxxx/sss/aaa?wx_fmt=jpeg
                    $qtmp = explode("&", $path[1]);
                    foreach($qtmp as $k=>$v) {
                        $vv = explode("=", $v);
                        foreach($vv as $kkk=>$vvv) {
                            if (in_array($vvv, $includeSuffixArray)) {
                                list($imgW,$imgH) = getimagesize($url);
                                // error_log('>>>>width:<'.$imgW.'> height:<'.$imgH.'>');
                                if ($imgH > MIN_IMG_HEIGHT) {
                                    return $url;
                                }
                            }
                        }
                    }
                }
                // http://xxxx/sss/aaa.jpeg
                $first = current($path);
                $urlPath = parse_url($first, PHP_URL_PATH);
                $path = explode(".", $urlPath);
                $last = end($path);
                if ($urlPath !== '/' && in_array($last, $includeSuffixArray)) {
                    list($imgW,$imgH) = getimagesize($url);
                    // error_log('>>>>width:<'.$imgW.'> height:<'.$imgH.'>');
                    if ($imgH > MIN_IMG_HEIGHT) {
                        return $first;
                    }
                }
            });
            $tmp = array_filter($tmp, function($v, $k) {
                return !empty($v);
            }, ARRAY_FILTER_USE_BOTH);
            foreach($tmp as $key => $val) {
                $imgUrls[] = $val;
            }
        }
        return $imgUrls;
    }
}
