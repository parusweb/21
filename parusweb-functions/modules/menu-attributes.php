<?php
/*
Plugin Name: PM Mega Menu Attributes
Description: Подгружает атрибуты товаров из JSON для Mega Menu и подменяет их при наведении.
Version: 1.0
Author: ParusWeb
*/

if (!defined('ABSPATH')) exit; // Защита от прямого доступа

class PM_Menu_Attributes {

    public function __construct() {
        // Скрипт вставляется в wp_footer
        add_action('wp_footer', [$this, 'inject_attributes_script']);
    }

    public function inject_attributes_script() {
        ?>
        <script>
        jQuery(function($){
            let cache = null;

            // Загружаем JSON один раз
            $.getJSON('<?php echo esc_url(home_url("/menu_attributes.json")); ?>', function(data){
                cache = data;

                // Рендерим для виджетов сразу после загрузки
                $('.widget_layered_nav').each(function(){
                    renderAttributes($(this));
                });
            });

            // Подмена при наведении на подкатегории Mega Menu
            $(document).on('mouseenter', '.mega-menu-item-type-taxonomy', function(){
                let href = $(this).find('a').attr('href');
                if (!href) return;

                let parts = href.split('/');
                let catSlug = parts.filter(Boolean).pop(); 

                $('.widget_layered_nav').each(function(){
                    renderAttributes($(this), catSlug);
                });
            });

            function renderAttributes($widget, overrideCat){
                if (!cache) return;

                let attr = $widget.data('attribute');
                let cat = overrideCat || $widget.data('category');

                if (cat && attr && cache[cat] && cache[cat][attr]) {
                    let $ul = $('<ul class="attribute-list"/>');
                    cache[cat][attr].forEach(function(t){
                        let base = '<?php echo esc_url(home_url("/product-category/")); ?>' + cat + '/';
                        let url = base + '?_' + attr.replace('pa_','') + '=' + t.slug;
                        $ul.append('<li><a href="'+url+'">'+t.name+' <span class="count">('+t.count+')</span></a></li>');
                    });
                    $widget.html($ul);
                } else {
                    $widget.html('<div class="no-attributes">Нет атрибутов</div>');
                }
            }
        });
        </script>
        <?php
    }
}

// Инициализация класса
new PM_Menu_Attributes();
