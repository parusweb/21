<?php
/**
 * Модуль: Frontend Calculators
 * Описание: Калькуляторы для страницы товара WooCommerce
 * Зависимости: product-calculations, category-helpers
 */

if (!defined('ABSPATH')) {
    exit;
}

// === ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ===

/**
 * Извлечение размеров из названия товара
 */
if (!function_exists('extract_dimensions_from_title')) {
    function extract_dimensions_from_title($title) {
        if (preg_match('/\d+\/(\d+)(?:\((\d+)\))?\/(\d+)-(\d+)/u', $title, $m)) {
            $widths = array($m[1]);
            if (!empty($m[2])) {
                $widths[] = $m[2];
            }
            $length_min = (int)$m[3];
            $length_max = (int)$m[4];
            return array(
                'widths' => $widths, 
                'length_min' => $length_min, 
                'length_max' => $length_max
            );
        }
        return null;
    }
}

/**
 * Получение услуг покраски для товара
 */
if (!function_exists('get_available_painting_services_by_material')) {
    function get_available_painting_services_by_material($product_id) {
        if (function_exists('get_acf_painting_services')) {
            $acf_services = get_acf_painting_services($product_id);
            $formatted_services = array();
            
            if (empty($acf_services) || !is_array($acf_services)) {
                return $formatted_services;
            }
            
            foreach ($acf_services as $index => $service) {
                if (empty($service['name_usluga'])) {
                    continue;
                }
                
                $key = 'service_' . sanitize_title($service['name_usluga']);
                $formatted_services[$key] = array(
                    'name' => $service['name_usluga'],
                    'price' => floatval($service['price_usluga'] ?? 0)
                );
            }
            
            return $formatted_services;
        }
        
        return array();
    }
}

// === КАЛЬКУЛЯТОР НА СТРАНИЦЕ ТОВАРА ===

