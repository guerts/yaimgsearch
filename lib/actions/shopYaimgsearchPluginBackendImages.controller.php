<?php

class shopYaimgsearchPluginBackendImagesController extends waJsonController
{
    public function execute()
    {
        /*hide ads Bing Images*/
        if (waRequest::post('hide_bing_ad')) {
            $plugin = wa('shop')->getPlugin('yaimgsearch');
            $plugin->saveSettings(array('hide_bing_ad' => 1));
            $this->response['ads_save'] = true;
            return false;
        }
        
        /*Begin Plugin*/
        $post = waRequest::post();
        if (!isset($post['post'])) {
            $response = self::get($post['url']);
        } else {
            $response = self::post($post['url'], $post['post']);
        }
        if ($response['status'] == 302) {
            $response = self::get($response['redirect_url']);
        }
        $this->response['status'] = $response['content'];

        $pMc = preg_match("/<form([^>]*)checkcaptcha([^>]*)>(.*?)<\/form>/s", $response['content'], $captcha);
        $pMm = preg_match('/https:\/\/mc\.yandex\.ru\/watch\/(.*?)\?ut\=noindex/s', $response['content'], $metrika);
        if ($metrika) {
            self::get($metrika[0]);
        }   
        if ($captcha) {
            $captcha_content = preg_replace('/onsubmit\="([^>]*)"/', '', $captcha[0]);
            $captcha_content = preg_replace('/form form_error_no|form form_error_yes/', 'yaimgs-captcha', $captcha_content);
            $this->response['captcha'] = $captcha_content;
            return;
        }
        
        $pMb = preg_match_all("/data\-bem\='(.*?)'/s", $response['content'], $bems);
        $items = array();
        foreach ($bems[1] as $bem) {
            if (preg_match("/serp\-item/s", $bem)) {
                $item = str_replace('{"serp-item":', '', $bem);
                $item = substr($item, 0, -1);
                $item = json_decode($item);
                $items[] = $item;
            }
        }
        if ($items) {
            $this->response['images'] = $this->arrangeItems($items);
        } else {
            $this->response['error'] = 'Ничего не найдено';
        }
        
    }
    
    protected function arrangeItems($items)
    {
        $items_tmp = array();
        $items = json_decode(json_encode($items), true);
        foreach ($items as $item_key => $item_val) {
            $preview_tmp = array();
            foreach ((array) $item_val['preview'] as $p_key => $p_val) {
                $p_tmp = $p_val;
                $p_tmp['width'] = isset($p_tmp['width']) ? $p_tmp['width'] : $p_tmp['w'];;
                $p_tmp['height'] = isset($p_tmp['height']) ? $p_tmp['h'] : $p_tmp['h'];
                unset($p_tmp['w']);
                unset($p_tmp['h']);
                $p_tmp['url'] = isset($p_val['origin']) ? $p_val['origin']['url'] : $p_val['url'];
                unset($p_tmp['origin']);
                $preview_tmp[] = $p_tmp;
            }
            $sizes_tmp = array();
            foreach ((array) $item_val['dups'] as $p_key => $p_val) {
                $p_tmp = $p_val;
                $p_tmp['width'] = isset($p_tmp['width']) ? $p_tmp['width'] : $p_tmp['w'];
                $p_tmp['height'] = isset($p_tmp['height']) ? $p_tmp['h'] : $p_tmp['h'];
                unset($p_tmp['w']);
                unset($p_tmp['h']);
                $p_tmp['url'] = isset($p_val['origin']) ? $p_val['origin']['url'] : $p_val['url'];
                unset($p_tmp['origin']);
                $sizes_tmp[] = $p_tmp;
            }
            $thumb = $item_val['thumb'];
            $thumb_size = $thumb['size'];
            $items_tmp[$item_key]['thumb'] = array(
                'url' => $thumb['url'],
                'width' => isset($thumb_size['width']) ? $thumb_size['width'] : $thumb_size['w'] ,
                'height' => isset($thumb_size['height']) ? $thumb_size['height'] : $thumb_size['h']
            );
            $items_tmp[$item_key]['preview'] = $preview_tmp;
            $items_tmp[$item_key]['sizes'] = $sizes_tmp;
            $items_tmp[$item_key]['description'] = $item_val['snippet']['text'];
        }
        return $items_tmp;
    }
    
    protected function get($url, &$status = null)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url); 
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'user-agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.141 Safari/537.36',
                'viewport-width: 1200',
                'referer:'.$url,
                'origin: https://yandex.ru',
                'pragma: no-cache',
                'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'accept-language: ru-RU,ru;q=0.9',
                //'accept-encoding: gzip, deflate, br'
            ));
            
            //curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
            //curl_setopt($ch, CURLINFO_HEADER, 0);
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 25);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, wa()->getDataPath('plugins/yaimgsearch/' . 'cookie.txt', false, 'shop', true)); 
            curl_setopt($ch, CURLOPT_COOKIEFILE, wa()->getDataPath('plugins/yaimgsearch/' . 'cookie.txt', false, 'shop', true));
            $content = curl_exec($ch);
            $info = curl_getinfo($ch);
            
            curl_close($ch);
            return array(
                'content' => $content,
                'status' => $info['http_code'],
                'redirect_url' => $info['redirect_url']
            );
        }
        return file_get_contents($url);
    }
    
    protected function post($url, $post_data, &$status = null)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'user-agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.141 Safari/537.36',
                'viewport-width: 1200',
                'referer:'.$url,
                'origin: https://yandex.ru',
                'pragma: no-cache',
                'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                'accept-language: ru-RU,ru;q=0.9',
                //'accept-encoding: gzip, deflate, br'
            ));
            
            //curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
            //curl_setopt($ch, CURLOPT_HEADER, 0);
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 25);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, wa()->getDataPath('plugins/yaimgsearch/' . 'cookie.txt', false, 'shop', true)); 
            curl_setopt($ch, CURLOPT_COOKIEFILE, wa()->getDataPath('plugins/yaimgsearch/' . 'cookie.txt', false, 'shop', true));
            $content = curl_exec($ch);
            $info = curl_getinfo($ch);
            
            curl_close($ch);
            return array(
                'content' => $content,
                'status' => $info['http_code'],
                'redirect_url' => $info['redirect_url']
            );
        }
        return file_get_contents($url);
    }
}