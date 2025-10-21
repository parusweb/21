<?php
/**
 * Модуль: Frontend Calculators
 * Описание: ПОЛНЫЙ JavaScript функционал калькуляторов из оригинального functions.php
 * Зависимости: product-calculations, category-helpers
 * 
 * ВАЖНО: Этот модуль содержит ВСЁ из строк 426-1289 оригинального functions_full_old.php
 */

if (!defined('ABSPATH')) {
    exit;
}


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

        // Находим услугу покраски по материалу
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



// --- Калькулятор площади и размеров ---
add_action('wp_footer', function () {
    if (!is_product()) return;
    
    global $product;
    $product_id = $product->get_id();

    // ОТЛАДКА: Базовая информация
    error_log('=== FALSEBALK DEBUG START ===');
    error_log('Product ID: ' . $product_id);
    error_log('Product Name: ' . $product->get_name());
    
    // Получаем все категории товара
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
    
    // ВАЖНО: Проверяем фальшбалки ДО использования переменной в условиях
    $show_falsebalk_calc = false;
    $is_falsebalk = false;
    $shapes_data = array();
    
    if ($is_square_meter) {
        error_log('Checking for falsebalk category (266)...');
        
        // Функция для проверки категории с учетом иерархии
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
            error_log('Shapes data content: ' . print_r($shapes_data, true));
            
            if (is_array($shapes_data)) {
                foreach ($shapes_data as $shape_key => $shape_info) {
                    error_log("Checking shape: {$shape_key}");
                    error_log("Shape info: " . print_r($shape_info, true));
                    
                    if (is_array($shape_info)) {
                        $enabled = !empty($shape_info['enabled']);
                        error_log("Shape {$shape_key} enabled: " . ($enabled ? 'YES' : 'NO'));
                        
                        if ($enabled) {
                            // Проверяем новый формат (min/max/step)
                            $has_width = !empty($shape_info['width_min']) || !empty($shape_info['width_max']);
                            error_log("Has width: " . ($has_width ? 'YES' : 'NO'));
                            
                            // Проверяем высоту в зависимости от формы
                            $has_height = false;
                            if ($shape_key === 'p') {
                                $has_height = !empty($shape_info['height1_min']) || !empty($shape_info['height1_max']) ||
                                             !empty($shape_info['height2_min']) || !empty($shape_info['height2_max']);
                                error_log("Has height (P-shape, two heights): " . ($has_height ? 'YES' : 'NO'));
                            } else {
                                $has_height = !empty($shape_info['height_min']) || !empty($shape_info['height_max']);
                                error_log("Has height (G/O-shape, one height): " . ($has_height ? 'YES' : 'NO'));
                            }
                            
                            $has_length = !empty($shape_info['length_min']) || !empty($shape_info['length_max']);
                            error_log("Has length: " . ($has_length ? 'YES' : 'NO'));
                            
                            // Также поддерживаем старый формат
                            $has_old_format = !empty($shape_info['widths']) || 
                                             !empty($shape_info['heights']) || 
                                             !empty($shape_info['lengths']);
                            error_log("Has old format: " . ($has_old_format ? 'YES' : 'NO'));
                            
                            if ($has_width || $has_height || $has_length || $has_old_format) {
                                $show_falsebalk_calc = true;
                                error_log("✓ Falsebalk calculator ENABLED for shape: {$shape_key}");
                                break;
                            } else {
                                error_log("✗ Shape {$shape_key} has no valid dimensions");
                            }
                        }
                    }
                }
            } else {
                error_log('Shapes data is NOT an array or is empty');
            }
        }
    }
    
    error_log('Final show_falsebalk_calc: ' . ($show_falsebalk_calc ? 'YES' : 'NO'));
    error_log('=== FALSEBALK DEBUG END ===');
    
    if (!$is_target && !$is_multiplier) {
        error_log('Product not in target or multiplier categories, exiting');
        return;
    }
    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product->get_id());
    $dims = extract_dimensions_from_title($title);
    
    // Получаем доступные услуги покраски
    $painting_services = get_available_painting_services_by_material($product->get_id());
    
    // Получаем множитель цены
    $price_multiplier = get_price_multiplier($product->get_id());
    
    // Получаем настройки калькулятора для категорий 265-268
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
    
    // Определяем единицу измерения для калькулятора
    $product_id = $product->get_id();
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
    $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
    $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
    $is_square_meter = has_term([270, 267, 268], 'product_cat', $product->get_id());
    $is_running_meter = has_term([266, 271], 'product_cat', $product->get_id());
    ?>
    
    <script>
    const isSquareMeter = <?php echo $is_square_meter ? 'true' : 'false'; ?>;
const isRunningMeter = 'false';
const paintingServices = <?php echo json_encode($painting_services); ?>;
const priceMultiplier = <?php echo $price_multiplier; ?>;
const isMultiplierCategory = <?php echo $is_multiplier ? 'true' : 'false'; ?>;
const calcSettings = <?php echo $calc_settings ? json_encode($calc_settings) : 'null'; ?>;

// Теперь DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    let form = document.querySelector('form.cart') || 
              document.querySelector('form[action*="add-to-cart"]') ||
              document.querySelector('.single_add_to_cart_button').closest('form');
    let quantityInput = document.querySelector('input[name="quantity"]') ||
                       document.querySelector('.qty') ||
                       document.querySelector('.input-text.qty');
    if (!form) return;

