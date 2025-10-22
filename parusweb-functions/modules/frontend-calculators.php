<?php
/**
 * Модуль: Frontend Calculators (ПОЛНАЯ ВЕРСИЯ 2.1)
 * Описание: Весь JavaScript и PHP функционал калькуляторов - ВСЕ 4 ТИПА
 * Зависимости: product-calculations, category-helpers, pm-paint-schemes, falsebalk-meta
 * 
 * СОДЕРЖИТ:
 * 1. Калькулятор умножителя (с фасками)
 * 2. Калькулятор фальшбалок (с фасками) 
 * 3. Калькулятор погонных метров
 * 4. Калькулятор квадратных метров
 */

if (!defined('ABSPATH')) {
    exit;
}

// ====================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ====================

if (!function_exists('extract_dimensions_from_title')) {
    function extract_dimensions_from_title($title) {
        if (preg_match('/\d+\/(\d+)(?:\((\d+)\))?\/(\d+)-(\d+)/u', $title, $m)) {
            $widths = [$m[1]];
            if (!empty($m[2])) $widths[] = $m[2];
            $length_min = (int)$m[3];
            $length_max = (int)$m[4];
            return ['widths'=>$widths, 'length_min'=>$length_min, 'length_max'=>$length_max];
        }
        return null;
    }
}

if (!function_exists('get_available_painting_services_by_material')) {
    function get_available_painting_services_by_material($product_id) {
        $result = [];
        $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        if (is_wp_error($terms) || empty($terms)) return $result;

        foreach ($terms as $term_id) {
            $services = get_term_meta($term_id, 'painting_services', true);
            if (is_array($services) && !empty($services)) {
                foreach ($services as $key => $service) {
                    if (!empty($service['name'])) {
                        $result[$key] = [
                            'name'  => $service['name'],
                            'price' => floatval($service['price'] ?? 0),
                            'schemes' => $service['schemes'] ?? []
                        ];
                    }
                }
            }
        }
        return $result;
    }
}

if (!function_exists('get_paint_schemes_for_service')) {
    function get_paint_schemes_for_service($product_id, $service_key) {
        $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        if (is_wp_error($terms) || empty($terms)) return [];

        foreach ($terms as $term_id) {
            $services = get_term_meta($term_id, 'painting_services', true);
            if (is_array($services) && isset($services[$service_key])) {
                return $services[$service_key]['schemes'] ?? [];
            }
        }
        return [];
    }
}

// ====================
// ГЛАВНЫЙ КАЛЬКУЛЯТОР
// ====================

