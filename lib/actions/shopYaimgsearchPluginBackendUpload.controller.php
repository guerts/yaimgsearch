<?php

class shopYaimgsearchPluginBackendUploadController extends waJsonController
{
    /**
     * @var shopProductImagesModel
     */
    private $model;

    public function execute()
    {
        $url = waRequest::post('url');
        $array = explode('/', $url);
        $f = explode('.', $array[count($array)-1]);
        $file_name = $f[0];
        $file_extension = $f[1];
        
        waFiles::upload($url, $file = wa()->getCachePath('plugins/yaimgsearch/' . $file_name . '.' . $file_extension));
        
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        $product_model = new shopProductModel();
        if (!$product_model->checkRights($product_id)) {
            $this->response['error'] = "Access denied";
        }
        
        if (!($file && file_exists($file))) {
            $this->response['error'] = 'File not found';
            return;
        }
        
        $image = waImage::factory($file);
        if (!$image) {
            $this->response['error'] = 'Incorrect image';
            return;
        }

        $image_changed = false;

        /**
         * Extend upload proccess
         * Make extra workup
         * @event image_upload
         */
        $event = wa()->event('image_upload', $image);
        if ($event) {
            foreach ($event as $plugin_id => $result) {
                if ($result) {
                    $image_changed = true;
                }
            }
        }
        
        if (!$this->model) {
            $this->model = new shopProductImagesModel();
        }
        
        $app_config = wa()->getConfig()->getAppConfig('shop');
        if ($app_config->getOption('image_filename')) {
            
            $filename = basename($file_name . '.' . $file_extension);
            if (!preg_match('//u', $filename)) {
                $tmp_name = @iconv('windows-1251', 'utf-8//ignore', $filename);
                if ($tmp_name) {
                    $filename = $tmp_name;
                }
            }
            $filename = preg_replace('/\s+/u', '_', $filename);
            if ($filename) {
                foreach (waLocale::getAll() as $l) {
                    $filename = waLocale::transliterate($filename, $l);
                }
            }
            $filename = preg_replace('/[^a-zA-Z0-9_\.-]+/', '', $filename);
            if (!strlen(str_replace('_', '', $filename))) {
                $filename = '';
            }
        } else {
            $filename = '';
        }

        $data = array(
            'product_id'        => $product_id,
            'upload_datetime'   => date('Y-m-d H:i:s'),
            'width'             => $image->width,
            'height'            => $image->height,
            'size'              => filesize($file),
            'filename'          => $filename,
            'original_filename' => basename($file_name . '.' . $file_extension),
            'ext'               => $image->getExt(),
        );
        
        $image_id = $data['id'] = $this->model->add($data);

        if (!$image_id) {
            $this->response['error'] = "Database error";
            return;
        }

        /**
         * @var shopConfig $config
         */
        $config = wa()->getConfig()->getAppConfig('shop');
        
        $image_path = shopImage::getPath($data);
        if ((file_exists($image_path) && !is_writable($image_path)) || (!file_exists($image_path) && !waFiles::create($image_path))) {
            $this->model->deleteById($image_id);
            //$this->response['error'] = sprintf("The insufficient file write permissions for the %s folder.", substr($image_path, strlen($config->getRootPath())));
            $this->response['error'] = 'Image saving error';
            return;
        }
        
        if ($image_changed) {
            $image->save($image_path);
            // save original
            $original_file = shopImage::getOriginalPath($data);
            if ($config->getOption('image_save_original') && $original_file) {
                waFiles::copy($file, $original_file);
            }
        } else {
            waFiles::copy($file, $image_path);
        }
        unset($image);        // free variable
        
        shopImage::generateThumbs($data, $config->getImageSizes());
        
        $this->response['images'][] = array(
            'id'             => $image_id,
            'name'           => $file_name . '.' . $file_extension,
            'type'           => waFiles::getMimeType($file),
            'size'           => filesize($file),
            'url_thumb'      => shopImage::getUrl($data, $config->getImageSize('thumb')),
            'url_crop'       => shopImage::getUrl($data, $config->getImageSize('crop')),
            'url_crop_small' => shopImage::getUrl($data, $config->getImageSize('crop_small')),
            'description'    => ''
        );
        
        unlink($file);
    }

}