const resultBlock = document.createElement('div');
resultBlock.id = 'custom-calc-block';
resultBlock.className = 'calc-result-container'; // ВАЖНО: Добавляем класс для поиска позже
resultBlock.style.marginTop = '20px';
resultBlock.style.marginBottom = '20px';
form.insertAdjacentElement('afterend', resultBlock);

    // Локальные переменные
    let isAutoUpdate = false;
    
        const paintingServices = <?php echo json_encode($painting_services); ?>;
        const priceMultiplier = <?php echo $price_multiplier; ?>;
        const isMultiplierCategory = <?php echo $is_multiplier ? 'true' : 'false'; ?>;
        const isSquareMeter = <?php echo $is_square_meter ? 'true' : 'false'; ?>;
        const isRunningMeter = <?php echo $is_running_meter ? 'true' : 'false'; ?>;
        const calcSettings = <?php echo $calc_settings ? json_encode($calc_settings) : 'null'; ?>;

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

        // Создаем блок для услуг покраски с select вместо radiobutton
function createPaintingServicesBlock(currentCategoryId) {
    if (Object.keys(paintingServices).length === 0) return null;

    const paintingBlock = document.createElement('div');
    paintingBlock.id = 'painting-services-block';

    // options
    let optionsHTML = '<option value="" selected>Без покраски</option>';
    Object.entries(paintingServices).forEach(([key, service]) => {
        let optionText = service.name;
        // Добавляем цену только если категория вне диапазона 265-271
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

        <!-- для вставки блоков выбора схем/цветов из pm-paint-schemes.php -->
        <div id="paint-schemes-root"></div>
    `;
    return paintingBlock;
}


        const paintingBlock = createPaintingServicesBlock();

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

        // Добавляем блок услуг покраски после блока расчета площади
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
        }
        
        if (quantityInput) {
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
// Проверяем, нужно ли показывать выбор фаски
$product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
$show_faska = false;
$faska_types = array();

if ($product_cats && !is_wp_error($product_cats)) {
    foreach ($product_cats as $cat_id) {
        if (in_array($cat_id, array(268, 270))) {
            $show_faska = true;
            // Получаем типы фасок из категории
            $faska_types = get_term_meta($cat_id, 'faska_types', true);
            if ($faska_types) break;
        }
    }
}
?>

<?php if($is_multiplier && !$show_falsebalk_calc): ?>
// Калькулятор для столярки (кроме фальшбалок)
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
    for (let l = calcSettings.length_min; l <= calcSettings.length_max; l += calcSettings.length_step) {
        calcHTML += `<option value="${l.toFixed(2)}">${l.toFixed(2)}</option>`;
    }
    calcHTML += `</select></label>`;
} else {
    calcHTML += `<label>Длина (м): 
        <input type="number" id="mult_length" min="0.01" step="0.01" placeholder="0.01" style="width:100px; margin-left:10px;">
    </label>`;
}

// Количество — НЕ вводимое поле. Используем главное поле WC. Показываем текущее значение.
calcHTML += `<label style="display:none">Количество (шт): <span id="mult_quantity_display" style="display:none">1</span></label>`;

calcHTML += '</div>';

<?php if ($show_faska && !empty($faska_types)): ?>
// Добавляем выбор фаски
calcHTML += `<div id="faska_selection" style="margin-top: 10px; display: none;">
    <h5>Выберите тип фаски:</h4>
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

// Добавляем CSS для выбранной фаски
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
@media (max-width: 480px) {
    #faska_grid {
        grid-template-columns: repeat(2, 1fr) !important;
        grid-template-rows: repeat(4, 1fr) !important;
    }
    #faska_selected_image {
        max-width: 200px !important;
    }
}
</style>
`);
<?php endif; ?>

calcHTML += '<div id="calc_mult_result" style="margin-top:10px; font-size:1.3em"></div>';
multiplierCalc.innerHTML = calcHTML;
resultBlock.appendChild(multiplierCalc);

// Добавляем блок услуг покраски после калькулятора с множителем
if (paintingBlock) {
    multiplierCalc.appendChild(paintingBlock);
}

const multWidthEl = document.getElementById('mult_width');
const multLengthEl = document.getElementById('mult_length');
const multQuantityDisplay = document.getElementById('mult_quantity_display');
const multResult = document.getElementById('calc_mult_result');
const basePriceMult = <?php echo floatval($product->get_price()); ?>;

function updateMultiplierCalc() {
    const widthValue = parseFloat(multWidthEl && multWidthEl.value);
    const lengthValue = parseFloat(multLengthEl && multLengthEl.value);

    // quantity берём из основного поля WC, fallback = 1
    const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;
    multQuantityDisplay.textContent = quantity;

    <?php if ($show_faska): ?>
    // Показываем выбор фаски только если введены размеры
    const faskaSelection = document.getElementById('faska_selection');
    if (faskaSelection) {
        if (widthValue > 0 && lengthValue > 0) {
            faskaSelection.style.display = 'block';
        } else {
            faskaSelection.style.display = 'none';
            // Сброс выбора фаски при изменении размеров
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
    html += `Толщина: <b>40мм</b></br>`;
    html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b>`;

    html += '<br>';
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
    // Сохраняем выбранную фаску
    const selectedFaska = document.querySelector('input[name="faska_type"]:checked');
    if (selectedFaska) {
        createHiddenField('selected_faska_type', selectedFaska.value);
    } else {
        removeHiddenFields('selected_faska_');
    }
    <?php endif; ?>

    // Не меняем quantityInput здесь — это поле главный источник.
}