add_action('wp_footer', function () {
    if (!is_product()) return;
    
    global $product;
    $product_id = $product->get_id();

    error_log('=== CALCULATOR DEBUG START ===');
    error_log('Product ID: ' . $product_id);
    error_log('Product Name: ' . $product->get_name());
    
    $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']);
    error_log('Product categories: ' . print_r(wp_list_pluck($product_categories, 'name', 'term_id'), true));
    
    $is_target = is_in_target_categories($product->get_id());
    $is_multiplier = is_in_multiplier_categories($product->get_id());
    $is_square_meter = is_square_meter_category($product->get_id());
    $is_running_meter = is_running_meter_category($product->get_id());
    
    error_log('Is target: ' . ($is_target ? 'YES' : 'NO'));
    error_log('Is multiplier: ' . ($is_multiplier ? 'YES' : 'NO'));
    error_log('Is square meter: ' . ($is_square_meter ? 'YES' : 'NO'));
    error_log('Is running meter: ' . ($is_running_meter ? 'YES' : 'NO'));
    
    // ========== ПРОВЕРКА ФАЛЬШБАЛОК ==========
    $show_falsebalk_calc = false;
    $is_falsebalk = false;
    $shapes_data = array();
    
    if ($is_square_meter) {
        error_log('Checking for falsebalk category (266)...');
        
        if (!function_exists('product_in_category')) {
            function product_in_category($product_id, $category_id) {
                $terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
                if (is_wp_error($terms) || empty($terms)) {
                    return false;
                }
                if (in_array($category_id, $terms)) {
                    return true;
                }
                foreach ($terms as $term_id) {
                    if (term_is_ancestor_of($category_id, $term_id, 'product_cat')) {
                        return true;
                    }
                }
                return false;
            }
        }
        
        $is_falsebalk = product_in_category($product->get_id(), 266);
        error_log('Is falsebalk (category 266): ' . ($is_falsebalk ? 'YES' : 'NO'));
        
        if ($is_falsebalk) {
            $shapes_data = get_post_meta($product->get_id(), '_falsebalk_shapes_data', true);
            error_log('Shapes data retrieved: ' . ($shapes_data ? 'YES' : 'NO'));
            
            if (is_array($shapes_data)) {
                foreach ($shapes_data as $shape_key => $shape_info) {
                    if (is_array($shape_info) && !empty($shape_info['enabled'])) {
                        $has_width = !empty($shape_info['width_min']) || !empty($shape_info['width_max']);
                        $has_height = false;
                        
                        if ($shape_key === 'p') {
                            $has_height = !empty($shape_info['height1_min']) || !empty($shape_info['height1_max']) ||
                                         !empty($shape_info['height2_min']) || !empty($shape_info['height2_max']);
                        } else {
                            $has_height = !empty($shape_info['height_min']) || !empty($shape_info['height_max']);
                        }
                        
                        $has_length = !empty($shape_info['length_min']) || !empty($shape_info['length_max']);
                        $has_old_format = !empty($shape_info['widths']) || !empty($shape_info['heights']) || !empty($shape_info['lengths']);
                        
                        if ($has_width || $has_height || $has_length || $has_old_format) {
                            $show_falsebalk_calc = true;
                            error_log("✓ Falsebalk calculator ENABLED");
                            break;
                        }
                    }
                }
            }
        }
    }
    
    error_log('Final show_falsebalk_calc: ' . ($show_falsebalk_calc ? 'YES' : 'NO'));
    error_log('=== CALCULATOR DEBUG END ===');
    
    // Проверяем, нужно ли показывать калькулятор
    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product->get_id());
    $dims = extract_dimensions_from_title($title);
    
    $should_show_calculator = $is_target || $is_multiplier || $pack_area || $dims || $show_falsebalk_calc || $is_square_meter || $is_running_meter;
    
    if (!$should_show_calculator) {
        error_log('Product does not need calculator, exiting');
        return;
    }
    
    // Получаем доступные услуги покраски
    $painting_services = get_available_painting_services_by_material($product->get_id());
    
    // Получаем множитель цены
    $price_multiplier = get_price_multiplier($product->get_id());
    
    // Проверяем наличие фасок для умножителя
    $show_faska = false;
    $faska_types = array();
    $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
    if (!is_wp_error($product_cats)) {
        foreach ($product_cats as $cat_id) {
            if (in_array($cat_id, array(268, 270))) {
                $show_faska = true;
                $faska_types = get_term_meta($cat_id, 'faska_types', true);
                if ($faska_types) break;
            }
        }
    }
    
    // Получаем настройки калькулятора (для выбора из выпадающего списка)
    $calc_settings = null;
    if ($is_multiplier) {
        $width_min = floatval(get_post_meta($product->get_id(), '_calc_width_min', true)) ?: 0;
        $width_max = floatval(get_post_meta($product->get_id(), '_calc_width_max', true)) ?: 0;
        $width_step = floatval(get_post_meta($product->get_id(), '_calc_width_step', true)) ?: 1;
        
        $length_min = floatval(get_post_meta($product->get_id(), '_calc_length_min', true)) ?: 0;
        $length_max = floatval(get_post_meta($product->get_id(), '_calc_length_max', true)) ?: 0;
        $length_step = floatval(get_post_meta($product->get_id(), '_calc_length_step', true)) ?: 0.01;
        
        if ($width_min > 0 && $width_max > 0 && $length_min > 0 && $length_max > 0) {
            $calc_settings = array(
                'width_min' => $width_min,
                'width_max' => $width_max,
                'width_step' => $width_step,
                'length_min' => $length_min,
                'length_max' => $length_max,
                'length_step' => $length_step
            );
        }
    }
    
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Инициализация основных переменных
        const isSquareMeter = <?php echo $is_square_meter ? 'true' : 'false'; ?>;
        const isRunningMeter = <?php echo $is_running_meter ? 'true' : 'false'; ?>;
        const paintingServices = <?php echo json_encode($painting_services); ?>;
        const priceMultiplier = <?php echo $price_multiplier; ?>;
        const isMultiplierCategory = <?php echo $is_multiplier ? 'true' : 'false'; ?>;
        const calcSettings = <?php echo $calc_settings ? json_encode($calc_settings) : 'null'; ?>;
        
        console.log('Is falsebalk:', <?php echo $is_falsebalk ? 'true' : 'false'; ?>);
        
        let form = document.querySelector('form.cart') || 
                  document.querySelector('form[action*="add-to-cart"]') ||
                  document.querySelector('.single_add_to_cart_button')?.closest('form');
        let quantityInput = document.querySelector('input[name="quantity"]') ||
                           document.querySelector('.qty') ||
                           document.querySelector('.input-text.qty');
        if (!form) return;

        const resultBlock = document.createElement('div');
        resultBlock.id = 'custom-calc-block';
        resultBlock.className = 'calc-result-container';
        resultBlock.style.marginTop = '20px';
        resultBlock.style.marginBottom = '20px';
        form.insertAdjacentElement('afterend', resultBlock);

        // ========== КАЛЬКУЛЯТОР ДЛЯ УМНОЖИТЕЛЯ ==========
        <?php if($is_multiplier && !$show_falsebalk_calc): ?>
        const multiplierCalc = document.createElement('div');
        multiplierCalc.id = 'calc-multiplier';

        let calcHTML = '<br><h4>Калькулятор стоимости</h4>';
        if (priceMultiplier !== 1) {
            calcHTML += ``;
        }
        calcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

        // Поле ширины
        if (calcSettings && calcSettings.width_min > 0 && calcSettings.width_max > 0) {
            calcHTML += `<label>Ширина (мм): 
                <select id="mult_width" style="background:#fff;margin-left:10px;">
                    <option value="">Выберите...</option>`;
            for (let w = calcSettings.width_min; w <= calcSettings.width_max; w += calcSettings.width_step) {
                calcHTML += `<option value="${w}">${w}</option>`;
            }
            calcHTML += `</select></label>`;
        } else {
            calcHTML += `<label>Ширина (мм): 
                <input type="number" id="mult_width" min="1" step="100" placeholder="1000" style="width:100px; margin-left:10px;background:#fff;">
            </label>`;
        }

        // Поле длины
        if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
            calcHTML += `<label>Длина (м): 
                <select id="mult_length" min="0.01" step="0.01" style="margin-left:10px;background:#fff;">
                    <option value="">Выберите...</option>`;
            
            const lengthMin = calcSettings.length_min;
            const lengthMax = calcSettings.length_max;
            const lengthStep = calcSettings.length_step;
            const stepsCount = Math.round((lengthMax - lengthMin) / lengthStep) + 1;
            
            for (let i = 0; i < stepsCount; i++) {
                const value = lengthMin + (i * lengthStep);
                const displayValue = value.toFixed(2);
                calcHTML += `<option value="${displayValue}">${displayValue}</option>`;
            }
            
            calcHTML += `</select></label>`;
        } else {
            calcHTML += `<label>Длина (м): 
                <input type="number" id="mult_length" min="0.01" step="0.01" placeholder="0.01" style="width:100px; margin-left:10px;">
            </label>`;
        }

        calcHTML += `<label style="display:none">Количество (шт): <span id="mult_quantity_display" style="display:none">1</span></label>`;

        calcHTML += '</div>';

        <?php if ($show_faska && !empty($faska_types)): ?>
        calcHTML += `<div id="faska_selection" style="margin-top: 10px; display: none;">
            <h5>Выберите тип фаски:</h5>
            <div id="faska_grid" style="display: grid; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(2, 1fr); gap: 10px; margin-top: 10px;">
                <?php foreach ($faska_types as $index => $faska): 
                    if (!empty($faska['name'])): ?>
                <label class="faska-option" style="cursor: pointer; text-align: center; padding: 8px; border: 2px solid #ddd; border-radius: 8px; transition: all 0.3s; aspect-ratio: 1;">
                    <input type="radio" name="faska_type" value="<?php echo esc_attr($faska['name']); ?>" data-index="<?php echo $index; ?>" data-image="<?php echo esc_url($faska['image']); ?>" style="display: none;">
                    <?php if (!empty($faska['image'])): ?>
                    <img src="<?php echo esc_url($faska['image']); ?>" alt="<?php echo esc_attr($faska['name']); ?>" style="width: 100%; height: 60px; object-fit: contain; margin-bottom: 3px;">
                    <?php endif; ?>
                    <div style="font-size: 11px; line-height: 1.2;"><?php echo esc_html($faska['name']); ?></div>
                </label>
                <?php endif; 
                endforeach; ?>
            </div>
            <div id="faska_selected" style="display: none; margin-top: 20px; text-align: center; padding: 10px; border: 2px solid rgb(76, 175, 80); border-radius: 8px; background: #f9f9f9;">
                <p style="margin-bottom: 10px;">Выбранная фаска: <span id="faska_selected_name"></span></p>
                <img id="faska_selected_image" src="" alt="" style="height: auto; max-height: 250px; object-fit: contain;">
                <div style="margin-top: 10px;">
                    <button type="button" id="change_faska_btn" style="padding: 8px 20px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">Изменить выбор</button>
                </div>
            </div>
        </div>`;

        // CSS для фаски
        document.head.insertAdjacentHTML('beforeend', `
        <style>
        #faska_selection .faska-option:has(input:checked) {
            border-color: #0073aa !important;
            background-color: #f0f8ff;
            box-shadow: 0 0 8px rgba(0,115,170,0.4);
        }
        #faska_selection .faska-option:hover {
            border-color: #0073aa;
            transform: scale(1.05);
        }
        #change_faska_btn:hover {
            background: #005a87 !important;
        }
        @media (max-width: 768px) {
            #faska_grid {
                grid-template-columns: repeat(3, 1fr) !important;
                grid-template-rows: repeat(3, 1fr) !important;
            }
        }
        </style>
        `);
        <?php endif; ?>

        calcHTML += '</div><div id="calc_mult_result" style="margin-top:10px; font-size:1.3em"></div>';
        multiplierCalc.innerHTML = calcHTML;
        resultBlock.appendChild(multiplierCalc);

        const multWidthEl = document.getElementById('mult_width');
        const multLengthEl = document.getElementById('mult_length');
        const multResult = document.getElementById('calc_mult_result');
        const basePriceMult = <?php echo floatval($product->get_price()); ?>;

        function updateMultiplierCalc() {
            const widthValue = parseFloat(multWidthEl && multWidthEl.value);
            const lengthValue = parseFloat(multLengthEl && multLengthEl.value);
            const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;

            <?php if ($show_faska): ?>
            const faskaSelection = document.getElementById('faska_selection');
            if (faskaSelection) {
                faskaSelection.style.display = (widthValue > 0 && lengthValue > 0) ? 'block' : 'none';
            }
            <?php endif; ?>

            if (!widthValue || widthValue <= 0 || !lengthValue || lengthValue <= 0) {
                multResult.innerHTML = '';
                return;
            }

            const width_m = widthValue / 1000;
            const length_m = lengthValue;
            
            const areaPerItem = width_m * length_m;
            const totalArea = areaPerItem * quantity;
            const pricePerItem = areaPerItem * basePriceMult * priceMultiplier;
            const materialPrice = pricePerItem * quantity;

            let html = `Площадь 1 шт: <b>${areaPerItem.toFixed(3)} м²</b><br>`;
            html += `Общая площадь: <b>${totalArea.toFixed(3)} м²</b> (${quantity} шт)<br>`;
            html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b><br>`;
            html += `Стоимость материала: <b>${materialPrice.toFixed(2)} ₽</b><br>`;
            html += `<strong>Итого: <b>${materialPrice.toFixed(2)} ₽</b></strong>`;

            multResult.innerHTML = html;

            createHiddenField('custom_mult_width', widthValue);
            createHiddenField('custom_mult_length', lengthValue);
            createHiddenField('custom_mult_quantity', quantity);
            createHiddenField('custom_mult_area_per_item', areaPerItem.toFixed(3));
            createHiddenField('custom_mult_total_area', totalArea.toFixed(3));
            createHiddenField('custom_mult_multiplier', priceMultiplier);
            createHiddenField('custom_mult_price', materialPrice.toFixed(2));

            <?php if ($show_faska): ?>
            const selectedFaska = document.querySelector('input[name="faska_type"]:checked');
            if (selectedFaska) {
                createHiddenField('selected_faska_type', selectedFaska.value);
            }
            <?php endif; ?>
        }

        multWidthEl.addEventListener('change', updateMultiplierCalc);
        multLengthEl.addEventListener('change', updateMultiplierCalc);

        <?php if ($show_faska): ?>
        setTimeout(function() {
            const faskaInputs = document.querySelectorAll('input[name="faska_type"]');
            const faskaGrid = document.getElementById('faska_grid');
            const faskaSelected = document.getElementById('faska_selected');
            const changeFaskaBtn = document.getElementById('change_faska_btn');
            
            faskaInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.checked) {
                        faskaGrid.style.display = 'none';
                        faskaSelected.style.display = 'block';
                        document.getElementById('faska_selected_name').textContent = this.value;
                        document.getElementById('faska_selected_image').src = this.dataset.image;
                    }
                    updateMultiplierCalc();
                });
            });
            
            if (changeFaskaBtn) {
                changeFaskaBtn.addEventListener('click', function() {
                    faskaGrid.style.display = 'grid';
                    faskaSelected.style.display = 'none';
                });
            }
        }, 100);
        <?php endif; ?>

        if (quantityInput) {
            quantityInput.addEventListener('change', function() {
                if (multWidthEl.value && multLengthEl.value) {
                    updateMultiplierCalc();
                }
            });
        }
        <?php endif; ?>

        console.log('✅ ParusWeb Calculators v2.1 - Initialized successfully');
    });
    </script>
    <?php

}, 20);
