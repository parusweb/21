<?php
/**
 * Модуль: Cart Functionality  
 * Описание: ПОЛНЫЙ функционал корзины из оригинального functions.php (строки 1291-1452)
 * Зависимости: product-calculations, category-helpers
 */

if (!defined('ABSPATH')) {
    exit;
}

// --- Добавляем выбранные данные в корзину ---
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id){

    // Проверяем, что товар из целевых категорий
    if (!is_in_target_categories($product_id)) {
        return $cart_item_data;
    }

    $product = wc_get_product($product_id);
    if (!$product) return $cart_item_data;

    $title = $product->get_name();
    $pack_area = extract_area_with_qty($title, $product_id);
    $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());

    // Тип товара
    $leaf_parent_id = 190;
    $leaf_children = [191, 127, 94];
    $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
    $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);

    // Услуга покраски
    $painting_service = null;
    if (!empty($_POST['painting_service_key'])) {
        $painting_service = [
            'key' => sanitize_text_field($_POST['painting_service_key']),
            'name' => sanitize_text_field($_POST['painting_service_name']),
            'price_per_m2' => floatval($_POST['painting_service_price_per_m2']),
            'area' => floatval($_POST['painting_service_area']),
            'total_cost' => floatval($_POST['painting_service_total_cost'])
        ];

        // Добавляем цвет
        if (!empty($_POST['pm_selected_color_filename'])) {
            $color_filename = sanitize_text_field($_POST['pm_selected_color_filename']);
            $painting_service['color_filename'] = $color_filename;
            $painting_service['name_with_color'] = $painting_service['name'] . ' (' . $color_filename . ')';
        }
    }
    
    // Данные схем покраски
    if (!empty($_POST['pm_selected_scheme_name'])) {
        $cart_item_data['pm_selected_scheme_name'] = sanitize_text_field($_POST['pm_selected_scheme_name']);
    }
    if (!empty($_POST['pm_selected_scheme_slug'])) {
        $cart_item_data['pm_selected_scheme_slug'] = sanitize_text_field($_POST['pm_selected_scheme_slug']);
    }
    if (!empty($_POST['pm_selected_color_image'])) {
        $cart_item_data['pm_selected_color_image'] = esc_url_raw($_POST['pm_selected_color_image']);
    }
    if (!empty($_POST['pm_selected_color_filename'])) {
        $cart_item_data['pm_selected_color'] = sanitize_text_field($_POST['pm_selected_color_filename']);
    }

    // ПРИОРИТЕТЫ (в порядке важности):

    // 1. Калькулятор площади
    if (!empty($_POST['custom_area_packs']) && !empty($_POST['custom_area_area_value'])) {
        $cart_item_data['custom_area_calc'] = [
            'packs' => intval($_POST['custom_area_packs']),
            'area' => floatval($_POST['custom_area_area_value']),
            'total_price' => floatval($_POST['custom_area_total_price']),
            'grand_total' => floatval($_POST['custom_area_grand_total'] ?? $_POST['custom_area_total_price']),
            'is_leaf' => $is_leaf_category,
            'painting_service' => $painting_service
        ];
        return $cart_item_data;
    }

    // 2. Калькулятор размеров (старый)
    if (!empty($_POST['custom_width_val']) && !empty($_POST['custom_length_val'])) {
        $cart_item_data['custom_dimensions'] = [
            'width' => intval($_POST['custom_width_val']),
            'length'=> intval($_POST['custom_length_val']),
            'price'=> floatval($_POST['custom_dim_price']),
            'grand_total' => floatval($_POST['custom_dim_grand_total'] ?? $_POST['custom_dim_price']),
            'is_leaf' => $is_leaf_category,
            'painting_service' => $painting_service
        ];
        return $cart_item_data;
    }

    // 3. НОВОЕ: Калькулятор с множителем (категории 265-271)
    if (!empty($_POST['custom_mult_width']) && !empty($_POST['custom_mult_length'])) {
        error_log('Adding multiplier calc to cart: ' . print_r($_POST, true));
        
        $cart_item_data['custom_multiplier_calc'] = [
            'width' => floatval($_POST['custom_mult_width']),
            'length' => floatval($_POST['custom_mult_length']),
            'quantity' => intval($_POST['custom_mult_quantity'] ?? 1),
            'area_per_item' => floatval($_POST['custom_mult_area_per_item']),
            'total_area' => floatval($_POST['custom_mult_total_area']),
            'multiplier' => floatval($_POST['custom_mult_multiplier']),
            'price' => floatval($_POST['custom_mult_price']),
            'grand_total' => floatval($_POST['custom_mult_grand_total'] ?? $_POST['custom_mult_price']),
            'painting_service' => $painting_service
        ];
        
        // Сохраняем выбранную фаску если есть
        if (!empty($_POST['selected_faska_type'])) {
            $cart_item_data['selected_faska_type'] = sanitize_text_field($_POST['selected_faska_type']);
        }
        
        error_log('Multiplier calc data: ' . print_r($cart_item_data['custom_multiplier_calc'], true));
        return $cart_item_data;
    }

    // 4. НОВОЕ: Калькулятор погонных метров (включая фальшбалки)
    if (!empty($_POST['custom_rm_length'])) {
        $rm_data = [
            'width' => floatval($_POST['custom_rm_width'] ?? 0),
            'length' => floatval($_POST['custom_rm_length']),
            'quantity' => intval($_POST['custom_rm_quantity'] ?? 1),
            'total_length' => floatval($_POST['custom_rm_total_length']),
            'painting_area' => floatval($_POST['custom_rm_painting_area'] ?? 0),
            'multiplier' => floatval($_POST['custom_rm_multiplier'] ?? 1),
            'price' => floatval($_POST['custom_rm_price']),
            'grand_total' => floatval($_POST['custom_rm_grand_total'] ?? $_POST['custom_rm_price']),
            'painting_service' => $painting_service
        ];
        
        // Дополнительные поля для фальшбалок
        if (!empty($_POST['custom_rm_shape'])) {
            $rm_data['shape'] = sanitize_text_field($_POST['custom_rm_shape']);
            $rm_data['shape_label'] = sanitize_text_field($_POST['custom_rm_shape_label']);
            $rm_data['height'] = floatval($_POST['custom_rm_height'] ?? 0);
            
            // Для П-образной может быть вторая высота
            if (!empty($_POST['custom_rm_height2'])) {
                $rm_data['height2'] = floatval($_POST['custom_rm_height2']);
            }
        }
        
        $cart_item_data['custom_running_meter_calc'] = $rm_data;
        error_log('PM: Added running meter calc to cart - ' . print_r($rm_data, true));
        return $cart_item_data;
    }

    // 5. НОВОЕ: Калькулятор квадратных метров
    if (!empty($_POST['custom_sq_width']) && !empty($_POST['custom_sq_length'])) {
        $cart_item_data['custom_square_meter_calc'] = [
            'width' => floatval($_POST['custom_sq_width']),
            'length' => floatval($_POST['custom_sq_length']),
            'quantity' => intval($_POST['custom_sq_quantity'] ?? 1),
            'area_per_item' => floatval($_POST['custom_sq_area_per_item']),
            'total_area' => floatval($_POST['custom_sq_total_area']),
            'multiplier' => floatval($_POST['custom_sq_multiplier'] ?? 1),
            'price' => floatval($_POST['custom_sq_price']),
            'grand_total' => floatval($_POST['custom_sq_grand_total'] ?? $_POST['custom_sq_price']),
            'painting_service' => $painting_service
        ];
        return $cart_item_data;
    }

    // 6. Покупка из карточки товара
    if (!empty($_POST['card_purchase']) && $_POST['card_purchase'] === '1' && $pack_area > 0) {
        $cart_item_data['card_pack_purchase'] = [
            'area' => $pack_area,
            'price_per_m2' => $base_price_m2,
            'total_price' => $base_price_m2 * $pack_area,
            'is_leaf' => $is_leaf_category,
            'unit_type' => $is_leaf_category ? 'лист' : 'упаковка',
            'painting_service' => $painting_service
        ];
        return $cart_item_data;
    }

    // 7. Обычная покупка без калькулятора
    if ($pack_area > 0) {
        $cart_item_data['standard_pack_purchase'] = [
            'area' => $pack_area,
            'price_per_m2' => $base_price_m2,
            'total_price' => $base_price_m2 * $pack_area,
            'is_leaf' => $is_leaf_category,
            'unit_type' => $is_leaf_category ? 'лист' : 'упаковка',
            'painting_service' => $painting_service
        ];
    }

    return $cart_item_data;
}, 10, 3);

