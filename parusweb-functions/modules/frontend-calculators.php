<?php
/**
 * Модуль: Frontend Calculators (ПОЛНАЯ ВЕРСИЯ)
 * Описание: Весь JavaScript и PHP функционал калькуляторов
 * Зависимости: product-calculations, category-helpers
 * 
 * ВАЖНО: Содержит ВСЁ из frontend-calculators + legacy-javascript
 */

if (!defined('ABSPATH')) {
    exit;
}

// Вспомогательные функции
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
                            'price' => floatval($service['price'] ?? 0)
                        ];
                    }
                }
            }
        }
        return $result;
    }
}

// === ГЛАВНЫЙ КАЛЬКУЛЯТОР ===
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
    
    // Проверка фальшбалок
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
    
    if (!$is_target && !$is_multiplier) {
        error_log('Product not in target or multiplier categories, exiting');
        return;
    }
    
    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product->get_id());
    $dims = extract_dimensions_from_title($title);
    
    $painting_services = get_available_painting_services_by_material($product->get_id());
    $price_multiplier = get_price_multiplier($product->get_id());
    
    $calc_settings = null;
    if ($is_multiplier) {
        $calc_settings = [
            'width_min' => floatval(get_post_meta($product->get_id(), '_calc_width_min', true)),
            'width_max' => floatval(get_post_meta($product->get_id(), '_calc_width_max', true)),
            'width_step' => floatval(get_post_meta($product->get_id(), '_calc_width_step', true)) ?: 100,
            'length_min' => floatval(get_post_meta($product->get_id(), '_calc_length_min', true)),
            'length_max' => floatval(get_post_meta($product->get_id(), '_calc_length_max', true)),
            'length_step' => floatval(get_post_meta($product->get_id(), '_calc_length_step', true)) ?: 0.01,
        ];
    }
    
    $product_id = $product->get_id();
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
    $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
    $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
    
    ?>
    
    <script>
    console.log('ParusWeb Calculators v2.0 - Loading...');
    
    const paintingServices = <?php echo json_encode($painting_services); ?>;
    const priceMultiplier = <?php echo $price_multiplier; ?>;
    const isMultiplierCategory = <?php echo $is_multiplier ? 'true' : 'false'; ?>;
    const isSquareMeter = <?php echo $is_square_meter ? 'true' : 'false'; ?>;
    const isRunningMeter = <?php echo $is_running_meter ? 'true' : 'false'; ?>;
    const calcSettings = <?php echo $calc_settings ? json_encode($calc_settings) : 'null'; ?>;

    document.addEventListener('DOMContentLoaded', function() {
        console.log('ParusWeb Calculators - DOMContentLoaded', {
            isSquareMeter,
            isRunningMeter,
            isMultiplierCategory,
            calcSettings
        });
        
        let form = document.querySelector('form.cart') || 
                  document.querySelector('form[action*="add-to-cart"]') ||
                  document.querySelector('.single_add_to_cart_button')?.closest('form');
                  
        let quantityInput = document.querySelector('input[name="quantity"]') ||
                           document.querySelector('.qty') ||
                           document.querySelector('.input-text.qty');
        
        if (!form) {
            console.warn('ParusWeb: Cart form not found');
            return;
        }

        const resultBlock = document.createElement('div');
        resultBlock.id = 'custom-calc-block';
        resultBlock.className = 'calc-result-container';
        resultBlock.style.cssText = 'margin-top:20px; margin-bottom:20px;';
        form.insertAdjacentElement('afterend', resultBlock);
        
        console.log('✓ Result block created');

        let isAutoUpdate = false;

        // === ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ===
        
        function getRussianPlural(n, forms) {
            n = Math.abs(n);
            n %= 100;
            if (n > 10 && n < 20) return forms[2];
            n %= 10;
            if (n === 1) return forms[0];
            if (n >= 2 && n <= 4) return forms[1];
            return forms[2];
        }

        function removeHiddenFields(prefix) {
            const fields = form.querySelectorAll(`input[name^="${prefix}"]`);
            fields.forEach(field => field.remove());
        }

        function createHiddenField(name, value) {
            let field = form.querySelector(`input[name="${name}"]`);
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                form.appendChild(field);
            }
            field.value = value;
            return field;
        }

        // === БЛОК УСЛУГ ПОКРАСКИ ===
        
        function createPaintingServicesBlock() {
            if (Object.keys(paintingServices).length === 0) return null;

            const paintingBlock = document.createElement('div');
            paintingBlock.id = 'painting-services-block';

            let optionsHTML = '<option value="" selected>Без покраски</option>';
            Object.entries(paintingServices).forEach(([key, service]) => {
                optionsHTML += `<option value="${key}" data-price="${service.price}">${service.name} (+${service.price} ₽/м²)</option>`;
            });

            paintingBlock.innerHTML = `
                <br><h4>Услуги покраски</h4>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 10px;">
                        Выберите услугу покраски:
                        <select id="painting_service_select" style="margin-left: 10px; padding: 5px; width: 100%; background: #fff">
                            ${optionsHTML}
                        </select>
                    </label>
                    <div id="painting-service-result"></div>
                </div>
                <div id="paint-schemes-root"></div>
            `;
            return paintingBlock;
        }

        const paintingBlock = createPaintingServicesBlock();

        // === КАЛЬКУЛЯТОР ПЛОЩАДИ ===
        
        <?php if($pack_area && $is_target): ?>
        console.log('Creating area calculator');
        
        const areaCalc = document.createElement('div');
        areaCalc.id = 'calc-area';
        areaCalc.innerHTML = `
            <br><h4>Расчет количества по площади</h4>
            <div style="margin-bottom: 10px;">
                Площадь ${<?php echo json_encode($unit_text); ?>.replace('упаковку', 'упаковки').replace('лист', 'листа')}: <strong>${<?php echo $pack_area; ?>.toFixed(3)} м²</strong><br>
                Цена за ${<?php echo json_encode($unit_text); ?>}: <strong>${(<?php echo floatval($product->get_price()); ?> * <?php echo $pack_area; ?>).toFixed(2)} ₽</strong>
            </div>
            <label>Введите нужную площадь, м²:
                <input type="number" min="<?php echo $pack_area; ?>" step="0.1" id="calc_area_input" placeholder="1" style="width:100px; margin-left:10px;">
            </label>
            <div id="calc_area_result" style="margin-top:10px;"></div>
        `;
        resultBlock.appendChild(areaCalc);

        if (paintingBlock) {
            areaCalc.appendChild(paintingBlock);
        }

        const areaInput = document.getElementById('calc_area_input');
        const areaResult = document.getElementById('calc_area_result');
        const basePriceM2 = <?php echo floatval($product->get_price()); ?>;
        const packArea = <?php echo $pack_area; ?>;
        const unitForms = <?php echo json_encode($unit_forms); ?>;

        function updateAreaCalc() {
            const area = parseFloat(areaInput.value);
            
            if (!area || area <= 0) {
                areaResult.innerHTML = '';
                removeHiddenFields('custom_area_');
                updatePaintingServiceCost(0);
                return;
            }

            const packs = Math.ceil(area / packArea);
            const totalPrice = packs * basePriceM2 * packArea;
            const totalArea = packs * packArea;
            const plural = getRussianPlural(packs, unitForms);
            
            const paintingCost = updatePaintingServiceCost(totalArea);
            const grandTotal = totalPrice + paintingCost;

            let html = `Нужная площадь: <b>${area.toFixed(2)} м²</b><br>`;
            html += `Необходимо: <b>${packs} ${plural}</b><br>`;
            html += `Стоимость материала: <b>${totalPrice.toFixed(2)} ₽</b><br>`;
            if (paintingCost > 0) {
                html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
                html += `<strong>Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
            } else {
                html += `<strong>Итого: <b>${totalPrice.toFixed(2)} ₽</b></strong>`;
            }
            
            areaResult.innerHTML = html;

            createHiddenField('custom_area_packs', packs);
            createHiddenField('custom_area_area_value', area.toFixed(2));
            createHiddenField('custom_area_total_price', totalPrice.toFixed(2));
            createHiddenField('custom_area_grand_total', grandTotal.toFixed(2));

            if (quantityInput) {
                isAutoUpdate = true;
                quantityInput.value = packs;
                quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
                setTimeout(() => { isAutoUpdate = false; }, 100);
            }
        }
        
        areaInput.addEventListener('input', updateAreaCalc);
        
        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate && areaInput.value) {
                    areaInput.value = '';
                    updateAreaCalc();
                }
            });
            
            quantityInput.addEventListener('change', function() {
                if (!isAutoUpdate) {
                    const packs = parseInt(this.value);
                    if (packs > 0) {
                        const area = packs * packArea;
                        areaInput.value = area.toFixed(2);
                        updateAreaCalc();
                    }
                }
            });
        }
        <?php endif; ?>

        // === КАЛЬКУЛЯТОР РАЗМЕРОВ ===
        
        <?php if($dims && $is_target && !$is_falsebalk): ?>
        console.log('Creating dimensions calculator');
        
        const dimCalc = document.createElement('div');
        dimCalc.id = 'calc-dim';
        let dimHTML = '<br><h4>Расчет по размерам</h4><div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;white-space:nowrap">';
        dimHTML += '<label>Ширина (мм): <select id="custom_width">';
        <?php foreach($dims['widths'] as $w): ?>
            dimHTML += '<option value="<?php echo $w; ?>"><?php echo $w; ?></option>';
        <?php endforeach; ?>
        dimHTML += '</select></label>';
        dimHTML += '<label>Длина (мм): <select id="custom_length">';
        <?php for($l=$dims['length_min']; $l<=$dims['length_max']; $l+=100): ?>
            dimHTML += '<option value="<?php echo $l; ?>"><?php echo $l; ?></option>';
        <?php endfor; ?>
        dimHTML += '</select></label></div><div id="calc_dim_result" style="margin-top:10px; font-size:1.3em"></div>';
        dimCalc.innerHTML = dimHTML;
        resultBlock.appendChild(dimCalc);

        if (paintingBlock && !document.getElementById('calc-area')) {
            dimCalc.appendChild(paintingBlock);
        }

        const widthEl = document.getElementById('custom_width');
        const lengthEl = document.getElementById('custom_length');
        const dimResult = document.getElementById('calc_dim_result');
        const basePriceDim = <?php echo floatval($product->get_price()); ?>;
        let dimInitialized = false;

        function updateDimCalc(userInteraction = false) {
            const width = parseFloat(widthEl.value);
            const length = parseFloat(lengthEl.value);
            const area = (width/1000) * (length/1000);
            const total = area * basePriceDim;
            
            const paintingCost = updatePaintingServiceCost(area);
            const grandTotal = total + paintingCost;

            let html = `Площадь: <b>${area.toFixed(3)} м²</b><br>`;
            html += `Стоимость материала: <b>${total.toFixed(2)} ₽</b><br>`;
            if (paintingCost > 0) {
                html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
                html += `<strong>Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
            } else {
                html += `<strong>Цена: <b>${total.toFixed(2)} ₽</b></strong>`;
            }

            dimResult.innerHTML = html;

            if (userInteraction) {
                createHiddenField('custom_width_val', width);
                createHiddenField('custom_length_val', length);
                createHiddenField('custom_dim_price', total.toFixed(2));
                createHiddenField('custom_dim_grand_total', grandTotal.toFixed(2));

                if (quantityInput) {
                    isAutoUpdate = true;
                    quantityInput.value = 1;
                    quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
                    setTimeout(() => { isAutoUpdate = false; }, 100);
                }
            } else if (!dimInitialized) {
                dimInitialized = true;
            }
        }

        widthEl.addEventListener('change', () => updateDimCalc(true));
        lengthEl.addEventListener('change', () => updateDimCalc(true));
        
        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate && form.querySelector('input[name="custom_width_val"]')) {
                    removeHiddenFields('custom_');
                    removeHiddenFields('painting_service_');
                    widthEl.selectedIndex = 0;
                    lengthEl.selectedIndex = 0;
                    const paintingSelect = document.getElementById('painting_service_select');
                    if (paintingSelect) paintingSelect.selectedIndex = 0;
                    updateDimCalc(false);
                }
            });
        }
        
        updateDimCalc(false);
        <?php endif; ?>

        // === КАЛЬКУЛЯТОР ДЛЯ СТОЛЯРКИ (С ФАСКОЙ) ===
        
        <?php 
        // Проверяем фаску для ПОДОКОННИКОВ (категории 268, 270)
        $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        $show_faska = false;
        $faska_types = array();

        if ($product_cats && !is_wp_error($product_cats)) {
            foreach ($product_cats as $cat_id) {
                // ИСПРАВЛЕНО: проверяем категории 268 и 270 для подоконников
                if (in_array($cat_id, array(268, 270))) {
                    $show_faska = true;
                    $faska_types = get_term_meta($cat_id, 'faska_types', true);
                    if ($faska_types) {
                        error_log('✓ Faska types found for category ' . $cat_id . ': ' . print_r($faska_types, true));
                        break;
                    }
                }
            }
        }
        
        error_log('Show faska: ' . ($show_faska ? 'YES' : 'NO') . ', Types count: ' . count($faska_types));
        ?>

        <?php if($is_multiplier && !$show_falsebalk_calc): ?>
        console.log('Creating multiplier calculator', { showFaska: <?php echo $show_faska ? 'true' : 'false'; ?> });
        
        const multiplierCalc = document.createElement('div');
        multiplierCalc.id = 'calc-multiplier';

        let calcHTML = '<br><h4>Калькулятор стоимости</h4>';
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
                <select id="mult_length" style="margin-left:10px;background:#fff;">
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

        calcHTML += '</div>';

        <?php if ($show_faska && !empty($faska_types)): ?>
        // ИСПРАВЛЕНО: Добавляем выбор фаски для подоконников
        console.log('Adding faska selection');
        
        calcHTML += `<div id="faska_selection" style="margin-top: 10px; display: none;">
            <h5 style="margin-bottom: 10px;">Выберите тип фаски:</h5>
            <div id="faska_grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                <?php foreach ($faska_types as $index => $faska): 
                    if (!empty($faska['name'])): ?>
                <label class="faska-option" style="cursor: pointer; text-align: center; padding: 8px; border: 2px solid #ddd; border-radius: 8px; transition: all 0.3s;">
                    <input type="radio" name="faska_type" value="<?php echo esc_attr($faska['name']); ?>" data-index="<?php echo $index; ?>" data-image="<?php echo esc_url($faska['image']); ?>" style="display: none;">
                    <?php if (!empty($faska['image'])): ?>
                    <img src="<?php echo esc_url($faska['image']); ?>" alt="<?php echo esc_attr($faska['name']); ?>" style="width: 100%; height: 60px; object-fit: contain; margin-bottom: 3px;">
                    <?php endif; ?>
                    <div style="font-size: 11px;"><?php echo esc_html($faska['name']); ?></div>
                </label>
                <?php endif; 
                endforeach; ?>
            </div>
            <div id="faska_selected" style="display: none; margin-top: 20px; text-align: center; padding: 10px; border: 2px solid #4CAF50; border-radius: 8px; background: #f9f9f9;">
                <p>Выбранная фаска: <span id="faska_selected_name"></span></p>
                <img id="faska_selected_image" src="" alt="" style="max-height: 200px;">
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
        @media (max-width: 768px) {
            #faska_grid { grid-template-columns: repeat(3, 1fr) !important; }
        }
        @media (max-width: 480px) {
            #faska_grid { grid-template-columns: repeat(2, 1fr) !important; }
        }
        </style>
        `);
        <?php endif; ?>

        calcHTML += '<div id="calc_mult_result" style="margin-top:10px; font-size:1.3em"></div>';
        multiplierCalc.innerHTML = calcHTML;
        resultBlock.appendChild(multiplierCalc);

        if (paintingBlock) {
            multiplierCalc.appendChild(paintingBlock);
        }

        const multWidthEl = document.getElementById('mult_width');
        const multLengthEl = document.getElementById('mult_length');
        const multResult = document.getElementById('calc_mult_result');
        const basePriceMult = <?php echo floatval($product->get_price()); ?>;

        function updateMultiplierCalc() {
            const widthValue = parseFloat(multWidthEl?.value);
            const lengthValue = parseFloat(multLengthEl?.value);
            const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;

            <?php if ($show_faska): ?>
            // Показываем фаску только если введены размеры
            const faskaSelection = document.getElementById('faska_selection');
            if (faskaSelection) {
                if (widthValue > 0 && lengthValue > 0) {
                    faskaSelection.style.display = 'block';
                    console.log('✓ Faska selection visible');
                } else {
                    faskaSelection.style.display = 'none';
                    const faskaInputs = document.querySelectorAll('input[name="faska_type"]');
                    faskaInputs.forEach(input => input.checked = false);
                    document.getElementById('faska_grid').style.display = 'grid';
                    document.getElementById('faska_selected').style.display = 'none';
                }
            }
            <?php endif; ?>

            if (!widthValue || widthValue <= 0 || !lengthValue || lengthValue <= 0) {
                multResult.innerHTML = '';
                removeHiddenFields('custom_mult_');
                updatePaintingServiceCost(0);
                return;
            }

            const width_m = widthValue / 1000;
            const length_m = lengthValue;
            
            const areaPerItem = width_m * length_m;
            const totalArea = areaPerItem * quantity;
            const pricePerItem = areaPerItem * basePriceMult * priceMultiplier;
            const materialPrice = pricePerItem * quantity;
            
            const paintingCost = updatePaintingServiceCost(totalArea);
            const grandTotal = materialPrice + paintingCost;

            let html = `Площадь 1 шт: <b>${areaPerItem.toFixed(3)} м²</b><br>`;
            html += `Общая площадь: <b>${totalArea.toFixed(3)} м²</b> (${quantity} шт)<br>`;
            html += `Толщина: <b>40мм</b><br>`;
            html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b><br>`;
            html += `Стоимость материала: <b>${materialPrice.toFixed(2)} ₽</b><br>`;
            
            if (paintingCost > 0) {
                html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
                html += `<strong>Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
            } else {
                html += `<strong>Итого: <b>${materialPrice.toFixed(2)} ₽</b></strong>`;
            }

            multResult.innerHTML = html;

            createHiddenField('custom_mult_width', widthValue);
            createHiddenField('custom_mult_length', lengthValue);
            createHiddenField('custom_mult_quantity', quantity);
            createHiddenField('custom_mult_area_per_item', areaPerItem.toFixed(3));
            createHiddenField('custom_mult_total_area', totalArea.toFixed(3));
            createHiddenField('custom_mult_multiplier', priceMultiplier);
            createHiddenField('custom_mult_price', materialPrice.toFixed(2));
            createHiddenField('custom_mult_grand_total', grandTotal.toFixed(2));

            <?php if ($show_faska): ?>
            const selectedFaska = document.querySelector('input[name="faska_type"]:checked');
            if (selectedFaska) {
                createHiddenField('selected_faska_type', selectedFaska.value);
            } else {
                removeHiddenFields('selected_faska_');
            }
            <?php endif; ?>
        }

        if (multWidthEl) multWidthEl.addEventListener('change', updateMultiplierCalc);
        if (multLengthEl) multLengthEl.addEventListener('change', updateMultiplierCalc);

        <?php if ($show_faska): ?>
        // Обработчик фаски
        setTimeout(function() {
            const faskaInputs = document.querySelectorAll('input[name="faska_type"]');
            const faskaGrid = document.getElementById('faska_grid');
            const faskaSelected = document.getElementById('faska_selected');
            const faskaSelectedName = document.getElementById('faska_selected_name');
            const faskaSelectedImage = document.getElementById('faska_selected_image');
            const changeFaskaBtn = document.getElementById('change_faska_btn');
            
            console.log('✓ Setting up faska handlers, inputs found:', faskaInputs.length);
            
            faskaInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.checked) {
                        faskaGrid.style.display = 'none';
                        faskaSelected.style.display = 'block';
                        faskaSelectedName.textContent = this.value;
                        faskaSelectedImage.src = this.dataset.image;
                        faskaSelectedImage.alt = this.value;
                        console.log('✓ Faska selected:', this.value);
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
                if (!isAutoUpdate && multWidthEl && multWidthEl.value && multLengthEl && multLengthEl.value) {
                    updateMultiplierCalc();
                }
            });
        }
        <?php endif; ?>

        // === ФУНКЦИЯ ОБНОВЛЕНИЯ ПОКРАСКИ ===
        
        function updatePaintingServiceCost(totalArea = null) {
            if (!paintingBlock) return 0;
            
            const serviceSelect = document.getElementById('painting_service_select');
            if (!serviceSelect) return 0;
            
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            const paintingResult = document.getElementById('painting-service-result');
            
            if (!paintingResult) return 0;
            
            if (!selectedOption || !selectedOption.value) {
                paintingResult.innerHTML = '';
                removeHiddenFields('painting_service_');
                return 0;
            }
            
            const serviceKey = selectedOption.value;
            const servicePrice = parseFloat(selectedOption.dataset.price);
            
            if (!totalArea) {
                paintingResult.innerHTML = `Выбрана услуга: ${paintingServices[serviceKey].name}`;
                return 0;
            }
            
            const totalPaintingCost = totalArea * servicePrice;
            paintingResult.innerHTML = `${paintingServices[serviceKey].name}: ${totalPaintingCost.toFixed(2)} ₽ (${totalArea.toFixed(3)} м² × ${servicePrice} ₽/м²)`;
            
            createHiddenField('painting_service_key', serviceKey);
            createHiddenField('painting_service_name', paintingServices[serviceKey].name);
            createHiddenField('painting_service_price_per_m2', servicePrice);
            createHiddenField('painting_service_area', totalArea.toFixed(3));
            createHiddenField('painting_service_total_cost', totalPaintingCost.toFixed(2));
            
            return totalPaintingCost;
        }

        // Обработчик услуг покраски
        if (paintingBlock) {
            const serviceSelect = document.getElementById('painting_service_select');
            if (serviceSelect) {
                serviceSelect.addEventListener('change', function() {
                    const areaInput = document.getElementById('calc_area_input');
                    const widthEl = document.getElementById('custom_width');
                    const lengthEl = document.getElementById('custom_length');
                    const multWidthEl = document.getElementById('mult_width');
                    const multLengthEl = document.getElementById('mult_length');

                    if (areaInput && areaInput.value) {
                        updateAreaCalc();
                        return;
                    }

                    if (widthEl && lengthEl) {
                        const width = parseFloat(widthEl.value);
                        const length = parseFloat(lengthEl.value);
                        if (width > 0 && length > 0) {
                            updateDimCalc(true);
                            return;
                        }
                    }

                    if (multWidthEl && multLengthEl) {
                        const width = parseFloat(multWidthEl.value);
                        const length = parseFloat(multLengthEl.value);
                        if (width > 0 && length > 0) {
                            updateMultiplierCalc();
                            return;
                        }
                    }

                    updatePaintingServiceCost(0);
                });
            }
        }
        
        // Обработчик выбора цвета покраски
        document.addEventListener('change', function(e) {
            if (e.target.name === 'pm_selected_color') {
                console.log('Paint color changed, recalculating...');
                
                const areaInput = document.getElementById('calc_area_input');
                const widthEl = document.getElementById('custom_width');
                const lengthEl = document.getElementById('custom_length');
                const multWidthEl = document.getElementById('mult_width');
                const multLengthEl = document.getElementById('mult_length');
                
                if (areaInput && areaInput.value) {
                    updateAreaCalc();
                } else if (widthEl && lengthEl && widthEl.value && lengthEl.value) {
                    updateDimCalc(true);
                } else if (multWidthEl && multLengthEl && multWidthEl.value && multLengthEl.value) {
                    updateMultiplierCalc();
                }
            }
        });
        
        console.log('✅ ParusWeb Calculators v2.0 - Initialized successfully');
    });
    </script>
    <?php
}, 20);