multWidthEl.addEventListener('change', updateMultiplierCalc);
multLengthEl.addEventListener('change', updateMultiplierCalc);

<?php if ($show_faska): ?>
// Обработчик выбора фаски
setTimeout(function() {
    const faskaInputs = document.querySelectorAll('input[name="faska_type"]');
    const faskaGrid = document.getElementById('faska_grid');
    const faskaSelected = document.getElementById('faska_selected');
    const faskaSelectedName = document.getElementById('faska_selected_name');
    const faskaSelectedImage = document.getElementById('faska_selected_image');
    const changeFaskaBtn = document.getElementById('change_faska_btn');
    
    faskaInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.checked) {
                // Скрываем сетку, показываем выбранное
                faskaGrid.style.display = 'none';
                faskaSelected.style.display = 'block';
                
                // Обновляем информацию о выбранной фаске
                faskaSelectedName.textContent = this.value;
                faskaSelectedImage.src = this.dataset.image;
                faskaSelectedImage.alt = this.value;
            }
            updateMultiplierCalc();
        });
    });
    
    // Кнопка изменения выбора
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
        if (!isAutoUpdate && multWidthEl.value && multLengthEl.value) {
            updateMultiplierCalc();
        }
    });
}

// Синхронизация количества из основного поля в калькулятор
if (quantityInput) {
    quantityInput.addEventListener('input', function() {
        if (!isAutoUpdate) {
            const mainQty = parseInt(this.value);
            if (mainQty > 0 && multWidthEl.value && multLengthEl.value) {
                multQuantityEl.value = mainQty;
                updateMultiplierCalc();
            }
        }
    });
    
    // Полный сброс при изменении количества вручную без активного калькулятора
    quantityInput.addEventListener('change', function() {
        if (!isAutoUpdate && !form.querySelector('input[name="custom_mult_width"]')) {
            // Калькулятор не активен, ничего не делаем
            return;
        }
    });
}
<?php endif; ?>