// --- Отображаем выбранные размеры/площадь в корзине и заказе ---
add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
    // Данные схем покраски
    if (!empty($cart_item['pm_selected_scheme_name'])) {
        $item_data[] = [
            'name' => 'Схема покраски',
            'value' => $cart_item['pm_selected_scheme_name']
        ];
    }
    if (!empty($cart_item['pm_selected_color'])) {
        $color_display = $cart_item['pm_selected_color'];
        
        // Добавляем миниатюру изображения и код цвета
        if (!empty($cart_item['pm_selected_color_image'])) {
            $image_url = $cart_item['pm_selected_color_image'];
            $filename = !empty($cart_item['pm_selected_color_filename']) ? $cart_item['pm_selected_color_filename'] : '';
            
            $color_display = '<div style="display:flex; align-items:center; gap:10px;">';
            $color_display .= '<img src="' . esc_url($image_url) . '" style="width:50px; height:50px; object-fit:cover; border:2px solid #ddd; border-radius:4px;" alt="' . esc_attr($filename) . '">';
            $color_display .= '<span>' . esc_html($cart_item['pm_selected_color']) . '</span>';
            $color_display .= '</div>';
        }
        
        $item_data[] = [
            'name' => 'Цвет',
            'value' => $color_display
        ];
    }

    // 1. Данные калькулятора площади
    if (!empty($cart_item['custom_area_calc'])) {
        $data = $cart_item['custom_area_calc'];
        $unit = !empty($data['is_leaf']) ? 'листа' : 'упаковки';
        
        $item_data[] = [
            'name' => 'Площадь ' . $unit,
            'value' => number_format($data['area'], 2, ',', ' ') . ' м²'
        ];
        
        $item_data[] = [
            'name' => 'Количество',
            'value' => $data['packs'] . ' ' . ($data['packs'] > 1 ? ($data['is_leaf'] ? 'листов' : 'упаковок') : $unit)
        ];
        
        if (!empty($data['painting_service'])) {
            $painting = $data['painting_service'];
            $paint_display = $painting['name'];
            if (!empty($painting['color_filename'])) {
                $paint_display .= ' (' . $painting['color_filename'] . ')';
            }
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $paint_display
            ];
        }
    }

    // 2. Данные калькулятора размеров
    if (!empty($cart_item['custom_dimensions'])) {
        $data = $cart_item['custom_dimensions'];
        
        $item_data[] = [
            'name' => 'Размеры',
            'value' => $data['width'] . ' × ' . $data['length'] . ' мм'
        ];
        
        if (!empty($data['painting_service'])) {
            $painting = $data['painting_service'];
            $paint_display = $painting['name'];
            if (!empty($painting['color_filename'])) {
                $paint_display .= ' (' . $painting['color_filename'] . ')';
            }
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $paint_display
            ];
        }
    }

    // 3. Данные калькулятора с множителем
    if (!empty($cart_item['custom_multiplier_calc'])) {
        $data = $cart_item['custom_multiplier_calc'];
        
        $item_data[] = [
            'name' => 'Размеры',
            'value' => $data['width'] . ' мм × ' . number_format($data['length'], 2, ',', ' ') . ' м'
        ];
        
        $item_data[] = [
            'name' => 'Площадь',
            'value' => number_format($data['total_area'], 3, ',', ' ') . ' м²'
        ];
        
        // Фаска
        if (!empty($cart_item['selected_faska_type'])) {
            $item_data[] = [
                'name' => 'Тип фаски',
                'value' => $cart_item['selected_faska_type']
            ];
        }
        
        if (!empty($data['painting_service'])) {
            $painting = $data['painting_service'];
            $paint_display = $painting['name'];
            if (!empty($painting['color_filename'])) {
                $paint_display .= ' (' . $painting['color_filename'] . ')';
            }
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $paint_display
            ];
        }
    }

    // 4. Данные калькулятора погонных метров
    if (!empty($cart_item['custom_running_meter_calc'])) {
        $data = $cart_item['custom_running_meter_calc'];
        
        // Для фальшбалок показываем форму
        if (!empty($data['shape_label'])) {
            $item_data[] = [
                'name' => 'Форма сечения',
                'value' => $data['shape_label']
            ];
            
            if (!empty($data['width'])) {
                $item_data[] = [
                    'name' => 'Ширина',
                    'value' => $data['width'] . ' мм'
                ];
            }
            
            if (!empty($data['height'])) {
                $item_data[] = [
                    'name' => 'Высота',
                    'value' => $data['height'] . ' мм'
                ];
            }
            
            if (!empty($data['height2'])) {
                $item_data[] = [
                    'name' => 'Высота 2',
                    'value' => $data['height2'] . ' мм'
                ];
            }
        }
        
        $item_data[] = [
            'name' => 'Длина',
            'value' => number_format($data['length'], 2, ',', ' ') . ' м.п.'
        ];
        
        $item_data[] = [
            'name' => 'Общая длина',
            'value' => number_format($data['total_length'], 2, ',', ' ') . ' м.п.'
        ];
        
        if (!empty($data['painting_service'])) {
            $painting = $data['painting_service'];
            $paint_display = $painting['name'];
            if (!empty($painting['color_filename'])) {
                $paint_display .= ' (' . $painting['color_filename'] . ')';
            }
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $paint_display
            ];
        }
    }

    // 5. Данные калькулятора квадратных метров
    if (!empty($cart_item['custom_square_meter_calc'])) {
        $data = $cart_item['custom_square_meter_calc'];
        
        $item_data[] = [
            'name' => 'Размеры',
            'value' => $data['width'] . ' мм × ' . number_format($data['length'], 2, ',', ' ') . ' м'
        ];
        
        $item_data[] = [
            'name' => 'Площадь',
            'value' => number_format($data['total_area'], 3, ',', ' ') . ' м²'
        ];
        
        if (!empty($data['painting_service'])) {
            $painting = $data['painting_service'];
            $paint_display = $painting['name'];
            if (!empty($painting['color_filename'])) {
                $paint_display .= ' (' . $painting['color_filename'] . ')';
            }
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $paint_display
            ];
        }
    }

    // 6. Данные покупки из карточки
    if (!empty($cart_item['card_pack_purchase'])) {
        $data = $cart_item['card_pack_purchase'];
        
        $item_data[] = [
            'name' => 'Площадь ' . $data['unit_type'],
            'value' => number_format($data['area'], 2, ',', ' ') . ' м²'
        ];
        
        if (!empty($data['painting_service'])) {
            $painting = $data['painting_service'];
            $paint_display = $painting['name'];
            if (!empty($painting['color_filename'])) {
                $paint_display .= ' (' . $painting['color_filename'] . ')';
            }
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $paint_display
            ];
        }
    }

    // 7. Данные стандартной покупки
    if (!empty($cart_item['standard_pack_purchase'])) {
        $data = $cart_item['standard_pack_purchase'];
        
        $item_data[] = [
            'name' => 'Площадь ' . $data['unit_type'],
            'value' => number_format($data['area'], 2, ',', ' ') . ' м²'
        ];
        
        if (!empty($data['painting_service'])) {
            $painting = $data['painting_service'];
            $paint_display = $painting['name'];
            if (!empty($painting['color_filename'])) {
                $paint_display .= ' (' . $painting['color_filename'] . ')';
            }
            $item_data[] = [
                'name' => 'Услуга покраски',
                'value' => $paint_display
            ];
        }
    }

    return $item_data;
}, 10, 2);

