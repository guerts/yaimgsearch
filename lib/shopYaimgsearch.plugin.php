<?php

class shopYaimgsearchPlugin extends shopPlugin {
    
    public function backendProductEdit($entry) {
        return array(
            'images' => self::getLoader($entry, '.s-product-form.images .content')
        );
    }
    
    public function backendProduct($entry) {
        return array(
            'toolbar_section' => self::getLoader($entry, '#s-product-view')
        );
    }
    
    public function escapeString($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    public function getLoader($entry, $inject = null) {
        $inline_data = array(
            'product_id' => $entry['id'],
            'product_name' => htmlentities($entry['name']),
            'plugin_url' => $this->getPluginStaticUrl(),
            'inject' => $inject
        );
        $settings = wa('shop')->getPlugin('yaimgsearch')->getSettings();
        if (isset($settings['hide_bing_ad'])) {
            $inline_data['hide_bing_ad'] = true;
        }
        $inline_js = 'var self = this; (function($){ if (typeof yaImgSearch === "function"){ new yaImgSearch(self); } })(jQuery);';
        return "<img style='display:none;' data-bem='".self::escapeString(json_encode($inline_data))."' src='' onerror='".$inline_js."'>";
    }
    
    public function backendMenu() {
        $aux_li_view = wa()->getView();
        $aux_li_data = array(
            'plugin_url' => $this->getPluginStaticUrl(),
        );
        $aux_li_view->assign('data', $aux_li_data);
        return array(
            'aux_li' => $aux_li_view->fetch($this->path . '/templates/BackendProductImages.html'),
        );
    }
}