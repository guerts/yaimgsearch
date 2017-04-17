<?php

class shopYaimgsearchPluginBackendImagesController extends waJsonController
{
    public function execute()
    {
        $url = waRequest::post('url');
        $response = self::get($url);
        if ($response['status'] == 302) {
            $response = self::get($response['redirect_url']);
        }
        $this->response['status'] = $response['status'];

        $pMc = preg_match("/<form([^>]*)checkcaptcha([^>]*)>(.*?)<\/form>/s", $response['content'], $captcha);
        if ($captcha) {
            $this->response['captcha'] = $captcha[0];
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
        foreach ($items as $item_key => $item_val) {
            $preview_tmp = array();
            foreach ($item_val->preview as $p_key => $p_val) {
                $p_tmp = $p_val;
                $p_origin = $p_val->origin;
                $p_val->origin ? $p_tmp->url = $p_origin->url : '';
                unset($p_tmp->origin);
                $preview_tmp[] = $p_tmp;
            }
            $sizes_tmp = array();
            foreach ($item_val->dups as $p_key => $p_val) {
                $p_tmp = $p_val;
                $p_origin = $p_val->origin;
                $p_val->origin ? $p_tmp->url = $p_origin->url : '';
                unset($p_tmp->origin);
                $sizes_tmp[] = $p_tmp;
            }
            $thumb = $item_val->thumb;
            $thumb_size = $thumb->size;
            $items_tmp[$item_key]['thumb'] = array(
                'url' => $thumb->url,
                'width' => $thumb_size->width,
                'height' => $thumb_size->height
            );
            $items_tmp[$item_key]['preview'] = $preview_tmp;
            $items_tmp[$item_key]['sizes'] = $sizes_tmp;
            $items_tmp[$item_key]['description'] = $item_val->snippet;
        }
        return $items_tmp;
    }
    
    protected function get($url, &$status = null)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
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
}