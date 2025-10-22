<?php
/**
 * Модуль: Frontend Calculators (ПОЛНАЯ ВЕРСИЯ 2.0)
 * Описание: Весь JavaScript и PHP функционал калькуляторов
 * Зависимости: product-calculations, category-helpers
 * 
 * ВАЖНО: Содержит ВСЁ из functions_full_old.php + pm-paint-schemes
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
                            'schemes' => $service['schemes'] ?? [] // ВАЖНО: схемы цветов
                        ];
                    }
                }
            }
        }
        return $result;
    }
}

// Получение данных схем покраски для конкретной услуги
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
    
    // Проверяем, нужно ли показывать калькулятор
    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product->get_id());
    $dims = extract_dimensions_from_title($title);
    
    $should_show_calculator = $is_target || $is_multiplier || $pack_area || $dims;
    
    if (!$should_show_calculator) {
        error_log('Product does not need calculator, exiting');
        return;
    }
    
    // Получаем доступные услуги покраски
    $painting_services = get_available_painting_services_by_material($product->get_id());
    
    // Получаем множитель цены
    $price_multiplier = get_price_multiplier($product->get_id());
    
    // Получаем настройки калькулятора для категорий 265-271
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
    
    // Определяем единицу измерения
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
    $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
    $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
    
    // Проверяем, нужно ли показывать выбор фаски
    $show_faska = false;
    $faska_types = array();
    
    if ($is_multiplier) {
        $product_cats = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'ids'));
        if ($product_cats && !is_wp_error($product_cats)) {
            foreach ($product_cats as $cat_id) {
                if (in_array($cat_id, array(268, 270))) {
                    $show_faska = true;
                    $faska_types = get_term_meta($cat_id, 'faska_types', true);
                    if ($faska_types) break;
                }
            }
        }
    }
    
    ?>
    
    <script>
    console.log('ParusWeb Calculators v2.0 - Loading...');
    
    // Глобальные константы
    const paintingServices = <?php echo json_encode($painting_services); ?>;
    const priceMultiplier = <?php echo $price_multiplier; ?>;
    const isMultiplierCategory = <?php echo $is_multiplier ? 'true' : 'false'; ?>;
    const isSquareMeter = <?php echo $is_square_meter ? 'true' : 'false'; ?>;
    const isRunningMeter = <?php echo $is_running_meter ? 'true' : 'false'; ?>;
    const calcSettings = <?php echo $calc_settings ? json_encode($calc_settings) : 'null'; ?>;
    const productId = <?php echo $product_id; ?>;

    console.log('Product ID:', productId);
    console.log('Painting services:', paintingServices);
    console.log('Price multiplier:', priceMultiplier);
    console.log('Calculator settings:', calcSettings);

    document.addEventListener('DOMContentLoaded', function() {
        let form = document.querySelector('form.cart') || 
                  document.querySelector('form[action*="add-to-cart"]') ||
                  document.querySelector('.single_add_to_cart_button').closest('form');
        let quantityInput = document.querySelector('input[name="quantity"]') ||
                           document.querySelector('.qty') ||
                           document.querySelector('.input-text.qty');
        
        if (!form) {
            console.error('Cart form not found!');
            return;
        }

        // Создаем контейнер для калькулятора
        const resultBlock = document.createElement('div');
        resultBlock.id = 'custom-calc-block';
        resultBlock.className = 'calc-result-container';
        resultBlock.style.marginTop = '20px';
        resultBlock.style.marginBottom = '20px';
        form.insertAdjacentElement('afterend', resultBlock);

        // Локальные переменные
        let isAutoUpdate = false;

        // ==================
        // ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
        // ==================

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

        // ==================
        // БЛОК УСЛУГ ПОКРАСКИ
        // ==================

        function createPaintingServicesBlock(currentCategoryId) {
            if (Object.keys(paintingServices).length === 0) return null;

            const paintingBlock = document.createElement('div');
            paintingBlock.id = 'painting-services-block';

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
                    <div id="painting-service-result" style="display:none; margin-top:10px;"></div>
                </div>

                <!-- Контейнер для схем и цветов покраски -->
                <div id="paint-schemes-root"></div>
            `;
            return paintingBlock;
        }

        const paintingBlock = createPaintingServicesBlock(<?php echo $product_categories[0]->term_id ?? 0; ?>);

        // ==================
        // ФУНКЦИЯ ОБНОВЛЕНИЯ ПОКРАСКИ
        // ==================

        function updatePaintingServiceCost(totalArea = null) {
            console.log('=== updatePaintingServiceCost called ===');
            console.log('  totalArea:', totalArea);
            console.log('  paintingBlock exists:', !!paintingBlock);
            
            if (!paintingBlock) {
                console.log('  No paintingBlock, returning 0');
                return 0;
            }
            
            const serviceSelect = document.getElementById('painting_service_select');
            if (!serviceSelect) {
                console.error('  ERROR: painting_service_select not found!');
                return 0;
            }
            
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            const paintingResult = document.getElementById('painting-service-result');
            
            console.log('  Selected option:', selectedOption ? selectedOption.value : 'none');
            
            if (!selectedOption || !selectedOption.value) {
                console.log('  No service selected, hiding result');
                if (paintingResult) paintingResult.style.display = 'none';
                removeHiddenFields('painting_service_');
                removeHiddenFields('pm_selected_');
                
                // Скрываем блок схем/цветов
                const schemesRoot = document.getElementById('paint-schemes-root');
                if (schemesRoot) schemesRoot.innerHTML = '';
                
                return 0;
            }
            
            const serviceKey = selectedOption.value;
            const servicePrice = parseFloat(selectedOption.dataset.price);
            const serviceName = paintingServices[serviceKey].name;
            
            console.log('  Service:', serviceName);
            console.log('  Price per m²:', servicePrice);
            
            // Создаем блок выбора схемы и цвета
            loadPaintSchemes(serviceKey);
            
            if (!totalArea || totalArea <= 0) {
                console.log('  No area, showing service name only');
                if (paintingResult) {
                    paintingResult.innerHTML = `<span style="color:#666;">Выбрана услуга: ${serviceName}<br>Введите размеры для расчета стоимости</span>`;
                    paintingResult.style.display = 'block';
                }
                
                // Сохраняем выбранную услугу даже без площади
                createHiddenField('painting_service_key', serviceKey);
                createHiddenField('painting_service_name', serviceName);
                createHiddenField('painting_service_price_per_m2', servicePrice);
                return 0;
            }
            
            const totalPaintingCost = totalArea * servicePrice;
            console.log('  Calculated cost:', totalPaintingCost, '₽');
            
            if (paintingResult) {
                paintingResult.innerHTML = `<strong>${serviceName}:</strong> ${totalPaintingCost.toFixed(2)} ₽ <span style="color:#666;">(${totalArea.toFixed(3)} м² × ${servicePrice} ₽/м²)</span>`;
                paintingResult.style.display = 'block';
            }
            
            createHiddenField('painting_service_key', serviceKey);
            createHiddenField('painting_service_name', serviceName);
            createHiddenField('painting_service_price_per_m2', servicePrice);
            createHiddenField('painting_service_area', totalArea.toFixed(3));
            createHiddenField('painting_service_total_cost', totalPaintingCost.toFixed(2));
            
            console.log('=== updatePaintingServiceCost END, returning:', totalPaintingCost, '===');
            return totalPaintingCost;
        }

        // ==================
        // ЗАГРУЗКА СХЕМ И ЦВЕТОВ ПОКРАСКИ
        // ==================

        function loadPaintSchemes(serviceKey) {
            console.log('Loading paint schemes for service:', serviceKey);
            
            const schemesRoot = document.getElementById('paint-schemes-root');
            if (!schemesRoot) {
                console.error('paint-schemes-root not found!');
                return;
            }
            
            const service = paintingServices[serviceKey];
            if (!service || !service.schemes || service.schemes.length === 0) {
                console.log('No schemes found for this service');
                schemesRoot.innerHTML = '';
                return;
            }
            
            console.log('Found schemes:', service.schemes);
            
            // ШАГ 1: Создаем блок выбора схемы
            let schemesHTML = `
                <div id="paint-scheme-selection" style="margin-top: 15px; border: 2px solid #e0e0e0; padding: 15px; border-radius: 8px; background: #f9f9f9;">
                    <h5 style="margin-bottom: 15px;">Выберите схему покраски:</h5>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
            `;
            
            service.schemes.forEach((scheme, index) => {
                schemesHTML += `
                    <label class="paint-scheme-option" data-scheme-slug="${scheme.slug}" style="cursor: pointer; padding: 10px; border: 2px solid #ddd; border-radius: 8px; transition: all 0.3s; background: #fff;">
                        <input type="radio" name="pm_selected_scheme" value="${scheme.slug}" style="display: none;">
                        <div style="font-weight: 600; margin-bottom: 5px;">${scheme.name}</div>
                        <div style="font-size: 12px; color: #666;">Выберите эту схему</div>
                    </label>
                `;
            });
            
            schemesHTML += '</div></div>';
            
            // ШАГ 2: Контейнер для цветов (будет заполнен после выбора схемы)
            schemesHTML += '<div id="paint-colors-root" style="margin-top: 15px;"></div>';
            
            schemesRoot.innerHTML = schemesHTML;
            
            // Добавляем обработчик выбора схемы
            document.querySelectorAll('.paint-scheme-option').forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    // Стилизация выбранной схемы
                    document.querySelectorAll('.paint-scheme-option').forEach(opt => {
                        opt.style.borderColor = '#ddd';
                        opt.style.boxShadow = 'none';
                    });
                    this.style.borderColor = '#4CAF50';
                    this.style.boxShadow = '0 0 8px rgba(76, 175, 80, 0.4)';
                    
                    // Загружаем цвета для выбранной схемы
                    const schemeSlug = this.dataset.schemeSlug;
                    const selectedScheme = service.schemes.find(s => s.slug === schemeSlug);
                    
                    if (selectedScheme) {
                        createHiddenField('pm_selected_scheme_name', selectedScheme.name);
                        createHiddenField('pm_selected_scheme_slug', selectedScheme.slug);
                        loadPaintColors(selectedScheme.colors, selectedScheme.name);
                    }
                });
            });
            
            // Добавляем hover эффект
            document.querySelectorAll('.paint-scheme-option').forEach(option => {
                option.addEventListener('mouseenter', function() {
                    if (!this.querySelector('input[type="radio"]').checked) {
                        this.style.borderColor = '#4CAF50';
                        this.style.transform = 'scale(1.02)';
                    }
                });
                
                option.addEventListener('mouseleave', function() {
                    if (!this.querySelector('input[type="radio"]').checked) {
                        this.style.borderColor = '#ddd';
                        this.style.transform = 'scale(1)';
                    }
                });
            });
        }

        function loadPaintColors(colors, schemeName) {
            console.log('Loading paint colors for scheme:', schemeName, colors);
            
            const colorsRoot = document.getElementById('paint-colors-root');
            if (!colorsRoot) {
                console.error('paint-colors-root not found!');
                return;
            }
            
            if (!colors || colors.length === 0) {
                console.log('No colors found for this scheme');
                colorsRoot.innerHTML = '<p style="color: #999;">Нет доступных цветов для этой схемы</p>';
                return;
            }
            
            let colorsHTML = `
                <div style="border: 2px solid #e0e0e0; padding: 15px; border-radius: 8px; background: #f9f9f9;">
                    <h5 style="margin-bottom: 15px;">Выберите цвет покраски:</h5>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px;">
            `;
            
            colors.forEach((color, index) => {
                const cleanFilename = cleanColorFilename(color.filename);
                colorsHTML += `
                    <label class="paint-color-option" style="cursor: pointer; text-align: center; transition: all 0.3s;">
                        <input type="radio" name="pm_selected_color" value="${color.filename}" data-image="${color.url}" data-clean="${cleanFilename}" style="display: none;">
                        <div style="border: 3px solid #ddd; border-radius: 8px; overflow: hidden; aspect-ratio: 1; background: #fff;">
                            <img src="${color.url}" alt="${cleanFilename}" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div style="margin-top: 5px; font-size: 11px; font-weight: 500;">${cleanFilename}</div>
                    </label>
                `;
            });
            
            colorsHTML += '</div></div>';
            
            colorsRoot.innerHTML = colorsHTML;
            
            // Добавляем обработчик выбора цвета
            document.querySelectorAll('.paint-color-option').forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    // Стилизация выбранного цвета
                    document.querySelectorAll('.paint-color-option div').forEach(div => {
                        div.style.borderColor = '#ddd';
                        div.style.boxShadow = 'none';
                    });
                    const imgContainer = this.querySelector('div');
                    imgContainer.style.borderColor = '#4CAF50';
                    imgContainer.style.boxShadow = '0 0 12px rgba(76, 175, 80, 0.6)';
                    
                    // Сохраняем выбранный цвет
                    const colorImage = radio.dataset.image;
                    const colorFilename = radio.dataset.clean;
                    
                    createHiddenField('pm_selected_color_image', colorImage);
                    createHiddenField('pm_selected_color_filename', colorFilename);
                    
                    console.log('Color selected:', colorFilename, colorImage);
                    
                    // Пересчитываем калькулятор
                    recalculateActiveCalculator();
                });
            });
            
            // Добавляем hover эффект для цветов
            document.querySelectorAll('.paint-color-option').forEach(option => {
                option.addEventListener('mouseenter', function() {
                    if (!this.querySelector('input[type="radio"]').checked) {
                        const imgContainer = this.querySelector('div');
                        imgContainer.style.borderColor = '#4CAF50';
                        imgContainer.style.transform = 'scale(1.05)';
                    }
                });
                
                option.addEventListener('mouseleave', function() {
                    if (!this.querySelector('input[type="radio"]').checked) {
                        const imgContainer = this.querySelector('div');
                        imgContainer.style.borderColor = '#ddd';
                        imgContainer.style.transform = 'scale(1)';
                    }
                });
            });
        }

        // Функция очистки имени файла цвета
        function cleanColorFilename(filename) {
            // Убираем расширение
            filename = filename.replace(/\.(jpg|jpeg|png|webp|gif)$/i, '');
            
            // Убираем суффиксы типа -180, -1, -kopiya, _180 и т.д.
            filename = filename.replace(/[-_](180|kopiya|copy|1|2|3)$/i, '');
            
            // Шаблоны для извлечения только кода цвета
            const patterns = [
                { regex: /^img[_-]?(\d+)[-_].*$/i, replace: '$1' },
                { regex: /^(\d+)[-_]\d+$/i, replace: '$1' },
                { regex: /^[a-z]+[_-]?[a-z]*[_-]?(\d+)$/i, replace: '$1' },
                { regex: /^(\d+)$/i, replace: '$1' }
            ];
            
            for (const pattern of patterns) {
                if (pattern.regex.test(filename)) {
                    filename = filename.replace(pattern.regex, pattern.replace);
                    break;
                }
            }
            
            return filename;
        }

        // Функция пересчета активного калькулятора
        function recalculateActiveCalculator() {
            const areaInput = document.getElementById('calc_area_input');
            const widthEl = document.getElementById('custom_width');
            const lengthEl = document.getElementById('custom_length');
            const multWidthEl = document.getElementById('mult_width');
            const multLengthEl = document.getElementById('mult_length');
            const rmLengthEl = document.getElementById('rm_length');
            const sqWidthEl = document.getElementById('sq_width');
            const sqLengthEl = document.getElementById('sq_length');
            
            if (areaInput && areaInput.value && parseFloat(areaInput.value) > 0) {
                console.log('→ Recalculating AREA calculator');
                updateAreaCalc();
            } else if (widthEl && lengthEl && widthEl.value && lengthEl.value) {
                console.log('→ Recalculating DIMENSIONS calculator');
                updateDimCalc(true);
            } else if (multWidthEl && multLengthEl && multWidthEl.value && multLengthEl.value) {
                console.log('→ Recalculating MULTIPLIER calculator');
                updateMultiplierCalc();
            } else if (rmLengthEl && rmLengthEl.value) {
                console.log('→ Recalculating RUNNING METER calculator');
                updateRunningMeterCalc();
            } else if (sqWidthEl && sqLengthEl && sqWidthEl.value && sqLengthEl.value) {
                console.log('→ Recalculating SQUARE METER calculator');
                updateSquareMeterCalc();
            }
        }

        // Обработчик изменения услуги покраски
        if (paintingBlock) {
            const serviceSelect = document.getElementById('painting_service_select');
            if (serviceSelect) {
                console.log('Setting up painting service change handler');
                
                serviceSelect.addEventListener('change', function() {
                    console.log('=== PAINTING SERVICE CHANGED ===');
                    console.log('Selected:', this.options[this.selectedIndex].text);
                    
                    // Определяем активный калькулятор и пересчитываем
                    recalculateActiveCalculator();
                });
            } else {
                console.error('ERROR: painting_service_select not found in DOM!');
            }
        }

        // ==================
        // КАЛЬКУЛЯТОРЫ
        // ==================

        <?php if($pack_area && $is_target): ?>
        // Калькулятор площади
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

        <?php if($dims && $is_target): ?>
        // Калькулятор размеров
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
            }
        }

        widthEl.addEventListener('change', () => updateDimCalc(true));
        lengthEl.addEventListener('change', () => updateDimCalc(true));
        
        updateDimCalc(false);
        <?php endif; ?>

        <?php if($is_multiplier && !$show_falsebalk_calc && !$is_running_meter): ?>
        // Калькулятор с множителем (столярка)
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
            <div id="faska_selected" style="display: none; margin-top: 20px; text-align: center; padding: 10px; border: 2px solid #4CAF50; border-radius: 8px;">
                <p>Выбранная фаска: <span id="faska_selected_name"></span></p>
                <img id="faska_selected_image" src="" alt="" style="max-width: 300px;">
                <div style="margin-top: 10px;">
                    <button type="button" id="change_faska_btn" style="padding: 8px 20px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer;">Изменить выбор</button>
                </div>
            </div>
        </div>`;
        <?php endif; ?>

        calcHTML += '</div><div id="calc_mult_result" style="margin-top:10px; font-size:1.3em"></div>';
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
                if (!isAutoUpdate && multWidthEl.value && multLengthEl.value) {
                    updateMultiplierCalc();
                }
            });
        }
        <?php endif; ?>

        console.log('✅ ParusWeb Calculators v2.0 - Initialized successfully');
    });
    </script>
    <?php
}, 20);
