<?php

return array(
    'name' => 'Яндекс.Картинки',
    'description' => 'Поиск и загрузка изображений для товара в Яндексе',
    'vendor' => '975294',
    'version' => '1.19.0',
    'img' => 'img/yaimgsearch.png',
    'shop_settings' => false,
    'frontend' => false,
    'icons' => array(
        16 => 'img/yaimgsearch.png',
    ),
    'handlers' => array(
        'backend_menu' => 'backendMenu',
        'backend_product_edit' => 'backendProductEdit',
        'backend_product' => 'backendProduct',
    ),
);
//EOF