// Функция обновления стоимости покраски
function updatePaintingServiceCost(totalArea = null) {
    if (!paintingBlock) return 0;
    
    const serviceSelect = document.getElementById('painting_service_select');
    const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
    const paintingResult = document.getElementById('painting-service-result');
    
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

// Обработчик для услуг покраски (select)
if (paintingBlock) {
    const serviceSelect = document.getElementById('painting_service_select');
    if (serviceSelect) {
        serviceSelect.addEventListener('change', function() {
            const areaInput = document.getElementById('calc_area_input');
            const widthEl = document.getElementById('custom_width');
            const lengthEl = document.getElementById('custom_length');
            const multWidthEl = document.getElementById('mult_width');
            const multLengthEl = document.getElementById('mult_length');

            // Сценарий 1: калькулятор площади
            if (areaInput && areaInput.value) {
                updateAreaCalc();
                return;
            }

            // Сценарий 2: калькулятор размеров для стандартных категорий
            if (widthEl && lengthEl) {
                const width = parseFloat(widthEl.value);
                const length = parseFloat(lengthEl.value);
                if (width > 0 && length > 0) {
                    updateDimCalc(true);
                    return;
                }
            }

            // Сценарий 3: калькулятор с множителем для категорий 265-268
            if (multWidthEl && multLengthEl) {
                const width = parseFloat(multWidthEl.value);
                const length = parseFloat(multLengthEl.value);
                if (width > 0 && length > 0) {
                    updateMultiplierCalc();
                    return;
                }
            }

            // Сценарий 3.5: калькулятор погонных метров (running meter)
            const rmWidthEl = document.getElementById('rm_width');
            const rmLengthEl = document.getElementById('rm_length');
            if (rmLengthEl && rmLengthEl.value) {
                updateRunningMeterCalc();
                return; // ВАЖНО: возвращаем return, чтобы не сбросить покраску
            }

            // Сценарий 4: ничего не введено, но есть pack_area
            if (typeof packArea !== 'undefined' && packArea > 0) {
                if (areaInput) {
                    areaInput.value = packArea.toFixed(2);
                    updateAreaCalc();
                } else if (widthEl && lengthEl) {
                    updateDimCalc(true);
                }
            }

            updatePaintingServiceCost(0);
        });
    }
}
        
// Обработчик для выбора цвета покраски через делегирование событий
document.addEventListener('change', function(e) {
    // Проверяем, что это радио-кнопка выбора цвета
    if (e.target.name === 'pm_selected_color') {
        console.log('Paint color changed, recalculating...');
        
        // Определяем, какой калькулятор активен и пересчитываем его
        const areaInput = document.getElementById('calc_area_input');
        const widthEl = document.getElementById('custom_width');
        const lengthEl = document.getElementById('custom_length');
        const multWidthEl = document.getElementById('mult_width');
        const multLengthEl = document.getElementById('mult_length');
        const rmLengthEl = document.getElementById('rm_length');
        const sqWidthEl = document.getElementById('sq_width');
        const sqLengthEl = document.getElementById('sq_length');
        
        // 1. Калькулятор площади
        if (areaInput && areaInput.value) {
            console.log('Updating area calculator');
            updateAreaCalc();
            return;
        }
        
        // 2. Калькулятор размеров (старый)
        if (widthEl && lengthEl && widthEl.value && lengthEl.value) {
            console.log('Updating dimensions calculator');
            updateDimCalc(true);
            return;
        }
        
        // 3. Калькулятор с множителем
        if (multWidthEl && multLengthEl && multWidthEl.value && multLengthEl.value) {
            console.log('Updating multiplier calculator');
            updateMultiplierCalc();
            return;
        }
        
        // 4. Калькулятор погонных метров (включая фальшбалки)
        if (rmLengthEl && rmLengthEl.value) {
            console.log('Updating running meter calculator (falsebalk)');
            updateRunningMeterCalc();
            return;
        }
        
        // 5. Калькулятор квадратных метров
        if (sqWidthEl && sqLengthEl && sqWidthEl.value && sqLengthEl.value) {
            console.log('Updating square meter calculator');
            updateSquareMeterCalc();
            return;
        }
    }
});



<?php if($is_running_meter): ?>
    <?php 
    // Получаем данные для фальшбалок (проверка уже была выполнена выше)
    $is_falsebalk = product_in_category($product->get_id(), 266);
    $shapes_data = array();
    $show_falsebalk_calculator = $show_falsebalk_calc; // Используем переменную из начала функции
    
    if ($show_falsebalk_calculator) {
        $shapes_data = get_post_meta($product->get_id(), '_falsebalk_shapes_data', true);
        if (!is_array($shapes_data)) {
            $shapes_data = array();
        }
    }
    ?>
    
    console.log('=== Running meter calculator initialization ===');
console.log('Show falsebalk calc:', <?php echo !empty($show_falsebalk_calc) ? 'true' : 'false'; ?>);

    console.log('Is falsebalk:', <?php echo $is_falsebalk ? 'true' : 'false'; ?>);
    
    // ТОЛЬКО для фальшбалок очищаем resultBlock
    <?php if ($show_falsebalk_calculator): ?>
        console.log('Clearing result block for FALSEBALK calculator');
        if (resultBlock) {
            resultBlock.innerHTML = '';
        }
    <?php endif; ?>
    
    const runningMeterCalc = document.createElement('div');
    runningMeterCalc.id = 'calc-running-meter';

    let rmCalcHTML = '<br><h4>Калькулятор стоимости</h4>';


<?php if ($show_falsebalk_calculator): ?>
// ============ ДЛЯ ФАЛЬШБАЛОК (КАТЕГОРИЯ 266) ============
console.log('=== Rendering FALSEBALK calculator ===');
const shapesData = <?php echo json_encode($shapes_data); ?>;
console.log('Shapes data:', shapesData);

<?php 
// --- ИКОНКИ ДЛЯ ФОРМ ---
$shape_icons = [
    'g' => '<svg width="60" height="60" viewBox="0 0 60 60">
                <rect x="5" y="5" width="10" height="50" fill="#000"/>
                <rect x="5" y="45" width="50" height="10" fill="#000"/>
            </svg>',
    'p' => '<svg width="60" height="60" viewBox="0 0 60 60">
                <rect x="5" y="5" width="10" height="50" fill="#000"/>
                <rect x="45" y="5" width="10" height="50" fill="#000"/>
                <rect x="5" y="5" width="50" height="10" fill="#000"/>
            </svg>',
    'o' => '<svg width="60" height="60" viewBox="0 0 60 60">
                <rect x="5" y="5" width="50" height="50" fill="none" stroke="#000" stroke-width="10"/>
            </svg>'
];

$shape_labels = [
    'g' => 'Г-образная',
    'p' => 'П-образная',
    'o' => 'О-образная'
];

// --- ГЕНЕРАЦИЯ HTML ДЛЯ ВЫБОРА ФОРМ ---
$shapes_buttons_html = '';

foreach ($shapes_data as $shape_key => $shape_info):
    if (is_array($shape_info) && !empty($shape_info['enabled'])):
        $shape_label = isset($shape_labels[$shape_key]) ? $shape_labels[$shape_key] : ucfirst($shape_key);
        $shapes_buttons_html .= '<label class="shape-tile" data-shape="' . esc_attr($shape_key) . '" style="cursor:pointer; border:2px solid #ccc; border-radius:10px; padding:10px; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; transition:all .2s; min-width:100px;">';
        $shapes_buttons_html .= '<input type="radio" name="falsebalk_shape" value="' . esc_attr($shape_key) . '" style="display:none;">';
        $shapes_buttons_html .= '<div>' . $shape_icons[$shape_key] . '</div>';
        $shapes_buttons_html .= '<span style="font-size:12px; color:#666; text-align:center;">' . esc_html($shape_label) . '</span>';
        $shapes_buttons_html .= '</label>';
    endif;
endforeach;
?>

// 1. ВЫБОР ФОРМЫ СЕЧЕНИЯ
rmCalcHTML += '<div style="margin-bottom:20px; border:2px solid #e0e0e0; padding:15px; border-radius:8px; background:#f9f9f9;">';
rmCalcHTML += '<label style="display:block; margin-bottom:15px; font-weight:600; font-size:1.1em;">Шаг 1: Выберите форму сечения фальшбалки</label>';
rmCalcHTML += '<div style="display:flex; gap:15px; flex-wrap:wrap;">';
rmCalcHTML += <?php echo json_encode($shapes_buttons_html); ?>;
rmCalcHTML += '</div></div>';

// 2. КОНТЕЙНЕР ДЛЯ ПАРАМЕТРОВ
rmCalcHTML += '<div id="falsebalk_params" style="display:none; margin-bottom:20px; border:2px solid #e0e0e0; padding:15px; border-radius:8px; background:#f9f9f9;">';
rmCalcHTML += '<label style="display:block; margin-bottom:15px; font-weight:600; font-size:1.1em;">Шаг 2: Выберите размеры</label>';
rmCalcHTML += '<div style="display:flex; gap:20px; flex-wrap:wrap; align-items:center;">';

rmCalcHTML += `<label style="display:flex; flex-direction:column; gap:5px;">
    <span style="font-weight:500;">Ширина (мм):</span>
    <select id="rm_width" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
        <option value="">Сначала выберите форму</option>
    </select>
</label>`;

rmCalcHTML += `<div id="height_container" style="dislpay:contents"></div>`;

rmCalcHTML += `<label style="display:flex; flex-direction:column; gap:5px;">
    <span style="font-weight:500;">Длина (м):</span>
    <select id="rm_length" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
        <option value="">Сначала выберите форму</option>
    </select>
</label>`;

rmCalcHTML += `<label style="display:none; flex-direction:column; gap:5px;">
    <span style="font-weight:500;">Количество (шт):</span>
    <span id="rm_quantity_display" style="font-weight:600; font-size:1.1em;">1</span>
</label>`;

rmCalcHTML += '</div></div>';

// 3. РЕЗУЛЬТАТ
rmCalcHTML += '<div id="calc_rm_result" style="margin-top:15px;"></div>';

<?php else: ?>
// ============ ДЛЯ ОБЫЧНЫХ СТОЛЯРНЫХ ИЗДЕЛИЙ ============
console.log('Rendering STANDARD running meter calculator');

rmCalcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

// Поле ширины
if (calcSettings && calcSettings.width_min > 0 && calcSettings.width_max > 0) {
    rmCalcHTML += `<label>Ширина (мм): 
        <select id="rm_width" style="background:#fff;margin-left:10px;">
            <option value="">Выберите...</option>`;
    for (let w = calcSettings.width_min; w <= calcSettings.width_max; w += calcSettings.width_step) {
        rmCalcHTML += `<option value="${w}">${w}</option>`;
    }
    rmCalcHTML += `</select></label>`;
} else {
    rmCalcHTML += `<label>Ширина (мм): 
        <input type="number" id="rm_width" min="1" step="100" placeholder="100" style="width:100px; margin-left:10px;background:#fff">
    </label>`;
}

// Поле длины
if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
    rmCalcHTML += `<label>Длина (м): 
        <select id="rm_length" style="background:#fff;margin-left:10px;">
            <option value="">Выберите...</option>`;
    for (let l = calcSettings.length_min; l <= calcSettings.length_max; l += calcSettings.length_step) {
        rmCalcHTML += `<option value="${l.toFixed(2)}">${l.toFixed(2)}</option>`;
    }
    rmCalcHTML += `</select></label>`;
} else {
    rmCalcHTML += `<label>Длина (пог. м): 
        <input type="number" id="rm_length" min="0.1" step="0.1" placeholder="2.0" style="width:100px; margin-left:10px;background:#fff">
    </label>`;
}

