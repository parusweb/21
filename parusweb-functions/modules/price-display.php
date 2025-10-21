<?php
/**
 * Модуль: Price Display
 * Описание: ПОЛНОЕ отображение цен из оригинального functions.php (строки 240-346)
 * Зависимости: product-calculations, category-helpers
 */

if (!defined('ABSPATH')) {
    exit;
}

// --- ОБНОВЛЕННЫЙ фильтр для цены ---
add_filter('woocommerce_get_price_html', function($price, $product) {
    $product_id = $product->get_id();
    
    // Категории для скрытия базовой цены (265-271)
    $hide_base_price_categories = range(265, 271);
    $should_hide_base_price = has_term($hide_base_price_categories, 'product_cat', $product_id);
    
    // Категории для "лист"
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);

    // Категории для пиломатериалов
    $lumber_categories = range(87, 93);

    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
    $is_lumber_category = has_term($lumber_categories, 'product_cat', $product_id);
    $is_square_meter = is_square_meter_category($product_id);
    $is_running_meter = is_running_meter_category($product_id);

    if ($is_leaf_category) {
        $price = str_replace('упак.', 'лист', $price);
    }
    
    
    $price_multiplier = get_price_multiplier($product->get_id());
    
    // Для столярных изделий за пог.м
    if ($is_running_meter) {
        $base_price_per_m = floatval($product->get_regular_price() ?: $product->get_price());
        if ($base_price_per_m) {
            // Получаем минимальные размеры
            $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;
            $min_length = round($min_length, 2);
            $min_price = $base_price_per_m * $min_length * $price_multiplier;
            
            if (is_product()) {
                // Если нужно скрыть базовую цену - показываем только цену за шт.
                if ($should_hide_base_price) {
                    return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . $min_length . ' м)</span>';
                }
                
                return wc_price($base_price_per_m) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за пог. м</span><br>' .
                       '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . $min_length . ' м)</span>';
            } else {
                // Если нужно скрыть базовую цену - показываем только цену за шт.
                if ($should_hide_base_price) {
                    return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
                }
                
                return wc_price($base_price_per_m) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за пог. м</span><br>' .
                       '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
            }
        }
    }

    // Для столярных изделий за кв.м
    if ($is_square_meter) {
        $base_price_per_m2 = floatval($product->get_regular_price() ?: $product->get_price());
        if ($base_price_per_m2) {
            // Получаем минимальные размеры
            $is_falshbalka = has_term(266, 'product_cat', $product_id);
            if ($is_falshbalka) {
                // Для фальшбалок (Г-образная форма): 70x70мм по умолчанию, 2 плоскости
                $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true)) ?: 70;
                $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;
                $min_length = round($min_length, 2);
                // Площадь Г-образной формы: 2 плоскости по 70мм каждая
                $min_area = 2 * ($min_width / 1000) * $min_length;
            } else {
                $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true)) ?: 100;
                $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 0.01;
                $min_length = round($min_length, 2);
                $min_area = ($min_width / 1000) * $min_length;
            }
            $min_price = $base_price_per_m2 * $min_area * $price_multiplier;
            
            if (is_product()) {
                // Если нужно скрыть базовую цену - показываем только цену за шт.
                if ($should_hide_base_price) {
                    return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 2) . ' м²)</span>';
                }
                
                return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт. (' . number_format($min_area, 2) . ' м²)</span>';
            } else {
                // Если нужно скрыть базовую цену - показываем только цену за шт.
                if ($should_hide_base_price) {
                    return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
                }
                
                return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
            }
        }
    }

    // Для пиломатериалов и листовых (категории 87-93 + листовые)
    if (($is_lumber_category || $is_leaf_category) && is_in_target_categories($product_id)) {
        $base_price_per_m2 = floatval($product->get_regular_price() ?: $product->get_price());
        $pack_area = extract_area_with_qty($product->get_name(), $product_id);
        
        if ($base_price_per_m2) {
            if (is_product() && $pack_area) {
                $price_per_pack = $base_price_per_m2 * $pack_area;
                $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
                
                return wc_price($base_price_per_m2) . '<span style="font-size:1.3em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:1.3em;"><strong>' . wc_price($price_per_pack) . '</strong> за 1 ' . $unit_text . '</span>';
            } elseif ($pack_area) {
                $price_per_pack = $base_price_per_m2 * $pack_area;
                $unit_text = $is_leaf_category ? 'лист' : 'упаковку';
                
                return wc_price($base_price_per_m2) . '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span><br>' .
                       '<span style="font-size:1.1em;"><strong>' . wc_price($price_per_pack) . '</strong> за ' . $unit_text . '</span>';
            } else {
                $price .= '<span style="font-size:0.9em; font-weight:600">&nbsp;за м<sup>2</sup></span>';
            }
        }
    }

    return $price;
}, 20, 2);