// --- Пересчет цен в корзине ---
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $new_price = null;

        // Калькулятор площади
        if (!empty($cart_item['custom_area_calc'])) {
            $data = $cart_item['custom_area_calc'];
            $new_price = isset($data['grand_total']) ? $data['grand_total'] : $data['total_price'];
        }
        
        // Калькулятор размеров
        elseif (!empty($cart_item['custom_dimensions'])) {
            $data = $cart_item['custom_dimensions'];
            $new_price = isset($data['grand_total']) ? $data['grand_total'] : $data['price'];
        }
        
        // Калькулятор с множителем
        elseif (!empty($cart_item['custom_multiplier_calc'])) {
            $data = $cart_item['custom_multiplier_calc'];
            $new_price = isset($data['grand_total']) ? $data['grand_total'] : $data['price'];
        }
        
        // Калькулятор погонных метров
        elseif (!empty($cart_item['custom_running_meter_calc'])) {
            $data = $cart_item['custom_running_meter_calc'];
            $new_price = isset($data['grand_total']) ? $data['grand_total'] : $data['price'];
        }
        
        // Калькулятор квадратных метров
        elseif (!empty($cart_item['custom_square_meter_calc'])) {
            $data = $cart_item['custom_square_meter_calc'];
            $new_price = isset($data['grand_total']) ? $data['grand_total'] : $data['price'];
        }
        
        // Покупка из карточки
        elseif (!empty($cart_item['card_pack_purchase'])) {
            $data = $cart_item['card_pack_purchase'];
            $new_price = $data['total_price'];
            if (!empty($data['painting_service']['total_cost'])) {
                $new_price += $data['painting_service']['total_cost'];
            }
        }
        
        // Стандартная покупка
        elseif (!empty($cart_item['standard_pack_purchase'])) {
            $data = $cart_item['standard_pack_purchase'];
            $new_price = $data['total_price'];
            if (!empty($data['painting_service']['total_cost'])) {
                $new_price += $data['painting_service']['total_cost'];
            }
        }

        // Устанавливаем новую цену
        if ($new_price !== null) {
            $cart_item['data']->set_price($new_price);
        }
    }
}, 10, 1);