rmCalcHTML += `<label style="display:none">Количество (шт): <span id="rm_quantity_display" style="margin-left:10px; font-weight:600;">1</span></label>`;
rmCalcHTML += '</div>';
rmCalcHTML += '<div id="calc_rm_result" style="margin-top:10px;"></div>';
<?php endif; ?>

// ВАЖНО: Добавляем HTML в DOM
runningMeterCalc.innerHTML = rmCalcHTML;
resultBlock.appendChild(runningMeterCalc);
console.log('✓ Running meter calculator HTML added to DOM');

// Добавляем блок услуг покраски
if (paintingBlock) {
    runningMeterCalc.appendChild(paintingBlock);
    console.log('✓ Painting block added');
}

<?php if ($show_falsebalk_calculator): ?>
// ============ JAVASCRIPT ЛОГИКА ДЛЯ ФАЛЬШБАЛОК ============

// === ФУНКЦИИ ===
function generateOptions(min, max, step, unit = '') {
    const options = ['<option value="">Выберите...</option>'];
    if (!min || !max || !step || min > max) return options.join('');
    const stepsCount = Math.round((max - min) / step) + 1;
    for (let i = 0; i < stepsCount; i++) {
        const value = min + (i * step);
        const displayValue = unit === 'м' ? value.toFixed(2) : Math.round(value);
        const rawValue = unit === 'м' ? value.toFixed(2) : Math.round(value);
        options.push(`<option value="${rawValue}">${displayValue}${unit ? ' ' + unit : ''}</option>`);
    }
    return options.join('');
}

function parseOldFormat(data) {
    if (typeof data === 'string' && data.includes(',')) {
        const values = data.split(',').map(v => v.trim()).filter(v => v);
        return values.map(v => `<option value="${v}">${v}</option>`).join('');
    }
    return null;
}

const falsebalkaParams = document.getElementById('falsebalk_params');
const rmWidthEl = document.getElementById('rm_width');
const heightContainer = document.getElementById('height_container');
const rmLengthEl = document.getElementById('rm_length');

