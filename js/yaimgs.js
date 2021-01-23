/*
 * yaimgs.js
 * v1.16
 */  
    function yaImgSearch(options) {
        
        var form = $('.yaimgs-toolbar').find('form');
        var content = $('.yaimgs-content');
        var carousel =  $('.yaimgs-carousel');
        var modal = $('.yaimgs-modal');
        var infos = modal.find('.yaimgs-info');
        var gallery = $('.yaimgs-gallery');
        var batches = $('.yaimgs-batches');
        
        var product_id = options.product_id;
        var locales = options.locales;
        var urls = options.urls;
        
        var page = 1;
        var images = '';
        var image_timestamp;
        var not_exist_images = [];
        var processed_images = [];
        
        function uploadImage(data) {
            //add to processing
            processed_images.push(data.url);
            //template
            var template = $(tmpl('yaimgs-batch', { data: data }));
            var bW = batches.find('.yaimgs-batches-wrapper');
            bW.prepend(template);
            setTimeout(function(){
                template.removeClass('just-added');
            }, 1);
            $.post(urls.upload, data, function(response) {
                if (template.hasClass('fade-out')) {
                    bW.prepend(template);
                }
                if (response && response.status == 'ok' && response.data.images) {
                    template.addClass('success');
                    $('#s-product-image-list').append(tmpl('template-product-image-list', {
                        images: response.data.images,
                        placeholder: locales.placeholder,
                        product_id: product_id
                    }));
                } else if (response && response.status == 'ok' && response.data.error) {
                    template.addClass('error');
                    console.log(response.data.error);
                }
                //remove from processing
                setTimeout(function() {
                    var index = processed_images.indexOf(data.url);
                    processed_images.splice(index, 1);
                }, 500);
                //template
                template.removeClass('fade-out');
            }, 'json').fail(function() {
                template.addClass('error');
                //remove from processing
                setTimeout(function() {
                    var index = processed_images.indexOf(data.url);
                    processed_images.splice(index, 1);
                }, 500);
                //template
                template.removeClass('fade-out');
            });
        }
        
        modal.on('click', '.yaimgs-core-upload', function(){
            var link = infos.find('.yaimgs-core-link.selected');
            var data = {
                product_id: product_id,
                url: link.attr('href'),
                thumb: link.data('thumb'),
                width: link.data('width'),
                height: link.data('height'),
                filesize: link.data('filesize')
            }
            var inarray = $.inArray(data.url, processed_images);
            if ($(this).hasClass('disabled') || $('.yaimgs-core-image').hasClass('load') || inarray > -1) {
                return false;
            }
            uploadImage(data);
            return false;
        });
        
        batches.on('click', '.yaimgs-batches-item', function(){
            var self = $(this);
            $(this).addClass('fade-out');
            if ($(this).hasClass('success') || $(this).hasClass('error')) {
                setTimeout(function(){
                    self.remove();
                }, 1000);
            }
            return false;
        })
        
        function imageExists(url, timestamp, callback) {
            var inarray = $.inArray(url, not_exist_images);
            if (inarray != -1) {
                callback({ exists: false, timestamp: timestamp });
                return false;
            }
            var img = new Image();
            img.onload = function() {
                callback({ exists: true, timestamp: timestamp });
            };
            img.onerror = function() {
                callback({ exists: false, timestamp: timestamp });
            };
            img.src = url;
        }
        
        function loadImage(url) {
            $('.yaimgs-core-image').addClass('load');
            $('.yaimgs-core-image').removeClass('error');
            $('#yaimgs-zoom-container').trigger('zoom.destroy');
            image_timestamp = new Date().getUTCMilliseconds();
            imageExists(url, image_timestamp, function(response) {
                if (response.timestamp == image_timestamp && response.exists) {
                    $('.yaimgs-core-image img').attr('src', url);
                    $('.yaimgs-core-image').removeClass('load');
                    $('.yaimgs-core-upload').removeClass('disabled');
                    $('#yaimgs-zoom-container').zoom({ on: 'mouseover' });
                } else if (response.timestamp == image_timestamp && !response.exists) {
                    $('.yaimgs-core-image').addClass('error');
                    $('.yaimgs-core-image').removeClass('load');
                    $('.yaimgs-core-upload').addClass('disabled');
                }
                if (!response.exists) {
                    not_exist_images.push(url);
                }
            });
        }

        function loadInfo(index) {
            var data = images[index];
            infos.html(tmpl('yaimgs-info', { data: data }));
            var url = data.preview[0].url;
            loadImage(url);
        }
        
        infos.on('click', '.yaimgs-core-link', function(){
            $(this).siblings().removeClass('selected');
            $(this).addClass('selected');
            var url = $(this).attr('href');
            loadImage(url);
            return false;
        });
        
        modal.on('click', '.yaimgs-modal-close', function(){
            modal.hide();
            return false;
        });
        
        modal.on('click', '.yaimgs-core-prev', function(){
            var sel = carousel.find('.selected').index();
            carousel.find('.slick-slide').eq(sel-1).click();
            return false;
        });
        
        modal.on('click', '.yaimgs-core-next', function(){
            var sel = $('.yaimgs-carousel').find('.selected').index();
            carousel.find('.slick-slide').eq(sel+1).click();
            return false;
        });
        
        carousel.on('click', '.slick-slide', function(){
            $(this).siblings().removeClass('selected');
            $(this).addClass('selected');
            var index = $(this).index();
            loadInfo(index);
            modal.show();
            carousel.slick('slickGoTo', index);
            return false;
        });
        
        carousel.slick({
            arrows: false,
            infinite: false,
            dots: false,
            centerMode: true,
            focusOnSelect: true,
            variableWidth: true,
            adaptiveHeight: false,
            swipe: false
        }).on('afterChange', function(event, slick, currentSlide, nextSlide){
            //some log
        });
        
        modal.on('mousewheel', function (e) {
            e.preventDefault()
            if (e.deltaY > 0) {
                carousel.slick('slickGoTo', carousel.find('.slick-current').index() - 1, true)
            } else {
                carousel.slick('slickGoTo', carousel.find('.slick-current').index() + 1, true)
            }
        });
        
        function getImages(url, is_append, callback) {
            $.post(urls.images, { url: url }, function(response) {
                var is_load = gallery.find('.yaimgs-gallery-item').length;
                content.find('form').remove();
                content.find('p').remove();
                modal.hide();
                if (response && response.status == 'ok' && response.data.captcha) {
                    var captcha = $($.parseHTML(response.data.captcha));
                    if (!is_append) {
                        images = '';
                        gallery.html('');
                        gallery.removeAttr('style');
                        carousel.slick('removeSlide', null, null, true);
                    }
                    content.append(captcha);
                    content.show();
                    content.find('.yaimgs-more').hide();
                    callback('captcha');
                } else if (response && response.status == 'ok' && response.data.images) {
                    if (images && is_append) {
                        images = images.concat(response.data.images);
                    } else if (!is_load || !is_append) {
                        images = response.data.images;
                    } else {
                        images = '';
                    }
                    var template = tmpl('yaimgs-images', { images: response.data.images });
                    content.show();
                    if (is_load && is_append) {
                        gallery.append(template);
                        gallery.justifiedGallery('norewind');
                        $.each(response.data.images, function(i, v) {
                            carousel.slick('slickAdd','<a href="#" style="background-image: url('+v['thumb'].url+');"></a>');
                        });
                    } else {
                        gallery.html(template);
                        gallery.justifiedGallery({
                            rowHeight: 120,
                            margins: 16
                        });
                        carousel.slick('removeSlide', null, null, true);
                        $.each(response.data.images, function(i, v) {
                            carousel.slick('slickAdd','<a href="#" style="background-image: url('+v['thumb'].url+');"></a>');
                        });
                    }
                    content.find('.yaimgs-more').show();
                    if (!response.data.images.length) {
                        callback(false);
                        return false;
                    }
                    callback('images');
                } else if (response && response.status == 'ok' && response.data.error) {
                    if (!is_append) {
                        images = '';
                        gallery.html('');
                        gallery.removeAttr('style');
                        carousel.slick('removeSlide', null, null, true);
                        content.find('.yaimgs-more').hide();
                    }
                    content.show();
                    content.append('<p>' + response.data.error + '</p>');
                    callback(false);
                } else {
                    //some errors
                }
            }, 'json');
        };
        
        form.submit(function(){
            form.addClass('load');
            var url = urls.search + encodeURIComponent(form.find('input').val());
            getImages(url, false, function(callback){
                form.removeClass('load');
            });
            return false;
        });
        
        content.on('click', '.yaimgs-more a', function(){
            var div = $(this).parent('div');
            if (div.hasClass('load')) {
                return false;
            }
            div.addClass('load');
            page = page + 1;
            var url = urls.search + encodeURIComponent(form.find('input').val()) + '&p=' + page;
            getImages(url, true, function(callback) {
                if (!callback) {
                    div.hide();
                }
                div.removeClass('load');
            });
            return false;
        });
        
        content.on('submit', 'form', function(){
            var f = $(this);
            var btn = f.find('button[type=submit]');
            if (!btn.find('.loading').length) {
                btn.append('<i class="icon16 loading"></i>');
            }
            f.addClass('load');
            var url = urls.captcha + f.serialize();
            getImages(url, true, function(callback){
                f.removeClass('load');
            });
            return false;
        });
        
        content.on('click', '.yaimgs-gallery-item a', function(){
            var index = $(this).parent('div').index();
            var img_array = [];
            $.each(images[index].preview, function(i, v){
                img_array.push({ href: v.url });
            });
            $.each(images[index].sizes, function(i, v){
                img_array.push({ href: v.url });
            });
            carousel.find('.slick-slide').siblings().removeClass('selected');
            carousel.find('.slick-slide').eq(index).addClass('selected');
            loadInfo(index);
            modal.show();
            carousel.slick('slickGoTo', index, true);
            return false;
        });
        
    };
/*
 * end
 */