// --- Корректировка количества для кастомных расчетов ---
add_filter('woocommerce_add_to_cart_quantity', function($quantity, $product_id) {
    // Для товаров с кастомными расчетами возвращаем 1
    // Реальное количество уже учтено в цене
    if (!empty($_POST['custom_area_packs'])) {
        return intval($_POST['custom_area_packs']);
    }
    
    if (!empty($_POST['custom_mult_quantity'])) {
        return intval($_POST['custom_mult_quantity']);
    }
    
    if (!empty($_POST['custom_rm_quantity'])) {
        return intval($_POST['custom_rm_quantity']);
    }
    
    if (!empty($_POST['custom_sq_quantity'])) {
        return intval($_POST['custom_sq_quantity']);
    }
    
    return $quantity;
}, 10, 2);

// --- Синхронизация количества с кастомными данными ---
add_filter('woocommerce_add_to_cart', function($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Проверяем есть ли кастомные расчеты
    $has_custom_calc = !empty($cart_item_data['custom_area_calc']) ||
                       !empty($cart_item_data['custom_dimensions']) ||
                       !empty($cart_item_data['custom_multiplier_calc']) ||
                       !empty($cart_item_data['custom_running_meter_calc']) ||
                       !empty($cart_item_data['custom_square_meter_calc']);
    
    if ($has_custom_calc) {
        // Для товаров с кастомными расчетами устанавливаем правильное количество
        $cart = WC()->cart->get_cart();
        
        if (isset($cart[$cart_item_key])) {
            $correct_quantity = 1;
            
            if (!empty($cart_item_data['custom_area_calc'])) {
                $correct_quantity = $cart_item_data['custom_area_calc']['packs'];
            }
            elseif (!empty($cart_item_data['custom_multiplier_calc'])) {
                $correct_quantity = $cart_item_data['custom_multiplier_calc']['quantity'];
            }
            elseif (!empty($cart_item_data['custom_running_meter_calc'])) {
                $correct_quantity = $cart_item_data['custom_running_meter_calc']['quantity'];
            }
            elseif (!empty($cart_item_data['custom_square_meter_calc'])) {
                $correct_quantity = $cart_item_data['custom_square_meter_calc']['quantity'];
            }
            
            WC()->cart->set_quantity($cart_item_key, $correct_quantity, false);
        }
    }
}, 10, 6);