function updateDimensions(selectedShape) {
    const shapeData = shapesData[selectedShape];
    console.log('Updating dimensions for:', selectedShape, shapeData);
    
    if (!shapeData || !shapeData.enabled) {
        console.error('No data found for shape:', selectedShape);
        return;
    }
    
    falsebalkaParams.style.display = 'block';
    
    // ШИРИНЫ
    const oldWidthFormat = parseOldFormat(shapeData.widths);
    if (oldWidthFormat) {
        rmWidthEl.innerHTML = '<option value="">Выберите...</option>' + oldWidthFormat;
    } else {
        rmWidthEl.innerHTML = generateOptions(shapeData.width_min, shapeData.width_max, shapeData.width_step, 'мм');
    }
    
    // ВЫСОТЫ
    heightContainer.innerHTML = '';
    if (selectedShape === 'p') {
        // П-образная: две высоты
        let height1Options, height2Options;
        const oldHeight1Format = parseOldFormat(shapeData.heights);
        
        if (oldHeight1Format) {
            height1Options = '<option value="">Выберите...</option>' + oldHeight1Format;
            height2Options = '<option value="">Выберите...</option>' + oldHeight1Format;
        } else {
            height1Options = generateOptions(shapeData.height1_min, shapeData.height1_max, shapeData.height1_step, 'мм');
            height2Options = generateOptions(shapeData.height2_min, shapeData.height2_max, shapeData.height2_step, 'мм');
        }
        
        heightContainer.innerHTML = `
            <label style="display:flex; flex-direction:column; gap:5px;">
                <span style="font-weight:500;">Высота 1 (мм):</span>
                <select id="rm_height1" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
                    ${height1Options}
                </select>
            </label>
            <label style="display:flex; flex-direction:column; gap:5px;">
                <span style="font-weight:500;">Высота 2 (мм):</span>
                <select id="rm_height2" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
                    ${height2Options}
                </select>
            </label>
        `;
        
        document.getElementById('rm_height1').addEventListener('change', updateRunningMeterCalc);
        document.getElementById('rm_height2').addEventListener('change', updateRunningMeterCalc);
    } else {
        // Г и О: одна высота
        const oldHeightFormat = parseOldFormat(shapeData.heights);
        let heightOptions = oldHeightFormat ? '<option value="">Выберите...</option>' + oldHeightFormat : 
                           generateOptions(shapeData.height_min, shapeData.height_max, shapeData.height_step, 'мм');
        
        heightContainer.innerHTML = `
            <label style="display:flex; flex-direction:column; gap:5px;">
                <span style="font-weight:500;">Высота (мм):</span>
                <select id="rm_height" style="background:#fff; padding:8px 12px; border:1px solid #ddd; border-radius:4px; min-width:150px;">
                    ${heightOptions}
                </select>
            </label>
        `;
        
        document.getElementById('rm_height').addEventListener('change', updateRunningMeterCalc);
    }
    
    // ДЛИНЫ
    const oldLengthFormat = parseOldFormat(shapeData.lengths);
    if (oldLengthFormat) {
        rmLengthEl.innerHTML = '<option value="">Выберите...</option>' + oldLengthFormat;
    } else {
        rmLengthEl.innerHTML = generateOptions(shapeData.length_min, shapeData.length_max, shapeData.length_step, 'м');
    }
    
    document.getElementById('calc_rm_result').innerHTML = '';
    if (typeof removeHiddenFields === 'function') {
        removeHiddenFields('custom_rm_');
    }
}

// Обработчик клика по плиткам
document.addEventListener('click', function(e) {
    const tile = e.target.closest('.shape-tile');
    if (!tile) return;
    
    document.querySelectorAll('.shape-tile').forEach(t => {
        t.style.borderColor = '#ccc';
        t.style.boxShadow = 'none';
    });
    
    tile.style.borderColor = '#3aa655';
    tile.style.boxShadow = '0 0 0 3px rgba(58,166,85,0.3)';
    
    const radio = tile.querySelector('input[name="falsebalk_shape"]');
    if (radio) {
        radio.checked = true;
        updateDimensions(radio.value);
    }
});

// Эффекты наведения
document.querySelectorAll('.shape-tile').forEach(tile => {
    tile.addEventListener('mouseenter', function() {
        const radio = this.querySelector('input[name="falsebalk_shape"]');
        if (!radio || !radio.checked) {
            this.style.borderColor = '#0073aa';
            this.style.transform = 'scale(1.02)';
        }
    });
    
    tile.addEventListener('mouseleave', function() {
        const radio = this.querySelector('input[name="falsebalk_shape"]');
        if (!radio || !radio.checked) {
            this.style.borderColor = '#ccc';
            this.style.transform = 'scale(1)';
        }
    });
});

console.log('✓ Falsebalk event handlers attached');

<?php else: ?>
// ============ JAVASCRIPT ЛОГИКА ДЛЯ ОБЫЧНЫХ ИЗДЕЛИЙ ============
console.log('Initializing STANDARD running meter logic');
const rmWidthEl = document.getElementById('rm_width');
const rmLengthEl = document.getElementById('rm_length');
<?php endif; ?>

// === ОБЩАЯ ФУНКЦИЯ РАСЧЕТА (для обоих типов) ===
const rmQuantityDisplay = document.getElementById('rm_quantity_display');
const rmResult = document.getElementById('calc_rm_result');
const basePriceRM = <?php echo floatval($product->get_price()); ?>;

document.querySelectorAll('input[name="falsebalk_shape"]').forEach(el => {
    el.addEventListener('change', e => {
        selectedShape = e.target.value;
        updateRunningMeterCalc();
    });
});