add_action('wp_footer', function () {
    if (!is_product()) return;
    
    global $product;
    $product_id = $product->get_id();
    
    $is_target = is_in_target_categories($product->get_id());
    $is_multiplier = is_in_multiplier_categories($product->get_id());
    $is_square_meter = is_square_meter_category($product->get_id());
    $is_running_meter = is_running_meter_category($product->get_id());
    
    // Проверка фальшбалок
    $show_falsebalk_calc = false;
    $is_falsebalk = false;
    $shapes_data = array();
    
    if (has_term(266, 'product_cat', $product->get_id())) {
        $is_falsebalk = true;
        $shapes_data = get_post_meta($product->get_id(), '_falsebalk_shapes_data', true);
        
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
                    $has_old_format = !empty($shape_info['widths']) || 
                                     !empty($shape_info['heights']) || 
                                     !empty($shape_info['lengths']);
                    
                    if ($has_width || $has_height || $has_length || $has_old_format) {
                        $show_falsebalk_calc = true;
                        break;
                    }
                }
            }
        }
    }
    
    if (!$is_target && !$is_multiplier) {
        return;
    }
    
    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product->get_id());
    $dims = extract_dimensions_from_title($title);
    
    // Получаем доступные услуги покраски
    $painting_services = get_available_painting_services_by_material($product->get_id());
    
    // Получаем множитель цены
    $price_multiplier = function_exists('get_price_multiplier') ? get_price_multiplier($product->get_id()) : 1.0;
    
    // Получаем настройки калькулятора
    $calc_settings = null;
    if ($is_multiplier) {
        $calc_settings = array(
            'width_min' => floatval(get_post_meta($product->get_id(), '_calc_width_min', true)),
            'width_max' => floatval(get_post_meta($product->get_id(), '_calc_width_max', true)),
            'width_step' => floatval(get_post_meta($product->get_id(), '_calc_width_step', true)) ?: 100,
            'length_min' => floatval(get_post_meta($product->get_id(), '_calc_length_min', true)),
            'length_max' => floatval(get_post_meta($product->get_id(), '_calc_length_max', true)),
            'length_step' => floatval(get_post_meta($product->get_id(), '_calc_length_step', true)) ?: 0.01,
        );
    }
    
    // Определяем единицу измерения
    $leaf_parent_id = 190;
    $leaf_children = array(191, 127, 94);
    $leaf_ids = array_merge(array($leaf_parent_id), $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
    $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
    $unit_forms = $is_leaf_category ? array('лист', 'листа', 'листов') : array('упаковка', 'упаковки', 'упаковок');
    ?>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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

        let isAutoUpdate = false;
        
        const paintingServices = <?php echo !empty($painting_services) ? json_encode($painting_services) : '{}'; ?>;
        const priceMultiplier = <?php echo $price_multiplier; ?>;
        const isMultiplierCategory = <?php echo $is_multiplier ? 'true' : 'false'; ?>;
        const isSquareMeter = <?php echo $is_square_meter ? 'true' : 'false'; ?>;
        const isRunningMeter = <?php echo $is_running_meter ? 'true' : 'false'; ?>;
        const calcSettings = <?php echo $calc_settings ? json_encode($calc_settings) : 'null'; ?>;

        console.log('=== CALCULATOR INIT ===');
        console.log('Painting services:', paintingServices);
        console.log('Price multiplier:', priceMultiplier);

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

        function createPaintingServicesBlock(currentCategoryId) {
            if (Object.keys(paintingServices).length === 0) return null;

            const paintingBlock = document.createElement('div');
            paintingBlock.id = 'painting-services-block';

            let optionsHTML = '<option value="" selected>Без покраски</option>';
            Object.entries(paintingServices).forEach(([key, service]) => {
                let optionText = service.name;
                if (currentCategoryId < 265 || currentCategoryId > 271) {
                    optionText += ` (+${service.price} ₽/м²)`;
                }
                optionsHTML += `<option value="${key}" data-price="${service.price}">${optionText}</option>`;
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
                    <div id="painting-service-result" style="display:none;"></div>
                </div>
                <div id="paint-schemes-root"></div>
            `;
            return paintingBlock;
        }

        let currentCategoryId = 0;
        <?php 
        $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        if (!empty($product_cats)) {
            echo 'currentCategoryId = ' . intval($product_cats[0]) . ';';
        }
        ?>
        
        const paintingBlock = createPaintingServicesBlock(currentCategoryId);

        <?php if($pack_area && $is_target): ?>
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

        <?php if($dims && $is_target && !$is_falsebalk): ?>
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

        <?php 
        $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        $show_faska = false;
        $faska_types = array();

        if ($product_cats && !is_wp_error($product_cats)) {
            foreach ($product_cats as $cat_id) {
                if (in_array($cat_id, array(268, 270))) {
                    $show_faska = true;
                    $faska_types = get_term_meta($cat_id, 'faska_types', true);
                    if ($faska_types) break;
                }
            }
        }
        ?>

        <?php if($is_multiplier && !$show_falsebalk_calc && !$is_running_meter): ?>
        const multiplierCalc = document.createElement('div');
        multiplierCalc.id = 'calc-multiplier';

        let calcHTML = '<br><h4>Калькулятор стоимости</h4>';
        calcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

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

        if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
            calcHTML += `<label>Длина (м): 
                <select id="mult_length" style="margin-left:10px;background:#fff;">
                    <option value="">Выберите...</option>`;
            for (let l = calcSettings.length_min; l <= calcSettings.length_max; l += calcSettings.length_step) {
                calcHTML += `<option value="${l.toFixed(2)}">${l.toFixed(2)}</option>`;
            }
            calcHTML += `</select></label>`;
        } else {
            calcHTML += `<label>Длина (м): 
                <input type="number" id="mult_length" min="0.01" step="0.01" placeholder="0.01" style="width:100px; margin-left:10px;">
            </label>`;
        }

        calcHTML += `<label style="display:none">Количество (шт): <span id="mult_quantity_display">1</span></label>`;
        calcHTML += '</div>';

        <?php if ($show_faska && !empty($faska_types)): ?>
        calcHTML += `<div id="faska_selection" style="margin-top: 10px; display: none;">
            <h5>Выберите тип фаски:</h5>
            <div id="faska_grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 10px;">
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
        </div>`;
        <?php endif; ?>

        calcHTML += '<div id="calc_mult_result" style="margin-top:10px; font-size:1.3em"></div>';
        multiplierCalc.innerHTML = calcHTML;
        resultBlock.appendChild(multiplierCalc);

        if (paintingBlock) {
            multiplierCalc.appendChild(paintingBlock);
        }

        const multWidthEl = document.getElementById('mult_width');
        const multLengthEl = document.getElementById('mult_length');
        const multQuantityDisplay = document.getElementById('mult_quantity_display');
        const multResult = document.getElementById('calc_mult_result');
        const basePriceMult = <?php echo floatval($product->get_price()); ?>;

        function updateMultiplierCalc() {
            const widthValue = parseFloat(multWidthEl?.value);
            const lengthValue = parseFloat(multLengthEl?.value);
            const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;
            
            if (multQuantityDisplay) {
                multQuantityDisplay.textContent = quantity;
            }

            <?php if ($show_faska): ?>
            const faskaSelection = document.getElementById('faska_selection');
            if (faskaSelection) {
                if (widthValue > 0 && lengthValue > 0) {
                    faskaSelection.style.display = 'block';
                } else {
                    faskaSelection.style.display = 'none';
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
            }
            <?php endif; ?>
        }

        if (multWidthEl) multWidthEl.addEventListener('change', updateMultiplierCalc);
        if (multLengthEl) multLengthEl.addEventListener('change', updateMultiplierCalc);

        if (quantityInput) {
            quantityInput.addEventListener('change', function() {
                if (!isAutoUpdate && multWidthEl?.value && multLengthEl?.value) {
                    updateMultiplierCalc();
                }
            });
        }
        <?php endif; ?>

        <?php if($is_running_meter): ?>
        <?php 
        $show_falsebalk_calculator = $show_falsebalk_calc;
        if ($show_falsebalk_calculator && !is_array($shapes_data)) {
            $shapes_data = array();
        }
        ?>
        
        <?php if ($show_falsebalk_calculator): ?>
        if (resultBlock) {
            resultBlock.innerHTML = '';
        }
        <?php endif; ?>
        
        const runningMeterCalc = document.createElement('div');
        runningMeterCalc.id = 'calc-running-meter';
        let rmCalcHTML = '<br><h4>Калькулятор стоимости</h4>';

        <?php if ($show_falsebalk_calculator): ?>
        const shapesData = <?php echo json_encode($shapes_data); ?>;
        
        <?php 
        $shape_icons = array(
            'g' => '<svg width="60" height="60" viewBox="0 0 60 60"><rect x="5" y="5" width="10" height="50" fill="#000"/><rect x="5" y="45" width="50" height="10" fill="#000"/></svg>',
            'p' => '<svg width="60" height="60" viewBox="0 0 60 60"><rect x="5" y="5" width="10" height="50" fill="#000"/><rect x="45" y="5" width="10" height="50" fill="#000"/><rect x="5" y="5" width="50" height="10" fill="#000"/></svg>',
            'o' => '<svg width="60" height="60" viewBox="0 0 60 60"><rect x="5" y="5" width="50" height="50" fill="none" stroke="#000" stroke-width="10"/></svg>'
        );
        $shape_labels = array(
            'g' => 'Г-образная',
            'p' => 'П-образная',
            'o' => 'О-образная'
        );
        $shapes_buttons_html = '';
        foreach ($shapes_data as $shape_key => $shape_info):
            if (is_array($shape_info) && !empty($shape_info['enabled'])):
                $shape_label = isset($shape_labels[$shape_key]) ? $shape_labels[$shape_key] : ucfirst($shape_key);
                $shapes_buttons_html .= '<label class="shape-tile" data-shape="' . esc_attr($shape_key) . '" style="cursor:pointer; border:2px solid #ccc; border-radius:10px; padding:10px; background:#fff; display:flex; flex-direction:column; align-items:center; gap:8px; transition:all .2s; min-width:100px;">';
                $shapes_buttons_html .= '<input type="radio" name="falsebalk_shape" value="' . esc_attr($shape_key) . '" style="display:none;">';
                $shapes_buttons_html .= '<div>' . $shape_icons[$shape_key] . '</div>';
                $shapes_buttons_html .= '<span style="font-size:12px; color:#666;">' . esc_html($shape_label) . '</span>';
                $shapes_buttons_html .= '</label>';
            endif;
        endforeach;
        ?>

        rmCalcHTML += '<div style="margin-bottom:20px; border:2px solid #e0e0e0; padding:15px; border-radius:8px;">';
        rmCalcHTML += '<label style="display:block; margin-bottom:15px; font-weight:600;">Шаг 1: Выберите форму сечения</label>';
        rmCalcHTML += '<div style="display:flex; gap:15px; flex-wrap:wrap;">';
        rmCalcHTML += <?php echo json_encode($shapes_buttons_html); ?>;
        rmCalcHTML += '</div></div>';

        rmCalcHTML += '<div id="falsebalk_params" style="display:none; margin-bottom:20px; border:2px solid #e0e0e0; padding:15px; border-radius:8px;">';
        rmCalcHTML += '<label style="display:block; margin-bottom:15px; font-weight:600;">Шаг 2: Выберите размеры</label>';
        rmCalcHTML += '<div style="display:flex; gap:20px; flex-wrap:wrap;">';
        rmCalcHTML += '<label><span>Ширина (мм):</span><select id="rm_width" style="background:#fff; padding:8px;"><option value="">Выберите форму</option></select></label>';
        rmCalcHTML += '<div id="height_container"></div>';
        rmCalcHTML += '<label><span>Длина (м):</span><select id="rm_length" style="background:#fff; padding:8px;"><option value="">Выберите форму</option></select></label>';
        rmCalcHTML += '<label style="display:none"><span>Количество:</span><span id="rm_quantity_display">1</span></label>';
        rmCalcHTML += '</div></div>';
        rmCalcHTML += '<div id="calc_rm_result"></div>';

        <?php else: ?>
        rmCalcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;">';
        rmCalcHTML += '<label>Ширина (мм): <input type="number" id="rm_width" min="1" step="100" placeholder="100" style="width:100px; margin-left:10px;background:#fff"></label>';
        rmCalcHTML += '<label>Длина (пог. м): <input type="number" id="rm_length" min="0.1" step="0.1" placeholder="2.0" style="width:100px; margin-left:10px;background:#fff"></label>';
        rmCalcHTML += '<label style="display:none">Количество: <span id="rm_quantity_display">1</span></label>';
        rmCalcHTML += '</div>';
        rmCalcHTML += '<div id="calc_rm_result" style="margin-top:10px;"></div>';
        <?php endif; ?>

        runningMeterCalc.innerHTML = rmCalcHTML;
        resultBlock.appendChild(runningMeterCalc);

        if (paintingBlock) {
            runningMeterCalc.appendChild(paintingBlock);
        }

        <?php if ($show_falsebalk_calculator): ?>
        function generateOptions(min, max, step, unit = '') {
            const options = ['<option value="">Выберите...</option>'];
            if (!min || !max || !step || min > max) return options.join('');
            const stepsCount = Math.round((max - min) / step) + 1;
            for (let i = 0; i < stepsCount; i++) {
                const value = min + (i * step);
                const displayValue = unit === 'м' ? value.toFixed(2) : Math.round(value);
                options.push(`<option value="${displayValue}">${displayValue}${unit}</option>`);
            }
            return options.join('');
        }

        const rmWidthEl = document.getElementById('rm_width');
        const heightContainer = document.getElementById('height_container');
        const rmLengthEl = document.getElementById('rm_length');

        function updateDimensions(selectedShape) {
            const shapeData = shapesData[selectedShape];
            if (!shapeData?.enabled) return;
            
            document.getElementById('falsebalk_params').style.display = 'block';
            
            rmWidthEl.innerHTML = generateOptions(shapeData.width_min, shapeData.width_max, shapeData.width_step, 'мм');
            
            if (selectedShape === 'p') {
                heightContainer.innerHTML = `
                    <label><span>Высота 1 (мм):</span><select id="rm_height1" style="background:#fff; padding:8px;">${generateOptions(shapeData.height1_min, shapeData.height1_max, shapeData.height1_step, 'мм')}</select></label>
                    <label><span>Высота 2 (мм):</span><select id="rm_height2" style="background:#fff; padding:8px;">${generateOptions(shapeData.height2_min, shapeData.height2_max, shapeData.height2_step, 'мм')}</select></label>
                `;
                document.getElementById('rm_height1').addEventListener('change', updateRunningMeterCalc);
                document.getElementById('rm_height2').addEventListener('change', updateRunningMeterCalc);
            } else {
                heightContainer.innerHTML = `<label><span>Высота (мм):</span><select id="rm_height" style="background:#fff; padding:8px;">${generateOptions(shapeData.height_min, shapeData.height_max, shapeData.height_step, 'мм')}</select></label>`;
                document.getElementById('rm_height').addEventListener('change', updateRunningMeterCalc);
            }
            
            rmLengthEl.innerHTML = generateOptions(shapeData.length_min, shapeData.length_max, shapeData.length_step, 'м');
            document.getElementById('calc_rm_result').innerHTML = '';
        }

        document.querySelectorAll('.shape-tile').forEach(tile => {
            tile.addEventListener('click', function() {
                document.querySelectorAll('.shape-tile').forEach(t => {
                    t.style.borderColor = '#ccc';
                    t.style.boxShadow = 'none';
                });
                this.style.borderColor = '#3aa655';
                this.style.boxShadow = '0 0 0 3px rgba(58,166,85,0.3)';
                const radio = this.querySelector('input[name="falsebalk_shape"]');
                if (radio) {
                    radio.checked = true;
                    updateDimensions(radio.value);
                }
            });
        });
        <?php else: ?>
        const rmWidthEl = document.getElementById('rm_width');
        const rmLengthEl = document.getElementById('rm_length');
        <?php endif; ?>

        const rmQuantityDisplay = document.getElementById('rm_quantity_display');
        const rmResult = document.getElementById('calc_rm_result');
        const basePriceRM = <?php echo floatval($product->get_price()); ?>;

        function updateRunningMeterCalc() {
            <?php if ($show_falsebalk_calculator): ?>
            const selectedShape = document.querySelector('input[name="falsebalk_shape"]:checked');
            if (!selectedShape) {
                rmResult.innerHTML = '<span style="color: #999;">⬆️ Выберите форму сечения</span>';
                return;
            }
            
            const widthValue = parseFloat(rmWidthEl?.value) || 0;
            const lengthValue = parseFloat(rmLengthEl?.value) || 0;
            let heightValue = 0;
            let height2Value = 0;
            
            if (selectedShape.value === 'p') {
                heightValue = parseFloat(document.getElementById('rm_height1')?.value) || 0;
                height2Value = parseFloat(document.getElementById('rm_height2')?.value) || 0;
            } else {
                heightValue = parseFloat(document.getElementById('rm_height')?.value) || 0;
            }
            <?php else: ?>
            const widthValue = parseFloat(rmWidthEl?.value) || 0;
            const lengthValue = parseFloat(rmLengthEl?.value) || 0;
            <?php endif; ?>

            const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;
            if (rmQuantityDisplay) {
                rmQuantityDisplay.textContent = quantity;
            }

            if (!lengthValue || lengthValue <= 0) {
                rmResult.innerHTML = '';
                removeHiddenFields('custom_rm_');
                updatePaintingServiceCost(0);
                return;
            }

            const totalLength = lengthValue * quantity;
            let paintingArea = 0;
            
            if (widthValue > 0) {
                const width_m = widthValue / 1000;
                const height_m = (typeof heightValue !== 'undefined' ? heightValue : 0) / 1000;
                const height2_m = (typeof height2Value !== 'undefined' ? height2Value : 0) / 1000;

                <?php if ($show_falsebalk_calculator): ?>
                const shapeKey = selectedShape.value;
                if (shapeKey === 'g') {
                    paintingArea = (width_m + height_m) * totalLength;
                } else if (shapeKey === 'p') {
                    paintingArea = (width_m + height_m + height2_m) * totalLength;
                } else if (shapeKey === 'o') {
                    paintingArea = 2 * (width_m + height_m) * totalLength;
                } else {
                    paintingArea = width_m * totalLength;
                }
                <?php else: ?>
                paintingArea = width_m * totalLength;
                <?php endif; ?>
            }

            const materialPrice = paintingArea * basePriceRM * priceMultiplier;
            const pricePerItem = (quantity > 0) ? (materialPrice / quantity) : 0;
            const paintingCost = updatePaintingServiceCost(paintingArea);
            const grandTotal = materialPrice + paintingCost;

            let html = `Длина 1 шт: <b>${lengthValue.toFixed(2)} пог. м</b><br>`;
            html += `Общая длина: <b>${totalLength.toFixed(2)} пог. м</b> (${quantity} шт)<br>`;
            html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b><br>`;
            html += `Стоимость материала: <b>${materialPrice.toFixed(2)} ₽</b><br>`;
            
            if (paintingCost > 0) {
                html += `Площадь покраски: <b>${paintingArea.toFixed(3)} м²</b><br>`;
                html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
                html += `<strong>Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
            } else {
                html += `<strong>Итого: <b>${materialPrice.toFixed(2)} ₽</b></strong>`;
            }

            rmResult.innerHTML = html;

            createHiddenField('custom_rm_length', lengthValue);
            createHiddenField('custom_rm_quantity', quantity);
            createHiddenField('custom_rm_total_length', totalLength.toFixed(2));
            createHiddenField('custom_rm_painting_area', paintingArea.toFixed(3));
            createHiddenField('custom_rm_multiplier', priceMultiplier);
            createHiddenField('custom_rm_price', materialPrice.toFixed(2));
            createHiddenField('custom_rm_grand_total', grandTotal.toFixed(2));
        }

        if (rmWidthEl) rmWidthEl.addEventListener('change', updateRunningMeterCalc);
        if (rmLengthEl) rmLengthEl.addEventListener('change', updateRunningMeterCalc);

        if (quantityInput) {
            quantityInput.addEventListener('change', function() {
                if (!isAutoUpdate && rmLengthEl?.value) {
                    updateRunningMeterCalc();
                }
            });
        }
        <?php endif; ?>

        <?php if($is_square_meter && !$is_running_meter): ?>
        const sqMeterCalc = document.createElement('div');
        sqMeterCalc.id = 'calc-square-meter';
        let sqCalcHTML = '<br><h4>Калькулятор стоимости</h4>';
        sqCalcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;">';

        if (calcSettings && calcSettings.width_min > 0 && calcSettings.width_max > 0) {
            sqCalcHTML += `<label>Ширина (мм): <select id="sq_width" style="background:#fff;margin-left:10px;"><option value="">Выберите...</option>`;
            for (let w = calcSettings.width_min; w <= calcSettings.width_max; w += calcSettings.width_step) {
                sqCalcHTML += `<option value="${w}">${w}</option>`;
            }
            sqCalcHTML += `</select></label>`;
        } else {
            sqCalcHTML += `<label>Ширина (мм): <input type="number" id="sq_width" min="1" step="100" placeholder="1000" style="width:100px; margin-left:10px;background:#fff"></label>`;
        }

        if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
            sqCalcHTML += `<label>Длина (м): <select id="sq_length" style="margin-left:10px;background:#fff;"><option value="">Выберите...</option>`;
            const stepsCount = Math.round((calcSettings.length_max - calcSettings.length_min) / calcSettings.length_step) + 1;
            for (let i = 0; i < stepsCount; i++) {
                const value = calcSettings.length_min + (i * calcSettings.length_step);
                const displayValue = value.toFixed(2);
                sqCalcHTML += `<option value="${displayValue}">${displayValue}</option>`;
            }
            sqCalcHTML += `</select></label>`;
        } else {
            sqCalcHTML += `<label>Длина (м): <input type="number" id="sq_length" min="0.01" step="0.01" placeholder="0.01" style="width:100px; margin-left:10px;"></label>`;
        }

        sqCalcHTML += '<label style="display:none">Количество: <span id="sq_quantity_display">1</span></label>';
        sqCalcHTML += '</div><div id="calc_sq_result" style="margin-top:10px; font-size:1.3em"></div>';
        sqMeterCalc.innerHTML = sqCalcHTML;
        resultBlock.appendChild(sqMeterCalc);

        if (paintingBlock) {
            sqMeterCalc.appendChild(paintingBlock);
        }

        const sqWidthEl = document.getElementById('sq_width');
        const sqLengthEl = document.getElementById('sq_length');
        const sqQuantityDisplay = document.getElementById('sq_quantity_display');
        const sqResult = document.getElementById('calc_sq_result');
        const basePriceSQ = <?php echo floatval($product->get_price()); ?>;

        function updateSquareMeterCalc() {
            const widthValue = parseFloat(sqWidthEl?.value);
            const lengthValue = parseFloat(sqLengthEl?.value);
            const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;

            if (sqQuantityDisplay) {
                sqQuantityDisplay.textContent = quantity;
            }

            if (!widthValue || widthValue <= 0 || !lengthValue || lengthValue <= 0) {
                sqResult.innerHTML = '';
                removeHiddenFields('custom_sq_');
                updatePaintingServiceCost(0);
                return;
            }

            const width_m = widthValue / 1000;
            const areaPerItem = width_m * lengthValue;
            const totalArea = areaPerItem * quantity;
            const pricePerItem = areaPerItem * basePriceSQ * priceMultiplier;
            const materialPrice = pricePerItem * quantity;
            const paintingCost = updatePaintingServiceCost(totalArea);
            const grandTotal = materialPrice + paintingCost;

            let html = `Площадь 1 шт: <b>${areaPerItem.toFixed(3)} м²</b><br>`;
            html += `Общая площадь: <b>${totalArea.toFixed(3)} м²</b> (${quantity} шт)<br>`;
            html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b><br>`;
            html += `Стоимость материала: <b>${materialPrice.toFixed(2)} ₽</b><br>`;
            
            if (paintingCost > 0) {
                html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
                html += `<strong>Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
            } else {
                html += `<strong>Итого: <b>${materialPrice.toFixed(2)} ₽</b></strong>`;
            }

            sqResult.innerHTML = html;

            createHiddenField('custom_sq_width', widthValue);
            createHiddenField('custom_sq_length', lengthValue);
            createHiddenField('custom_sq_quantity', quantity);
            createHiddenField('custom_sq_area_per_item', areaPerItem.toFixed(3));
            createHiddenField('custom_sq_total_area', totalArea.toFixed(3));
            createHiddenField('custom_sq_multiplier', priceMultiplier);
            createHiddenField('custom_sq_price', materialPrice.toFixed(2));
            createHiddenField('custom_sq_grand_total', grandTotal.toFixed(2));
        }

        if (sqWidthEl) sqWidthEl.addEventListener('change', updateSquareMeterCalc);
        if (sqLengthEl) sqLengthEl.addEventListener('change', updateSquareMeterCalc);

        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate && sqWidthEl?.value && sqLengthEl?.value) {
                    updateSquareMeterCalc();
                }
            });
        }
        <?php endif; ?>

        function updatePaintingServiceCost(totalArea = null) {
            if (!paintingBlock) return 0;
            
            const serviceSelect = document.getElementById('painting_service_select');
            if (!serviceSelect) return 0;
            
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            const paintingResult = document.getElementById('painting-service-result');
            
            if (!selectedOption || !selectedOption.value) {
                if (paintingResult) paintingResult.innerHTML = '';
                removeHiddenFields('painting_service_');
                return 0;
            }
            
            const serviceKey = selectedOption.value;
            const servicePrice = parseFloat(selectedOption.dataset.price);
            
            if (!totalArea) {
                if (paintingResult) paintingResult.innerHTML = `Выбрана услуга: ${paintingServices[serviceKey].name}`;
                return 0;
            }
            
            const totalPaintingCost = totalArea * servicePrice;
            if (paintingResult) {
                paintingResult.innerHTML = `${paintingServices[serviceKey].name}: ${totalPaintingCost.toFixed(2)} ₽ (${totalArea.toFixed(3)} м² × ${servicePrice} ₽/м²)`;
            }
            
            createHiddenField('painting_service_key', serviceKey);
            createHiddenField('painting_service_name', paintingServices[serviceKey].name);
            createHiddenField('painting_service_price_per_m2', servicePrice);
            createHiddenField('painting_service_area', totalArea.toFixed(3));
            createHiddenField('painting_service_total_cost', totalPaintingCost.toFixed(2));
            
            return totalPaintingCost;
        }

        if (paintingBlock) {
            const serviceSelect = document.getElementById('painting_service_select');
            if (serviceSelect) {
                serviceSelect.addEventListener('change', function() {
                    const areaInput = document.getElementById('calc_area_input');
                    const widthEl = document.getElementById('custom_width');
                    const lengthEl = document.getElementById('custom_length');
                    const multWidthEl = document.getElementById('mult_width');
                    const multLengthEl = document.getElementById('mult_length');
                    const rmLengthEl = document.getElementById('rm_length');
                    const sqWidthEl = document.getElementById('sq_width');
                    const sqLengthEl = document.getElementById('sq_length');

                    if (areaInput?.value) {
                        updateAreaCalc();
                    } else if (widthEl?.value && lengthEl?.value) {
                        updateDimCalc(true);
                    } else if (multWidthEl?.value && multLengthEl?.value) {
                        updateMultiplierCalc();
                    } else if (rmLengthEl?.value) {
                        updateRunningMeterCalc();
                    } else if (sqWidthEl?.value && sqLengthEl?.value) {
                        updateSquareMeterCalc();
                    } else {
                        updatePaintingServiceCost(0);
                    }
                });
            }
        }

        document.addEventListener('change', function(e) {
            if (e.target.name === 'pm_selected_color') {
                const areaInput = document.getElementById('calc_area_input');
                const widthEl = document.getElementById('custom_width');
                const lengthEl = document.getElementById('custom_length');
                const multWidthEl = document.getElementById('mult_width');
                const multLengthEl = document.getElementById('mult_length');
                const rmLengthEl = document.getElementById('rm_length');
                const sqWidthEl = document.getElementById('sq_width');
                const sqLengthEl = document.getElementById('sq_length');
                
                if (areaInput?.value) {
                    updateAreaCalc();
                } else if (widthEl?.value && lengthEl?.value) {
                    updateDimCalc(true);
                } else if (multWidthEl?.value && multLengthEl?.value) {
                    updateMultiplierCalc();
                } else if (rmLengthEl?.value) {
                    updateRunningMeterCalc();
                } else if (sqWidthEl?.value && sqLengthEl?.value) {
                    updateSquareMeterCalc();
                }
            }
        });
    });
    </script>
    <?php
}, 20);
