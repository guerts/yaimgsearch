<?php

class shopYaimgsearchPluginBackendUploadController extends waJsonController
{
    /**
     * @var shopProductImagesModel
     */
    private $model;
    private $allowed_mime_types = array('image/png', 'image/jpeg', 'image/jpg');
    
    public function execute()
    {
        /*check product*/
        $product_id = waRequest::post('product_id', null, waRequest::TYPE_INT);
        $product_model = new shopProductModel();
        
        if (!$product_model->checkRights($product_id)) {
            $this->response['error'] = "Access denied";
            return;
        }
        
        /*set $image_url*/
        $image_url = waRequest::post('url');
        
        /*check image & url*/
        if (!$this->checkImage($image_url)) {
            $this->response['error'] = "Not allowed MIME type or Sever Error";
            return;
        }
        
        /*generate filename & ext*/
        $url_array = explode('/', $image_url);
        $file_array = explode('.', $url_array[count($url_array)-1]);
        $file_name = $file_array[0];
        $file_extension = $file_array[1];
        
        /*upload file*/
        waFiles::upload($image_url, $file = wa()->getCachePath('plugins/yaimgsearch/' . $file_name . '.' . $file_extension));
        
        /*check file exist*/
        if (!($file && file_exists($file))) {
            $this->response['error'] = 'File not found';
            return;
        }
        
        $image = waImage::factory($file);
        
        /*check waImage*/
        if (!$image) {
            $this->response['error'] = 'Incorrect image';
            unlink($file);
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
    
    /**
     *  Check MIME Type & Image exist at remote url
     */
    protected function checkImage($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
		    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $exec = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            
            if ($exec !== FALSE && in_array($info['content_type'], $this->allowed_mime_types)) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }
    
    /**
     *  Check MIME Type from file
     */
    public function allowedMimeTypes($file)
    {
        if (!function_exists('mime_content_type')) {
            
            function mime_content_type($filename) {
                $mime_types = array(
                    'png' => 'image/png',
                    'jpeg' => 'image/jpeg',
                    'jpg' => 'image/jpg',
                    'gif' => 'image/gif',
                    'bmp' => 'image/bmp',
                );
                $ext = strtolower(array_pop(explode('.', $file)));
                if (array_key_exists($ext, $mime_types)) {
                    return $mime_types[$ext];
                } elseif (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME);
                    $mimetype = finfo_file($finfo, $file);
                    finfo_close($finfo);
                    return $mimetype;
                } else {
                    return 'application/octet-stream';
                }
            }
            
        }
        
        if (!in_array(mime_content_type($file), $this->allowed_mime_types)) {
            return false;
        }
        return true;
    }

}