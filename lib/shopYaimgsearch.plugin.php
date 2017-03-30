<?php

class shopYaimgsearchPlugin extends shopPlugin {
    
    public function backendProduct($entry) {
        $path = wa()->getAppPath('plugins/yaimgsearch/templates/BackendProductImages.html', 'shop');
        $view = wa()->getView();
        $view->assign('yaimgs_product_id', $entry['id']);
        $view->assign('product_name', $entry['name']);
        $view->assign('plugin_url', wa()->getPlugin('yaimgsearch')->getPluginStaticUrl());
        return array('images' => $view->fetch($path));
    }

}