function updateRunningMeterCalc() {
    <?php if ($show_falsebalk_calculator): ?>
    const selectedShape = document.querySelector('input[name="falsebalk_shape"]:checked');
    if (!selectedShape) {
        rmResult.innerHTML = '<span style="color: #999;">⬆️ Выберите форму сечения фальшбалки</span>';
        return;
    }
    
    const widthValue = rmWidthEl ? parseFloat(rmWidthEl.value) : 0;
    const lengthValue = parseFloat(rmLengthEl.value);
    
    let heightValue = 0;
    let height2Value = 0;
    
    if (selectedShape.value === 'p') {
        const height1El = document.getElementById('rm_height1');
        const height2El = document.getElementById('rm_height2');
        heightValue = height1El ? parseFloat(height1El.value) : 0;
        height2Value = height2El ? parseFloat(height2El.value) : 0;
    } else {
        const heightEl = document.getElementById('rm_height');
        heightValue = heightEl ? parseFloat(heightEl.value) : 0;
    }
    <?php else: ?>
    const widthValue = rmWidthEl ? parseFloat(rmWidthEl.value) : 0;
    const lengthValue = parseFloat(rmLengthEl.value);
    <?php endif; ?>

    const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;
    rmQuantityDisplay.textContent = quantity;

    if (!lengthValue || lengthValue <= 0) {
        rmResult.innerHTML = '';
        removeHiddenFields('custom_rm_');
        updatePaintingServiceCost(0);
        return;
    }

// пересчёт длин и площади
const totalLength = lengthValue * quantity;

// --- вычисляем площадь покраски (m^2) как раньше ---
let paintingArea = 0;
if (widthValue > 0) {
    const width_m = widthValue / 1000;
    const height_m = (typeof heightValue !== 'undefined' ? heightValue : 0) / 1000;
    const height2_m = (typeof height2Value !== 'undefined' ? height2Value : 0) / 1000;

    if (selectedShape) {
        const shapeKey = selectedShape.value;
        if (shapeKey === 'g') {
            paintingArea = (width_m + height_m) * totalLength;
        } else if (shapeKey === 'p') {
            paintingArea = (width_m + height_m + height2_m) * totalLength;
        } else if (shapeKey === 'o') {
            // О-образная — две стороны и две высоты (эквивалент 4 плоскостей)
            paintingArea = 2 * (width_m + height_m) * totalLength;
        } else {
            // запасной вариант
            paintingArea = width_m * totalLength;
        }
    } else {
        paintingArea = width_m * totalLength;
    }
}

// --- материал берём пропорционально той же площади ---
// basePriceRM трактуем как цена за 1 м² материала/покрытия
const materialPrice = paintingArea * basePriceRM * priceMultiplier;

// цена за одну единицу (на случай вывода)
const pricePerItem = (quantity > 0) ? (materialPrice / quantity) : 0;

// рассчёт стоимости покраски 
const paintingCost = updatePaintingServiceCost(paintingArea);

// итог
const grandTotal = materialPrice + paintingCost;


    <?php if ($show_falsebalk_calculator): ?>
    const shapeLabel = selectedShape.closest('.shape-tile')?.querySelector('span')?.textContent.trim() || selectedShape.value;
    let html = `<div style="background: #f0f8ff; padding: 10px; font-size:1em; border-radius: 5px; margin-bottom: 10px; border-left: 4px solid #8bc34a;">`;
    html += `<div>Форма сечения: <b>${shapeLabel}</b></div>`;
    if (widthValue > 0) html += `<div>Ширина: <b>${widthValue} мм</b></div>`;
    if (heightValue > 0) {
        if (selectedShape.value === 'p') {
            html += `<div>Высота 1: <b>${heightValue} мм</b></div>`;
            if (height2Value > 0) html += `<div>Высота 2: <b>${height2Value} мм</b></div>`;
        } else {
            html += `<div>Высота: <b>${heightValue} мм</b></div>`;
        }
    }
    html += `<div>Длина 1 шт: <b>${lengthValue.toFixed(2)} пог. м</b></div></div>`;
    <?php else: ?>
    let html = `Длина 1 шт: <b>${lengthValue.toFixed(2)} пог. м</b><br>`;
    <?php endif; ?>
    
    html += `Общая длина: <b>${totalLength.toFixed(2)} пог. м</b> (${quantity} шт)<br>`;
    html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b><br>`;
    html += `Стоимость материала: <b>${materialPrice.toFixed(2)} ₽</b><br>`;
    
    if (paintingCost > 0) {
        html += `Площадь покраски: <b>${paintingArea.toFixed(3)} м²</b><br>`;
        html += `Стоимость покраски: <b>${paintingCost.toFixed(2)} ₽</b><br>`;
        html += `<strong style="font-size: 1.2em; color: #0073aa;">Итого с покраской: <b>${grandTotal.toFixed(2)} ₽</b></strong>`;
    } else {
        html += `<strong style="font-size: 1.2em; color: #0073aa;">Итого: <b>${materialPrice.toFixed(2)} ₽</b></strong>`;
    }

    rmResult.innerHTML = html;

    <?php if ($show_falsebalk_calculator): ?>
    createHiddenField('custom_rm_shape', selectedShape.value);
    createHiddenField('custom_rm_shape_label', shapeLabel);
    createHiddenField('custom_rm_width', widthValue || 0);
    createHiddenField('custom_rm_height', heightValue || 0);
    if (selectedShape.value === 'p' && height2Value > 0) {
        createHiddenField('custom_rm_height2', height2Value);
    }
    <?php else: ?>
    createHiddenField('custom_rm_width', widthValue || 0);
    <?php endif; ?>
    
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
    quantityInput.addEventListener('input', function() {
        if (!isAutoUpdate && rmLengthEl && rmLengthEl.value) {
            updateRunningMeterCalc();
        }
    });
    
    quantityInput.addEventListener('change', function() {
        if (!isAutoUpdate && rmLengthEl && rmLengthEl.value) {
            updateRunningMeterCalc();
        }
    });
}

console.log('✓ Running meter calculator fully initialized');
<?php endif; ?>

        <?php if($is_square_meter && !$is_running_meter): ?>
        // Калькулятор для категорий за квадратные метры - столярные изделия
        const sqMeterCalc = document.createElement('div');
        sqMeterCalc.id = 'calc-square-meter';

        let sqCalcHTML = '<br><h4>Калькулятор стоимости</h4>';

        sqCalcHTML += '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items: center;">';

        // Поле ширины
        if (calcSettings && calcSettings.width_min > 0 && calcSettings.width_max > 0) {
            sqCalcHTML += `<label>Ширина (мм): 
                <select id="sq_width" style="background:#fff;margin-left:10px;">
                    <option value="">Выберите...</option>`;
            for (let w = calcSettings.width_min; w <= calcSettings.width_max; w += calcSettings.width_step) {
                sqCalcHTML += `<option value="${w}">${w}</option>`;
            }
            sqCalcHTML += `</select></label>`;
        } else {
            sqCalcHTML += `<label>Ширина (мм): 
                <input type="number" id="sq_width" min="1" step="100" placeholder="1000" style="width:100px; margin-left:10px;background:#fff">
            </label>`;
        }

        // Поле длины
        if (calcSettings && calcSettings.length_min > 0 && calcSettings.length_max > 0) {
            sqCalcHTML += `<label>Длина (м): 
                <select id="sq_length" style="margin-left:10px;background:#fff;">
                    <option value="">Выберите...</option>`;
            
            // ВАЖНО: Используем целочисленный счётчик для избежания ошибок округления float
            const lengthMin = calcSettings.length_min;
            const lengthMax = calcSettings.length_max;
            const lengthStep = calcSettings.length_step;
            
            // Вычисляем количество шагов
            const stepsCount = Math.round((lengthMax - lengthMin) / lengthStep) + 1;
            
            for (let i = 0; i < stepsCount; i++) {
                const value = lengthMin + (i * lengthStep);
                // Округляем до 2 знаков после запятой для отображения
                const displayValue = value.toFixed(2);
                sqCalcHTML += `<option value="${displayValue}">${displayValue}</option>`;
            }
            
            sqCalcHTML += `</select></label>`;
        } else {
            sqCalcHTML += `<label>Длина (м): 
                <input type="number" id="sq_length" min="0.01" step="0.01" placeholder="0.01" style="width:100px; margin-left:10px;">
            </label>`;
        }

        // Количество
        sqCalcHTML += '</div><div id="calc_sq_result" style="margin-top:10px; font-size:1.3em"></div>';
        sqMeterCalc.innerHTML = sqCalcHTML;
        resultBlock.appendChild(sqMeterCalc);

        // Добавляем блок услуг покраски
        if (paintingBlock) {
            sqMeterCalc.appendChild(paintingBlock);
        }

        const sqWidthEl = document.getElementById('sq_width');
        const sqLengthEl = document.getElementById('sq_length');
        const sqQuantityDisplay = document.getElementById('sq_quantity_display');
        const sqResult = document.getElementById('calc_sq_result');
        const basePriceSQ = <?php echo floatval($product->get_price()); ?>;

        function updateSquareMeterCalc() {
            const widthValue = parseFloat(sqWidthEl.value);
            const lengthValue = parseFloat(sqLengthEl.value);

            const quantity = (quantityInput && !isNaN(parseInt(quantityInput.value))) ? parseInt(quantityInput.value) : 1;
            sqQuantityDisplay.textContent = quantity;

            if (!widthValue || widthValue <= 0 || !lengthValue || lengthValue <= 0) {
                sqResult.innerHTML = '';
                removeHiddenFields('custom_sq_');
                updatePaintingServiceCost(0);
                return;
            }

            const width_m = widthValue / 1000;
            const length_m = lengthValue;
            
            const areaPerItem = width_m * length_m;
            const totalArea = areaPerItem * quantity;
            const pricePerItem = areaPerItem * basePriceSQ;
            const materialPrice = pricePerItem * quantity;
            
            const paintingCost = updatePaintingServiceCost(totalArea);
            const grandTotal = materialPrice + paintingCost;

            let html = `Площадь 1 шт: <b>${areaPerItem.toFixed(3)} м²</b><br>`;
            html += `Общая площадь: <b>${totalArea.toFixed(3)} м²</b> (${quantity} шт)<br>`;
            html += `Цена за 1 шт: <b>${pricePerItem.toFixed(2)} ₽</b>`;
            html += '<br>';
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

        sqWidthEl.addEventListener('change', updateSquareMeterCalc);
        sqLengthEl.addEventListener('change', updateSquareMeterCalc);

        // Синхронизация количества
        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate && sqWidthEl.value && sqLengthEl.value) {
                    updateSquareMeterCalc();
                }
            });
        }
        <?php endif; ?>
    
    });
    </script>
    <?php
}, 20);
