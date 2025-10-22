

// ===============================
// Исправление отображения в корзине
// ===============================

// Вспомогательная функция для склонения
function get_russian_plural_for_cart($n, $forms) {
    $n = abs($n);
    $n %= 100;
    if ($n > 10 && $n < 20) return $forms[2];
    $n %= 10;
    if ($n === 1) return $forms[0];
    if ($n >= 2 && $n <= 4) return $forms[1];
    return $forms[2];
}




// --- Изменяем отображение цены в корзине для наших товаров ---
add_filter('woocommerce_cart_item_price', function($price, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $product_id = $product->get_id();
    
    // Проверяем, что это наш товар
    if (!is_in_target_categories($product_id)) {
        return $price;
    }
    
    // Если товар добавлен из карточки или через калькуляторы
    if (isset($cart_item['card_pack_purchase']) || 
        isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions'])) {
        
        $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
        $current_price = floatval($product->get_price());
        
        // Определяем тип товара для правильного отображения единицы
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
        $unit_text = $is_leaf_category ? 'лист' : 'упаковка';
        
        // Показываем цену за упаковку/лист и базовую цену за м²
        return wc_price($current_price) . ' за ' . $unit_text . '<br>' .
               '<small style="color: #666;">' . wc_price($base_price_m2) . ' за м²</small>';
    }
    
    return $price;
}, 10, 3);

// --- Изменяем отображение итоговой цены в строке корзины ---
add_filter('woocommerce_cart_item_subtotal', function($subtotal, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $product_id = $product->get_id();
    
    // Проверяем, что это наш товар
    if (!is_in_target_categories($product_id)) {
        return $subtotal;
    }
    
    // Если товар добавлен из карточки или через калькуляторы
    if (isset($cart_item['card_pack_purchase']) || 
        isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions'])) {
        
        $quantity = $cart_item['quantity'];
        $current_price = floatval($product->get_price());
        $total = $current_price * $quantity;
        
        // Определяем тип товара
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
        $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
        
        $plural = get_russian_plural_for_cart($quantity, $unit_forms);
        
        // Показываем итоговую сумму с указанием количества единиц
        return '<strong>' . wc_price($total) . '</strong><br>' .
               '<small style="color: #666;">' . $quantity . ' ' . $plural . '</small>';
    }
    
    return $subtotal;
}, 10, 3);

// --- Дополнительно: исправляем отображение в мини-корзине (виджет) ---
add_filter('woocommerce_widget_cart_item_quantity', function($quantity, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $product_id = $product->get_id();
    
    // Проверяем, что это наш товар
    if (!is_in_target_categories($product_id)) {
        return $quantity;
    }
    
    // Если товар добавлен из карточки или через калькуляторы
    if (isset($cart_item['card_pack_purchase']) || 
        isset($cart_item['custom_area_calc']) || 
        isset($cart_item['custom_dimensions'])) {
        
        $qty = $cart_item['quantity'];
        $current_price = floatval($product->get_price());
        $base_price_m2 = floatval($product->get_regular_price() ?: $product->get_price());
        
        // Определяем тип товара
        $leaf_parent_id = 190;
        $leaf_children = [191, 127, 94];
        $leaf_ids = array_merge([$leaf_parent_id], $leaf_children);
        $is_leaf_category = has_term($leaf_ids, 'product_cat', $product_id);
        $unit_forms = $is_leaf_category ? ['лист', 'листа', 'листов'] : ['упаковка', 'упаковки', 'упаковок'];
        
        $plural = get_russian_plural_for_cart($qty, $unit_forms);
        
        // Возвращаем количество с единицей измерения и ценой за м²
        return '<span class="quantity">' . $qty . ' ' . $plural . ' × ' . wc_price($current_price) . '</span><br>' .
               '<small style="color: #999; font-size: 0.9em;">(' . wc_price($base_price_m2) . ' за м²)</small>';
    }
    
    return $quantity;
}, 10, 3);

require_once get_stylesheet_directory() . '/inc/pm-paint-schemes.php';












// -----------------------
// код ЛК, полей, меню и корзины
// -----------------------
//parusweb
// Отключенеие цифровых товаров и удаление платежного адреса
add_filter( 'woocommerce_account_menu_items', 'remove_my_account_downloads', 999 );
function remove_my_account_downloads( $items ) {
    unset( $items['downloads'] );
    return $items;
}

// Переименовать пункт меню "Адреса" в "Адрес доставки"
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    if ( isset( $items['edit-address'] ) ) {
        $items['edit-address'] = 'Адрес доставки';
    }
    return $items;
});

// Убрать заголовок "Платёжный адрес", оставить "Адрес доставки"
add_filter( 'woocommerce_my_account_my_address_title', function( $title, $address_type, $customer_id ) {
    if ( $address_type === 'billing' ) {
        return ''; 
    }
    if ( $address_type === 'shipping' ) {
        return 'Адрес доставки';
    }
    return $title;
}, 10, 3 );

// Скрыть блок платёжного адреса на странице "Адреса"
add_filter( 'woocommerce_my_account_get_addresses', function( $addresses, $customer_id ) {
    unset( $addresses['billing'] ); 
    return $addresses;
}, 10, 2 );

// Шорткод для количества товаров в категории
function wc_product_count_by_cat_id($atts) {
    $atts = shortcode_atts( array(
        'id' => 0,
    ), $atts, 'wc_cat_count_id' );

    $cat_id = intval($atts['id']);
    if ($cat_id <= 0) return '';

    $term = get_term($cat_id, 'product_cat');
    if (!$term || is_wp_error($term)) return '';

    $count = $term->count;

    return ($count > 0) ? $count : 'нет';
}
add_shortcode('wc_cat_count_id', 'wc_product_count_by_cat_id');

// -----------------------
// Кастомизация Личного Кабинета WooCommerce
// -----------------------
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    if ( isset( $items['edit-account'] ) ) {
        $items['edit-account'] = 'Мои данные';
    }

    if ( isset( $items['orders'] ) ) {
        unset( $items['orders'] );
    }

    $items['cart'] = 'Корзина';
    $items['orders'] = 'Мои заказы';

    return $items;
});

// Регистрация endpoint "Корзина"
add_action( 'init', function() {
    add_rewrite_endpoint( 'cart', EP_ROOT | EP_PAGES );
});

// Вывод корзины во вкладке "Корзина"
add_action( 'woocommerce_account_cart_endpoint', function() {
    echo do_shortcode('[woocommerce_cart]');
});

// Поля типа клиента и реквизитов в ЛК
add_action( 'woocommerce_edit_account_form', function() {
    $user_id = get_current_user_id();
    $client_type = get_user_meta( $user_id, 'client_type', true );

    $fields = [
        'billing_full_name'      => 'Полное наименование (или ФИО предпринимателя)',
        'billing_short_name'     => 'Краткое наименование',
        'billing_legal_address'  => 'Юридический адрес',
        'billing_fact_address'   => 'Фактический адрес',
        'billing_inn'            => 'ИНН',
        'billing_kpp'            => 'КПП (только для юрлиц)',
        'billing_ogrn'           => 'ОГРН / ОГРНИП',
        'billing_director'       => 'Должность и ФИО руководителя',
        'billing_buh'            => 'ФИО главного бухгалтера',
        'billing_dover'          => 'Лицо по доверенности',
        'billing_bank'           => 'Наименование банка',
        'billing_bik'            => 'БИК',
        'billing_korr'           => 'Корреспондентский счёт',
        'billing_rs'             => 'Расчётный счёт',
    ];
    ?>
    <p class="form-row form-row-wide">
        <label for="client_type">Тип клиента</label>
        <select name="client_type" id="client_type">
            <option value="fiz" <?php selected( $client_type, 'fiz' ); ?>>Физическое лицо</option>
            <option value="jur" <?php selected( $client_type, 'jur' ); ?>>Юридическое лицо / ИП</option>
        </select>
    </p>

    <div id="jur-fields" style="<?php echo $client_type === 'jur' ? '' : 'display:none;'; ?>">
        <?php foreach ( $fields as $meta_key => $label ) : 
            $val = get_user_meta( $user_id, $meta_key, true );
        ?>
            <p class="form-row form-row-wide">
                <label for="<?php echo $meta_key; ?>"><?php echo esc_html( $label ); ?></label>
                <input type="text" name="<?php echo $meta_key; ?>" id="<?php echo $meta_key; ?>" value="<?php echo esc_attr( $val ); ?>"
                    <?php echo $meta_key === 'billing_inn' ? 'class="inn-lookup"' : ''; ?>>
            </p>
        <?php endforeach; ?>
        <button type="button" id="inn-lookup-btn">Заполнить по ИНН</button>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('client_type');
        const jurFields = document.getElementById('jur-fields');
        const innField = document.getElementById('billing_inn');
        const lookupBtn = document.getElementById('inn-lookup-btn');
        
        select.addEventListener('change', function() {
            if (this.value === 'jur') {
                jurFields.style.display = '';
            } else {
                jurFields.style.display = 'none';
            }
        });

        // Подтягивание данных по ИНН
        lookupBtn.addEventListener('click', function() {
            const inn = innField.value.trim();
            if (!inn) {
                alert('Введите ИНН');
                return;
            }

            lookupBtn.disabled = true;
            lookupBtn.textContent = 'Загрузка...';

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=inn_lookup&inn=' + encodeURIComponent(inn)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const info = data.data;
                    if (info.full_name) document.getElementById('billing_full_name').value = info.full_name;
                    if (info.short_name) document.getElementById('billing_short_name').value = info.short_name;
                    if (info.legal_address) document.getElementById('billing_legal_address').value = info.legal_address;
                    if (info.kpp) document.getElementById('billing_kpp').value = info.kpp;
                    if (info.ogrn) document.getElementById('billing_ogrn').value = info.ogrn;
                    if (info.director) document.getElementById('billing_director').value = info.director;
                } else {
                    alert('Ошибка получения данных: ' + (data.data || 'Неизвестная ошибка'));
                }
            })
            .catch(error => {
                alert('Ошибка запроса: ' + error.message);
            })
            .finally(() => {
                lookupBtn.disabled = false;
                lookupBtn.textContent = 'Заполнить по ИНН';
            });
        });
    });
    </script>
    <?php
});

// Сохранение полей в ЛК
add_action( 'woocommerce_save_account_details', function( $user_id ) {
    if ( isset( $_POST['client_type'] ) ) {
        update_user_meta( $user_id, 'client_type', sanitize_text_field( $_POST['client_type'] ) );
    }
    $fields = [
        'billing_full_name', 'billing_short_name', 'billing_legal_address', 'billing_fact_address',
        'billing_inn', 'billing_kpp', 'billing_ogrn',
        'billing_director', 'billing_buh', 'billing_dover', 'billing_bank', 'billing_bik',
        'billing_korr', 'billing_rs'
    ];
    foreach ( $fields as $field ) {
        if ( isset( $_POST[$field] ) ) {
            update_user_meta( $user_id, $field, sanitize_text_field( $_POST[$field] ) );
        }
    }
});

// Меню ЛК
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    return [
        'dashboard'       => 'Панель управления',
        'orders'          => 'Заказы',
        'edit-account'    => 'Мои данные',
        'edit-address'    => 'Адрес доставки',
    ];
}, 20 );

// Панель управления — плитки
add_action( 'woocommerce_account_dashboard', function() {
    $orders_url = esc_url( wc_get_account_endpoint_url('orders') );
    $account_url = esc_url( wc_get_account_endpoint_url('edit-account') );
    $address_url = esc_url( wc_get_account_endpoint_url('edit-address') );
    ?>
    <br>
    <div class="lk-tiles">
        <a href="<?php echo $orders_url; ?>" class="lk-tile" aria-label="Заказы">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path d="M411.883 127.629h-310.08c-18.313 0-33.227 14.921-33.227 33.26v190.095c0 18.332 14.914 33.253 33.227 33.253h310.08c18.32 0 33.24-14.921 33.24-33.253V160.889c-.002-18.34-14.92-33.26-33.24-33.26zM311.34 293.18h-110.67v-27.57h110.67v27.57zm86.11-67.097H115.83v-24.64h281.62v24.64z"/></svg>
            <br>Заказы
        </a>
        <a href="<?php echo $account_url; ?>" class="lk-tile" aria-label="Мои данные">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 12c2.7 0 4.85-2.15 4.85-4.85S14.7 2.3 12 2.3 7.15 4.45 7.15 7.15 9.3 12 12 12zm0 2.7c-3.15 0-9.45 1.6-9.45 4.85v2.15h18.9v-2.15c0-3.25-6.3-4.85-9.45-4.85z"/></svg>
            <br>Мои данные
        </a>
        <a href="<?php echo $address_url; ?>" class="lk-tile" aria-label="Адрес доставки">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/></svg>
            <br>Адрес доставки
        </a>
    </div>
    <?php
});

// Страница заказов — мини-корзина + заказы
add_action( 'woocommerce_account_orders_endpoint', function() {
    echo do_shortcode('[woocommerce_cart]');
}, 5 );

// Поле телефона после фамилии в ЛК
add_action( 'woocommerce_edit_account_form', function() {
    $user_id = get_current_user_id();
    $phone = get_user_meta( $user_id, 'billing_phone', true );
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const lastNameField = document.querySelector('p.woocommerce-form-row.form-row-last');
        if (lastNameField) {
            const phoneField = document.createElement('p');
            phoneField.className = 'woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide';
            phoneField.innerHTML = `
                <label for="account_billing_phone"><?php echo esc_js( __( 'Телефон', 'woocommerce' ) ); ?></label>
                <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_billing_phone" id="account_billing_phone" value="<?php echo esc_js( $phone ); ?>" />
            `;
            lastNameField.after(phoneField);
        }
    });
    </script>
    <?php
});

// Валидация телефона
add_action( 'woocommerce_save_account_details_errors', function( $args, $user ) {
    if ( isset( $_POST['account_billing_phone'] ) ) {
        $phone = trim( $_POST['account_billing_phone'] );
        if ( $phone === '' ) {
            $args->add( 'error', __( 'Пожалуйста, укажите телефон.', 'woocommerce' ) );
        } elseif ( ! preg_match( '/^[\d\+\-\(\) ]+$/', $phone ) ) {
            $args->add( 'error', __( 'Телефон должен содержать только цифры, +, -, пробелы и скобки.', 'woocommerce' ) );
        }
    }
}, 10, 2 );

// Сохранение телефона
add_action( 'woocommerce_save_account_details', function( $user_id ) {
    if ( isset( $_POST['account_billing_phone'] ) ) {
        update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $_POST['account_billing_phone'] ) );
    }
});

// -----------------------
// Кастомизация регистрации
// -----------------------

// Добавление полей в форму регистрации
add_action( 'woocommerce_register_form_start', function() {
    ?>
    <p class="form-row form-row-wide">
        <label for="reg_client_type">Тип клиента <span class="required">*</span></label>
        <select name="client_type" id="reg_client_type" required>
            <option value="">Выберите тип клиента</option>
            <option value="fiz">Физическое лицо</option>
            <option value="jur">Юридическое лицо / ИП</option>
        </select>
    </p>

    <div id="reg-jur-fields" style="display:none;">
        <p class="form-row form-row-wide">
            <label for="reg_billing_inn">ИНН <span class="required">*</span></label>
            <input type="text" class="input-text" name="billing_inn" id="reg_billing_inn" />
        </p>
        <p class="form-row form-row-wide">
            <button type="button" id="reg-inn-lookup-btn">Заполнить по ИНН</button>
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_full_name">Полное наименование</label>
            <input type="text" class="input-text" name="billing_full_name" id="reg_billing_full_name" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_short_name">Краткое наименование</label>
            <input type="text" class="input-text" name="billing_short_name" id="reg_billing_short_name" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_legal_address">Юридический адрес</label>
            <input type="text" class="input-text" name="billing_legal_address" id="reg_billing_legal_address" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_kpp">КПП</label>
            <input type="text" class="input-text" name="billing_kpp" id="reg_billing_kpp" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_ogrn">ОГРН / ОГРНИП</label>
            <input type="text" class="input-text" name="billing_ogrn" id="reg_billing_ogrn" />
        </p>
        <p class="form-row form-row-wide">
            <label for="reg_billing_director">Должность и ФИО руководителя</label>
            <input type="text" class="input-text" name="billing_director" id="reg_billing_director" />
        </p>
    </div>
    <?php
});

// JavaScript для формы регистрации
add_action( 'woocommerce_register_form_end', function() {
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const regSelect = document.getElementById('reg_client_type');
        const regJurFields = document.getElementById('reg-jur-fields');
        const regInnField = document.getElementById('reg_billing_inn');
        const regLookupBtn = document.getElementById('reg-inn-lookup-btn');
        
        if (!regSelect) return;
        
        regSelect.addEventListener('change', function() {
            if (this.value === 'jur') {
                regJurFields.style.display = 'block';
            } else {
                regJurFields.style.display = 'none';
            }
        });

        if (regLookupBtn) {
            regLookupBtn.addEventListener('click', function() {
                const inn = regInnField.value.trim();
                if (!inn) {
                    alert('Введите ИНН');
                    return;
                }

                regLookupBtn.disabled = true;
                regLookupBtn.textContent = 'Загрузка...';

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=inn_lookup&inn=' + encodeURIComponent(inn)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        const info = data.data;
                        const fullNameField = document.getElementById('reg_billing_full_name');
                        const shortNameField = document.getElementById('reg_billing_short_name');
                        const legalAddressField = document.getElementById('reg_billing_legal_address');
                        const kppField = document.getElementById('reg_billing_kpp');
                        const ogrnField = document.getElementById('reg_billing_ogrn');
                        const directorField = document.getElementById('reg_billing_director');
                        
                        if (info.full_name && fullNameField) fullNameField.value = info.full_name;
                        if (info.short_name && shortNameField) shortNameField.value = info.short_name;
                        if (info.legal_address && legalAddressField) legalAddressField.value = info.legal_address;
                        if (info.kpp && kppField) kppField.value = info.kpp;
                        if (info.ogrn && ogrnField) ogrnField.value = info.ogrn;
                        if (info.director && directorField) directorField.value = info.director;
                        
                        alert('Данные успешно загружены');
                    } else {
                        alert('Ошибка получения данных: ' + (data.data || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Ошибка запроса: ' + error.message);
                })
                .finally(() => {
                    regLookupBtn.disabled = false;
                    regLookupBtn.textContent = 'Заполнить по ИНН';
                });
            });
        }
    });
    </script>
    <?php
});

// Валидация при регистрации
add_filter( 'woocommerce_registration_errors', function( $errors, $username, $email ) {
    if ( empty( $_POST['client_type'] ) ) {
        $errors->add( 'client_type_error', __( 'Пожалуйста, выберите тип клиента.', 'woocommerce' ) );
    }
    
    if ( isset( $_POST['client_type'] ) && $_POST['client_type'] === 'jur' ) {
        if ( empty( $_POST['billing_inn'] ) ) {
            $errors->add( 'billing_inn_error', __( 'Для юридических лиц обязательно указание ИНН.', 'woocommerce' ) );
        } else {
            $inn = sanitize_text_field( $_POST['billing_inn'] );
            if ( !preg_match('/^\d{10}$|^\d{12}$/', $inn) ) {
                $errors->add( 'billing_inn_format_error', __( 'ИНН должен содержать 10 или 12 цифр.', 'woocommerce' ) );
            }
        }
    }
    
    return $errors;
}, 10, 3 );

// Сохранение данных при регистрации
add_action( 'woocommerce_created_customer', function( $customer_id ) {
    if ( isset( $_POST['client_type'] ) ) {
        update_user_meta( $customer_id, 'client_type', sanitize_text_field( $_POST['client_type'] ) );
    }

    $fields = array(
        'billing_inn', 
        'billing_full_name', 
        'billing_short_name', 
        'billing_legal_address', 
        'billing_kpp', 
        'billing_ogrn', 
        'billing_director'
    );

    foreach ( $fields as $field ) {
        if ( isset( $_POST[$field] ) && !empty( $_POST[$field] ) ) {
            update_user_meta( $customer_id, $field, sanitize_text_field( $_POST[$field] ) );
        }
    }
    
    error_log( 'WooCommerce registration: Customer ' . $customer_id . ' created with client_type: ' . ($_POST['client_type'] ?? 'not set') );
}, 10, 1 );

// -----------------------
// Кастомизация checkout (оформления заказа)
// -----------------------

// Удаление стандартных полей billing
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
    //unset( $fields['billing']['billing_address_1'] );
    //unset( $fields['billing']['billing_address_2'] );
    //unset( $fields['billing']['billing_city'] );
    //unset( $fields['billing']['billing_postcode'] );
    unset( $fields['billing']['billing_country'] );
    //unset( $fields['billing']['billing_state'] );
    unset( $fields['billing']['billing_company'] );
    //unset( $fields['billing']['billing_phone'] );
    //unset( $fields['billing']['billing_email'] );

    return $fields;
});

// Добавление кастомных полей на checkout
add_action( 'woocommerce_after_checkout_billing_form', function( $checkout ) {
    $user_id = get_current_user_id();
    $client_type = '';
    
    if ( $user_id ) {
        $client_type = get_user_meta( $user_id, 'client_type', true );
    }
    
    ?>
    <div class="checkout-client-type">
        <h3>Тип плательщика</h3>
        
        <?php
        woocommerce_form_field( 'checkout_client_type', array(
            'type'          => 'select',
            'class'         => array('form-row-wide'),
            'label'         => __('Тип клиента'),
            'required'      => true,
            'options'       => array(
                ''     => 'Выберите тип клиента',
                'fiz'  => 'Физическое лицо',
                'jur'  => 'Юридическое лицо / ИП'
            )
        ), $checkout->get_value( 'checkout_client_type' ) ?: $client_type );
        ?>
        
        <div id="checkout-jur-fields" style="display:none;">
            <?php
            $jur_fields = array(
                'checkout_billing_inn' => array(
                    'label' => 'ИНН',
                    'required' => true,
                    'class' => array('form-row-wide inn-field')
                ),
                'checkout_billing_full_name' => array(
                    'label' => 'Полное наименование',
                    'class' => array('form-row-wide')
                ),
                'checkout_billing_short_name' => array(
                    'label' => 'Краткое наименование',
                    'class' => array('form-row-wide')
                ),
                'checkout_billing_legal_address' => array(
                    'label' => 'Юридический адрес',
                    'class' => array('form-row-wide')
                ),
                'checkout_billing_kpp' => array(
                    'label' => 'КПП',
                    'class' => array('form-row-first')
                ),
                'checkout_billing_ogrn' => array(
                    'label' => 'ОГРН / ОГРНИП',
                    'class' => array('form-row-last')
                ),
                'checkout_billing_director' => array(
                    'label' => 'Должность и ФИО руководителя',
                    'class' => array('form-row-wide')
                )
            );

            foreach ( $jur_fields as $key => $args ) {
                $value = '';
                if ( $user_id ) {
                    $meta_key = str_replace('checkout_', '', $key);
                    $value = get_user_meta( $user_id, $meta_key, true );
                }
                woocommerce_form_field( $key, $args, $checkout->get_value( $key ) ?: $value );
            }
            ?>
            <button type="button" id="checkout-inn-lookup-btn">Заполнить по ИНН</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('checkout_client_type');
        const jurFields = document.getElementById('checkout-jur-fields');
        const innField = document.getElementById('checkout_billing_inn');
        const lookupBtn = document.getElementById('checkout-inn-lookup-btn');
        
        function toggleFields() {
            if (select && select.value === 'jur') {
                jurFields.style.display = 'block';
            } else {
                jurFields.style.display = 'none';
            }
        }
        
        toggleFields();
        
        if (select) {
            select.addEventListener('change', toggleFields);
        }

        if (lookupBtn && innField) {
            lookupBtn.addEventListener('click', function() {
                const inn = innField.value.trim();
                if (!inn) {
                    alert('Введите ИНН');
                    return;
                }

                lookupBtn.disabled = true;
                lookupBtn.textContent = 'Загрузка...';

                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=inn_lookup&inn=' + encodeURIComponent(inn)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const info = data.data;
                        if (info.full_name) document.getElementById('checkout_billing_full_name').value = info.full_name;
                        if (info.short_name) document.getElementById('checkout_billing_short_name').value = info.short_name;
                        if (info.legal_address) document.getElementById('checkout_billing_legal_address').value = info.legal_address;
                        if (info.kpp) document.getElementById('checkout_billing_kpp').value = info.kpp;
                        if (info.ogrn) document.getElementById('checkout_billing_ogrn').value = info.ogrn;
                        if (info.director) document.getElementById('checkout_billing_director').value = info.director;
                    } else {
                        alert('Ошибка получения данных: ' + (data.data || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    alert('Ошибка запроса: ' + error.message);
                })
                .finally(() => {
                    lookupBtn.disabled = false;
                    lookupBtn.textContent = 'Заполнить по ИНН';
                });
            });
        }
    });
    </script>
    <?php
});

// Валидация полей checkout
add_action( 'woocommerce_checkout_process', function() {
    if ( empty( $_POST['checkout_client_type'] ) ) {
        wc_add_notice( __( 'Пожалуйста, выберите тип клиента.' ), 'error' );
    }
    
    if ( isset( $_POST['checkout_client_type'] ) && $_POST['checkout_client_type'] === 'jur' ) {
        if ( empty( $_POST['checkout_billing_inn'] ) ) {
            wc_add_notice( __( 'Для юридических лиц обязательно указание ИНН.' ), 'error' );
        }
    }
});

// Сохранение данных checkout в мета заказа и пользователя
add_action( 'woocommerce_checkout_update_order_meta', function( $order_id ) {
    $checkout_fields = array(
        'checkout_client_type' => 'client_type',
        'checkout_billing_inn' => 'billing_inn',
        'checkout_billing_full_name' => 'billing_full_name',
        'checkout_billing_short_name' => 'billing_short_name',
        'checkout_billing_legal_address' => 'billing_legal_address',
        'checkout_billing_kpp' => 'billing_kpp',
        'checkout_billing_ogrn' => 'billing_ogrn',
        'checkout_billing_director' => 'billing_director'
    );

    $user_id = get_current_user_id();
    
    foreach ( $checkout_fields as $checkout_field => $meta_key ) {
        if ( ! empty( $_POST[$checkout_field] ) ) {
            $value = sanitize_text_field( $_POST[$checkout_field] );
            
            // Сохранение в мета заказа
            update_post_meta( $order_id, '_' . $meta_key, $value );
            
            // Сохранение в профиль пользователя (если авторизован)
            if ( $user_id ) {
                update_user_meta( $user_id, $meta_key, $value );
            }
        }
    }
});

// Отображение данных в админке заказа
add_action( 'woocommerce_admin_order_data_after_billing_address', function( $order ) {
    $client_type = get_post_meta( $order->get_id(), '_client_type', true );
    
    if ( $client_type === 'jur' ) {
        echo '<h3>Реквизиты юридического лица</h3>';
        
        $jur_fields = array(
            '_billing_inn' => 'ИНН',
            '_billing_full_name' => 'Полное наименование',
            '_billing_short_name' => 'Краткое наименование',
            '_billing_legal_address' => 'Юридический адрес',
            '_billing_kpp' => 'КПП',
            '_billing_ogrn' => 'ОГРН / ОГРНИП',
            '_billing_director' => 'Руководитель'
        );
        
        foreach ( $jur_fields as $meta_key => $label ) {
            $value = get_post_meta( $order->get_id(), $meta_key, true );
            if ( $value ) {
                echo '<p><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</p>';
            }
        }
    }
});

// -----------------------
// AJAX обработчик для получения данных по ИНН
// -----------------------

add_action( 'wp_ajax_inn_lookup', 'handle_inn_lookup' );
add_action( 'wp_ajax_nopriv_inn_lookup', 'handle_inn_lookup' );

function handle_inn_lookup() {
    $inn = sanitize_text_field( $_POST['inn'] ?? '' );
    
    if ( empty( $inn ) ) {
        wp_send_json_error( 'ИНН не указан' );
    }
    
    // Используем предоставленные API ключи DaData
    $api_key = '903f6c9ee3c3fabd7b9ae599e3735b164f9f71d9';
    $secret_key = 'ea0595f2a66c84887976a56b8e57ec0aa329a9f7';
    
    // Реальный запрос к DaData
    $response = wp_remote_post( 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/findById/party', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Token ' . $api_key,
            'X-Secret' => $secret_key
        ),
        'body' => json_encode( array( 'query' => $inn ) ),
        'timeout' => 30
    ));
    
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Ошибка запроса к API: ' . $response->get_error_message() );
    }
    
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );
    
    if ( empty( $data['suggestions'] ) ) {
        wp_send_json_error( 'Данные по указанному ИНН не найдены' );
    }
    
    $suggestion = $data['suggestions'][0];
    $company_data = $suggestion['data'];
    
$result = array(
    'full_name'     => $company_data['name']['full_with_opf'] ?? '',
    'short_name'    => $company_data['name']['short_with_opf'] ?? '',
    // берём адрес из data['address'], fallback на unrestricted_value если нет
    'legal_address' => $company_data['address']['value'] 
                       ?? $company_data['address']['unrestricted_value'] 
                       ?? $suggestion['unrestricted_value'] 
                       ?? '',
    'kpp'           => $company_data['kpp'] ?? '',
    'ogrn'          => $company_data['ogrn'] ?? '',
    'director'      => ''
);
    
    // Получение данных о руководителе
    if ( !empty( $company_data['management'] ) && !empty( $company_data['management']['name'] ) ) {
        $management = $company_data['management'];
        $director_name = $management['name'];
        $director_post = $management['post'] ?? 'Руководитель';
        $result['director'] = $director_post . ' ' . $director_name;
    }
    
    wp_send_json_success( $result );
}

// -----------------------
// Настройки для API ключей (добавить в админку)
// -----------------------

add_action( 'admin_menu', function() {
    add_options_page(
        'Настройки ИНН API',
        'ИНН API',
        'manage_options',
        'inn-api-settings',
        'inn_api_settings_page'
    );
});

function inn_api_settings_page() {
    if ( isset( $_POST['submit'] ) ) {
        update_option( 'dadata_api_key', sanitize_text_field( $_POST['dadata_api_key'] ) );
        update_option( 'dadata_secret_key', sanitize_text_field( $_POST['dadata_secret_key'] ) );
        echo '<div class="notice notice-success"><p>Настройки сохранены!</p></div>';
    }
    
    $api_key = get_option( 'dadata_api_key', '903f6c9ee3c3fabd7b9ae599e3735b164f9f71d9' );
    $secret_key = get_option( 'dadata_secret_key', 'ea0595f2a66c84887976a56b8e57ec0aa329a9f7' );
    ?>
    <div class="wrap">
        <h1>Настройки ИНН API</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">API ключ DaData</th>
                    <td><input type="text" name="dadata_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">Secret ключ DaData</th>
                    <td><input type="text" name="dadata_secret_key" value="<?php echo esc_attr( $secret_key ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <p><strong>Примечание:</strong> Для работы автозаполнения по ИНН нужно получить API ключи на сайте <a href="https://dadata.ru/" target="_blank">DaData.ru</a></p>
    </div>
    <?php
}

// -----------------------
// Стили для форм
// -----------------------

add_action( 'wp_head', function() {
    ?>
    <style>
    .checkout-client-type {
        margin-bottom: 20px;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 5px;
        background: #f9f9f9;
    }
    
    .checkout-client-type h3 {
        margin-top: 0;
        margin-bottom: 15px;
    }
    
    #checkout-inn-lookup-btn,
    #inn-lookup-btn,
    #reg-inn-lookup-btn {
        margin-top: 10px;
        padding: 8px 15px;
        background: #0073aa;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    
    #checkout-inn-lookup-btn:hover,
    #inn-lookup-btn:hover,
    #reg-inn-lookup-btn:hover {
        background: #005177;
    }
    
    #checkout-inn-lookup-btn:disabled,
    #inn-lookup-btn:disabled,
    #reg-inn-lookup-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    
    .lk-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    
    .lk-tile {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 30px 20px;
        text-decoration: none;
        color: #333;
        background: #f8f8f8;
        border-radius: 8px;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    .lk-tile:hover {
        background: #e8e8e8;
        transform: translateY(-2px);
        text-decoration: none;
        color: #000;
    }
    
    .lk-tile svg {
        width: 48px;
        height: 48px;
        fill: currentColor;
        margin-bottom: 10px;
    }
    </style>
    <?php
});











// Функция для доваления За литр и проверки, находится ли товар в категории 81 или её дочерних категориях
function is_in_liter_categories($product_id) {
    $product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
    
    if (is_wp_error($product_categories) || empty($product_categories)) {
        return false;
    }
    
    $target_categories = range(81, 86);
    
    foreach ($product_categories as $cat_id) {
        // Проверяем, является ли текущая категория одной из целевых категорий
        if (in_array($cat_id, $target_categories)) {
            return true;
        }
        
        // Проверяем, является ли одна из целевых категорий предком текущей категории
        foreach ($target_categories as $target_cat_id) {
            if (cat_is_ancestor_of($target_cat_id, $cat_id)) {
                return true;
            }
        }
    }
    
    return false;
}

// Изменяем отображение цены для товаров из категории 81 и дочерних
add_filter('woocommerce_get_price_html', function($price, $product) {
    $product_id = $product->get_id();
    
    // Проверяем, находится ли товар в нужных категориях
    if (!is_in_liter_categories($product_id)) {
        return $price;
    }
    
    // Проверяем, не добавлено ли уже "за литр" к цене
    if (strpos($price, 'за литр') === false) {
        // Если цена содержит HTML теги (например, <span>), добавляем "за литр" внутрь
        if (preg_match('/(.*)<\/span>(.*)$/i', $price, $matches)) {
            $price = $matches[1] . '/литр</span>' . $matches[2];
        } else {
            // Если цена простая, просто добавляем в конец
            $price .= ' за литр';
        }
    }
    
    return $price;
}, 10, 2);






add_action('wp_footer', function() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function addFacetTitles() {
            const facetMap = {
                'poroda': 'Порода',
                'sort_': 'Сорт',
                'profil': 'Профиль', 
                'dlina': 'Длина',
                'shirina': 'Ширина',
                'tolshina': 'Толщина',
                'proizvoditel': 'Производитель',
                'krepej': 'Крепёж',
                'tip': 'Тип',
                'brend': 'Бренд'
            };

            // Находим все фильтры
            const facets = document.querySelectorAll('.facetwp-facet');
            
            facets.forEach(facet => {
                const facetName = facet.getAttribute('data-name');
                const titleText = facetMap[facetName];
                
                if (titleText) {
                    // Проверяем, есть ли уже заголовок
                    const prevElement = facet.previousElementSibling;
                    const hasTitle = prevElement && 
                                   prevElement.classList.contains('facet-title-added');
                    
                    // Проверяем, есть ли внутри элементы (фильтр не пустой)
                    const hasContent = facet.querySelector('.facetwp-checkbox') || 
                                     facet.querySelector('.facetwp-search') ||
                                     facet.querySelector('.facetwp-slider') ||
                                     facet.innerHTML.trim() !== '';
                    
                    if (!hasTitle && hasContent) {
                        // Создаем заголовок
                        const title = document.createElement('div');
                        title.className = 'facet-title-added';
                        title.innerHTML = `<h4 style="margin: 20px 0 10px 0; padding: 8px 0 5px 0; font-size: 16px; font-weight: 600; color: #333; border-bottom: 2px solid #8bc34a; text-transform: uppercase; letter-spacing: 0.5px;">${titleText}</h4>`;
                        
                        // Вставляем перед фильтром
                        facet.parentNode.insertBefore(title, facet);
                    }
                    
                    // Удаляем заголовок если фильтр стал пустым
                    if (hasTitle && !hasContent) {
                        const titleElement = facet.previousElementSibling;
                        if (titleElement && titleElement.classList.contains('facet-title-added')) {
                            titleElement.remove();
                        }
                    }
                }
            });
        }

        // Запускаем сразу
        addFacetTitles();

        // Запускаем с интервалом на случай динамической загрузки
        const interval = setInterval(addFacetTitles, 300);
        
        // Останавливаем через 10 секунд
        setTimeout(() => clearInterval(interval), 10000);

        // Также на события FacetWP
        if (typeof FWP !== 'undefined') {
            document.addEventListener('facetwp-loaded', addFacetTitles);
            document.addEventListener('facetwp-refresh', addFacetTitles);
        }

        // Mutation Observer для отслеживания изменений DOM
        const observer = new MutationObserver(addFacetTitles);
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    </script>
    <?php
});















// Своя тара для ЛКМ (по брендам)

// 1. Задаём доступные объёмы по брендам
function tara_by_brand() {
    return [
        'reiner'    => [1, 3, 5, 25],
        'renowood'  => [1, 3, 5, 9],
        'talatu'    => [1, 3, 5, 18],
        'tikkurila' => [1, 3, 5, 9, 18],
        'woodsol'   => [1, 3, 5, 18]
    ];
}

// Функция для получения бренда товара (универсальная)
function get_product_brand_for_tara($product_id) {
    // Список возможных таксономий для брендов
    $brand_taxonomies = [
        'product_brand',        // Официальный плагин WooCommerce Brands
        'yith_product_brand',   // YITH WooCommerce Brands
        'pa_brand',            // Атрибут товара "Бренд"
        'pa_brend',            // Атрибут товара "Бренд" (с опечаткой)
        'pwb-brand',           // Perfect WooCommerce Brands
        'brand'                // Другие плагины
    ];
    
    foreach ($brand_taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            $terms = wp_get_post_terms($product_id, $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                return strtolower($terms[0]->slug); // Возвращаем slug в нижнем регистре
            }
        }
    }
    
    // Проверяем мета-поля как запасной вариант
    $meta_keys = ['_brand', 'brand', '_product_brand'];
    foreach ($meta_keys as $key) {
        $brand = get_post_meta($product_id, $key, true);
        if (!empty($brand)) {
            return strtolower(sanitize_title($brand));
        }
    }
    
    return false;
}

// Диагностическая функция для определения используемой таксономии
function debug_brand_taxonomy($product_id) {
    echo "<!-- Диагностика брендов для товара #$product_id -->\n";
    
    $brand_taxonomies = [
        'product_brand',
        'yith_product_brand', 
        'pa_brand',
        'pa_brend',
        'pwb-brand',
        'brand'
    ];
    
    foreach ($brand_taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            $terms = wp_get_post_terms($product_id, $taxonomy);
            if (!empty($terms) && !is_wp_error($terms)) {
                echo "<!-- Найден бренд в '$taxonomy': {$terms[0]->name} (slug: {$terms[0]->slug}) -->\n";
            } else {
                echo "<!-- Таксономия '$taxonomy' существует, но брендов нет -->\n";
            }
        } else {
            echo "<!-- Таксономия '$taxonomy' не существует -->\n";
        }
    }
}

// 2. Вывод селекта на странице товара
add_action('woocommerce_before_add_to_cart_button', function() {
    global $product;
    if (!$product->is_type('simple')) return;
    
    $product_id = $product->get_id();
    
    // Включаем диагностику (уберите после настройки)
    if (current_user_can('manage_options')) {
        debug_brand_taxonomy($product_id);
    }
    
    // Получаем бренд товара
    $brand_slug = get_product_brand_for_tara($product_id);
    
    if (!$brand_slug) {
        echo "<!-- Бренд не найден для товара #$product_id -->\n";
        return;
    }
    
    echo "<!-- Найден бренд: $brand_slug -->\n";
    
    $map = tara_by_brand();
    
    // Проверяем, есть ли этот бренд в нашем списке объёмов
    if (!empty($map[$brand_slug])) {
        $base_price = wc_get_price_to_display($product);
        ?>
        <style>
            #brxe-gkyfue .cart {
                align-items: flex-end;
            }
            .tara-select {

            }
            .tara-select label {
                display: inline-block;
                margin-right: 10px;
                font-weight: bold;
                white-space: nowrap;
            }
        </style>
        <div class="tara-select">
            <label for="tara">Объем (л): </label>
            <div class="tinv-wraper" style="padding:2.5px; width:80px; display:inline-block;">
                <select id="tara" name="tara" data-base-price="<?php echo esc_attr($base_price); ?>">
                    <?php foreach ($map[$brand_slug] as $volume): ?>
                        <option value="<?php echo esc_attr($volume); ?>"><?php echo esc_html($volume); ?> л</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    } else {
        echo "<!-- Бренд '$brand_slug' не найден в списке доступных объёмов -->\n";
        echo "<!-- Доступные бренды: " . implode(', ', array_keys($map)) . " -->\n";
    }
});

// 3. Добавляем объём в корзину
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['tara'])) {
        $cart_item_data['tara'] = (float) $_POST['tara'];
    }
    return $cart_item_data;
}, 10, 3);

// 4. Показываем выбранный объём в корзине/заказе
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!empty($cart_item['tara'])) {
        $item_data[] = [
            'name'  => 'Объем',
            'value' => $cart_item['tara'] . ' л',
        ];
    }
    return $item_data;
}, 10, 2);

// 5. Пересчёт цены = цена * объем, скидка -10% при объеме >= 9
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item) {
        if (!empty($cart_item['tara'])) {
            $price_per_liter = (float) $cart_item['data']->get_price();
            $final_price = $price_per_liter * $cart_item['tara'];
            if ($cart_item['tara'] >= 9) {
                $final_price *= 0.9; // скидка 10%
            }
            $cart_item['data']->set_price($final_price);
        }
    }
});

// 6. JS для обновления цены на лету (со скидкой)
add_action('wp_footer', function() {
    if ( ! is_product() ) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let select = document.getElementById('tara');
        if (!select) return;

        let priceEl = document.querySelector('.woocommerce-Price-amount');
        let basePrice = parseFloat(select.dataset.basePrice);

        function updatePrice() {
            let multiplier = parseFloat(select.value) || 1;
            let newPrice = basePrice * multiplier;
            if (multiplier >= 9) {
                newPrice *= 0.9; // скидка 10%
            }
            if (priceEl) {
                priceEl.innerHTML = newPrice.toFixed(2).replace('.', ',') + ' ₽';
            }
        }

        select.addEventListener('change', updatePrice);
        updatePrice();
    });
    </script>
    <?php
});






//Замена в фильтре
function facetwp_custom_text_replacement() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Функция для замены текста
        function replaceFacetWPText() {
            // Ищем все элементы с классом facetwp-toggle
            const toggleElements = document.querySelectorAll('.facetwp-toggle');
            
            toggleElements.forEach(function(element) {
                // Регулярное выражение для поиска "Посмотреть X Подробнее"
                const regex = /Посмотреть\s+(\d+)\s+Подробнее/g;
                
                if (element.textContent && regex.test(element.textContent)) {
                    element.textContent = element.textContent.replace(regex, 'Развернуть (еще $1)');
                }
            });
            
            // Также проверяем другие возможные селекторы
            const otherElements = document.querySelectorAll('.facetwp-expand, .facetwp-collapse, [class*="facet"] a, [class*="facet"] span');
            
            otherElements.forEach(function(element) {
                const regex = /Посмотреть\s+(\d+)\s+Подробнее/g;
                
                if (element.textContent && regex.test(element.textContent)) {
                    element.textContent = element.textContent.replace(regex, 'Раскрыть $1');
                }
            });
        }
        
        // Запускаем замену при загрузке страницы
        replaceFacetWPText();
        
        // Запускаем замену после каждого обновления FacetWP
        document.addEventListener('facetwp-loaded', function() {
            setTimeout(replaceFacetWPText, 100);
        });
        
        // Дополнительно следим за изменениями DOM
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    replaceFacetWPText();
                }
            });
        });
        
        // Наблюдаем за контейнером с фильтрами
        const facetContainer = document.querySelector('.facetwp-template');
        if (facetContainer) {
            observer.observe(facetContainer, {
                childList: true,
                subtree: true
            });
        }
// === ADDED: height field listeners for running meter calculator ===
if (document.getElementById('rm_height1')) {
    document.getElementById('rm_height1').addEventListener('change', updateRunningMeterCalc);
}
if (document.getElementById('rm_height2')) {
    document.getElementById('rm_height2').addEventListener('change', updateRunningMeterCalc);
}
if (document.getElementById('rm_height')) {
    document.getElementById('rm_height').addEventListener('change', updateRunningMeterCalc);
}

// Delegated handler for dynamically created height fields
document.addEventListener('change', function(e) {
    if (!e || !e.target) return;
    if (e.target.id === 'rm_height' || e.target.id === 'rm_height1' || e.target.id === 'rm_height2') {
        try { console.log('Height changed:', e.target.id, '=', e.target.value); } catch(e) {}
        if (typeof updateRunningMeterCalc === 'function') updateRunningMeterCalc();
    }
});

    });
    </script>
    <?php
}
add_action('wp_footer', 'facetwp_custom_text_replacement');








/* ===== Mega Menu: атрибуты из JSON с подменой при наведении ===== */

add_action('wp_footer', function(){ ?>
<script>
jQuery(function($){
    let cache = null;

    // Загружаем JSON один раз
    $.getJSON('<?php echo home_url("/menu_attributes.json"); ?>', function(data){
        cache = data;

        // Рендерим для родительских категорий (если есть)
        $('.widget_layered_nav').each(function(){
            renderAttributes($(this));
        });
    });

    // Подмена при наведении на подкатегории
    $(document).on('mouseenter', '.mega-menu-item-type-taxonomy', function(){
        let href = $(this).find('a').attr('href');
        if (!href) return;

        // Достаём slug из ссылки категории
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
                let base = '<?php echo home_url("/product-category/"); ?>' + cat + '/';
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
<?php });












//------------------Доставка-----------------






add_action('wp_enqueue_scripts', function() {
    if (is_checkout() || is_cart()) {
        $api_key = '81c72bf5-a635-4fb5-8939-e6b31aa52ffe';
        wp_enqueue_script('yandex-maps', "https://api-maps.yandex.ru/2.1/?apikey={$api_key}&lang=ru_RU", [], null, true);
        wp_enqueue_script('delivery-calc', get_stylesheet_directory_uri() . '/js/delivery-calc.js', ['jquery','yandex-maps'], '1.3', true);

        $cart_weight = WC()->cart ? WC()->cart->get_cart_contents_weight() : 0;
        wp_localize_script('delivery-calc', 'deliveryVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'basePoint' => 'г. Санкт-Петербург, Выборгское шоссе 369к6',
            'rateLight' => 200, // руб/км для легких грузов (до 1500г)
            'rateHeavy' => 250, // руб/км для тяжелых грузов (свыше 1500г)
            'minLight' => 6000, // минимальная стоимость для легких грузов
            'minHeavy' => 7500, // минимальная стоимость для тяжелых грузов
            'minDistance' => 30, // минимальное расстояние для применения минималки (км)
            'cartWeight' => $cart_weight,
            'apiKey' => $api_key
        ]);
    }
});

// Ajax для сохранения стоимости доставки
add_action('wp_ajax_set_delivery_cost', 'set_delivery_cost');
add_action('wp_ajax_nopriv_set_delivery_cost', 'set_delivery_cost');
function set_delivery_cost() {
    if (isset($_POST['cost'])) {
        $cost = round(floatval($_POST['cost'])); // округляем до целых
        WC()->session->set('custom_delivery_cost', $cost);

        // сохраняем расстояние, если передано
        if (!empty($_POST['distance'])) {
            WC()->session->set('delivery_distance', floatval($_POST['distance']));
        }

        // лог для отладки
        error_log("Установлена стоимость доставки: {$cost} руб.");

        // очищаем уведомления
        if (function_exists('wc_clear_notices')) {
            wc_clear_notices();
        }

        // очищаем кэши WooCommerce
        wp_cache_flush();
        WC_Cache_Helper::get_transient_version('shipping', true);
        delete_transient('wc_shipping_method_count');

        // сбрасываем пользовательский кэш доставки
        $packages_hash = 'wc_ship_' . md5( 
            json_encode(WC()->cart->get_cart_for_session()) . 
            WC()->customer->get_shipping_country() . 
            WC()->customer->get_shipping_state() . 
            WC()->customer->get_shipping_postcode() . 
            WC()->customer->get_shipping_city()
        );
        wp_cache_delete($packages_hash, 'shipping_zones');

        // пересчет корзины
        if (WC()->cart) {
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
        }

        wp_send_json_success([
            'cost'    => $cost,
            'message' => 'Стоимость доставки обновлена'
        ]);
    } else {
        wp_send_json_error('Не указана стоимость');
    }
    wp_die();
}


// Ajax для очистки стоимости доставки
add_action('wp_ajax_clear_delivery_cost', 'clear_delivery_cost');
add_action('wp_ajax_nopriv_clear_delivery_cost', 'clear_delivery_cost');
function clear_delivery_cost() {
    WC()->session->__unset('custom_delivery_cost');
    WC()->session->__unset('delivery_distance');
    
    // Очищаем кэши
    WC_Cache_Helper::get_transient_version('shipping', true);
    delete_transient('wc_shipping_method_count');
    
    if (WC()->cart) {
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }
    
    wp_send_json_success(['message' => 'Стоимость доставки очищена']);
    wp_die();
}

// ВАЖНО: Создаем кастомный метод доставки для отображения стоимости
add_action('woocommerce_shipping_init', 'init_custom_delivery_method');
function init_custom_delivery_method() {
    if (!class_exists('WC_Custom_Delivery_Method')) {
        class WC_Custom_Delivery_Method extends WC_Shipping_Method {
            public function __construct($instance_id = 0) {
                $this->id = 'custom_delivery';
                $this->instance_id = absint($instance_id);
                $this->method_title = __('Доставка по карте');
                $this->method_description = __('Расчет доставки по карте');
                $this->supports = array(
                    'shipping-zones',
                    'instance-settings',
                );
                $this->enabled = 'yes';
                $this->title = 'Доставка по карте';
                $this->init();
            }

            public function init() {
                $this->init_form_fields();
                $this->init_settings();
                $this->enabled = $this->get_option('enabled');
                $this->title = $this->get_option('title');
                
                // Добавляем хуки для сохранения настроек
                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            }

            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Включить/Отключить'),
                        'type' => 'checkbox',
                        'description' => __('Включить этот метод доставки.'),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('Название'),
                        'type' => 'text',
                        'description' => __('Название метода доставки.'),
                        'default' => __('Доставка по карте'),
                        'desc_tip' => true,
                    )
                );
            }

            public function calculate_shipping($package = array()) {
                $delivery_cost = WC()->session->get('custom_delivery_cost');
                
                if ($delivery_cost && $delivery_cost > 0) {
                    $rate = array(
                        'id' => $this->id . ':' . $this->instance_id,
                        'label' => $this->title,
                        'cost' => $delivery_cost,
                        'calc_tax' => 'per_item'
                    );
                    
                    $this->add_rate($rate);
                }
            }
        }
    }
}

// Добавляем метод доставки в список доступных
add_filter('woocommerce_shipping_methods', 'add_custom_delivery_method');
function add_custom_delivery_method($methods) {
    $methods['custom_delivery'] = 'WC_Custom_Delivery_Method';
    return $methods;
}

// Принудительно обновляем методы доставки при изменении стоимости
add_action('woocommerce_checkout_update_order_review', 'force_shipping_update');
function force_shipping_update($post_data) {
    if (WC()->session->get('custom_delivery_cost')) {
        // Очищаем кэш методов доставки
        WC_Cache_Helper::get_transient_version('shipping', true);
        
        // Пересчитываем доставку
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }
}

// Выводим интерфейс на странице checkout
add_action('woocommerce_before_checkout_billing_form', function() {
    ?>
    <style>
    .woocommerce-delivery-calc {
        background: #f8f9fa;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }
    .woocommerce-delivery-calc h3 {
        margin: 0 0 15px 0;
        color: #495057;
        font-size: 18px;
    }
    #delivery-map {
        width: 100%;
        height: 400px;
        margin-bottom: 15px;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    #ymaps-address {
        width: 100%;
        padding: 12px;
        margin-bottom: 15px;
        box-sizing: border-box;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
    }
    #ymaps-address:focus {
        outline: none;
        border-color: #0066cc;
        box-shadow: 0 0 0 2px rgba(0,102,204,0.2);
    }
    
    /* Стили для автокомплита */
    .ymaps-suggest-container {
        position: absolute;
        background: white;
        border: 1px solid #ccc;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        width: 100%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-radius: 4px;
        margin-top: 1px;
    }
    
    .ymaps-suggest-item {
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s;
    }
    
    .ymaps-suggest-item:last-child {
        border-bottom: none;
    }
    
    .ymaps-suggest-item:hover,
    .ymaps-suggest-item.active {
        background-color: #f5f5f5;
    }
    
    .ymaps-suggest-item.active {
        background-color: #007bff !important;
        color: white !important;
    }
    
    @media(max-width:768px) {
        #delivery-map { height: 300px; }
        .woocommerce-delivery-calc { padding: 15px; margin-bottom: 15px; }
    }
    #delivery-result {
        font-weight: normal;
        margin-top: 10px;
    }
    .delivery-instructions {
        background: #e7f3ff;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        font-size: 13px;
        color: #0066cc;
    }
    </style>

    <div class="woocommerce-delivery-calc">
        <h3>📍 Расчет стоимости доставки</h3>
        <div class="delivery-instructions">
            💡 <strong>Как рассчитать доставку:</strong><br>
            1️⃣ Введите адрес в поле ниже и выберите из подсказок<br>
            2️⃣ Или просто кликните по нужной точке на карте<br>
            3️⃣ Стоимость рассчитается автоматически
        </div>
        <p>
            <label for="ymaps-address"><strong>🏠 Адрес доставки:</strong>
                <input type="text" id="ymaps-address" placeholder="Введите адрес доставки (например: Невский проспект, 1)">
            </label>
        </p>
        <div id="delivery-map"></div>
        <div id="delivery-result"></div>
    </div>
    <?php
});

// Добавляем информацию о доставке в заказ
add_action('woocommerce_checkout_update_order_meta', 'save_delivery_info_to_order');
function save_delivery_info_to_order($order_id) {
    $delivery_cost = WC()->session->get('custom_delivery_cost');
    $delivery_distance = WC()->session->get('delivery_distance');

    if ($delivery_cost) {
        update_post_meta($order_id, '_delivery_cost', $delivery_cost);
    }
    if ($delivery_distance) {
        update_post_meta($order_id, '_delivery_distance', $delivery_distance);
    }

    // Очищаем сессию после сохранения заказа
    WC()->session->__unset('custom_delivery_cost');
    WC()->session->__unset('delivery_distance');
}

// Отображаем информацию о доставке в админке заказов
add_action('woocommerce_admin_order_data_after_shipping_address', 'display_delivery_info_in_admin');
function display_delivery_info_in_admin($order) {
    $delivery_cost = get_post_meta($order->get_id(), '_delivery_cost', true);
    $delivery_distance = get_post_meta($order->get_id(), '_delivery_distance', true);

    if ($delivery_cost || $delivery_distance) {
        echo '<h3>Информация о доставке</h3>';
        if ($delivery_distance) {
            echo '<p><strong>Расстояние:</strong> ' . number_format($delivery_distance, 1) . ' км</p>';
        }
        if ($delivery_cost) {
            echo '<p><strong>Стоимость доставки:</strong> ' . number_format($delivery_cost, 0) . ' ₽</p>';
        }
    }
}

// Всегда показываем поля доставки
add_filter('woocommerce_checkout_show_ship_to_different_address', '__return_true');
add_filter('woocommerce_cart_needs_shipping_address', '__return_true');
add_filter('woocommerce_ship_to_different_address_checked', '__return_true');

// Убираем обязательность полей billing адреса
add_filter('woocommerce_billing_fields', 'remove_billing_required_fields');
function remove_billing_required_fields($fields) {
    foreach($fields as $key => &$field) {
        if ($key !== 'billing_email') {
            $field['required'] = false;
        }
    }
    return $fields;
}

// Скрипт для подстановки адреса из карты в поля checkout
add_action('wp_footer', function() {
    if (!is_checkout()) return;
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('=== ИНИЦИАЛИЗАЦИЯ WooCommerce ИНТЕГРАЦИИ ===');
        
        // Глобальная функция для подстановки адреса в поля WooCommerce
        window.updateWooCommerceAddress = function(address) {
            console.log('updateWooCommerceAddress вызвана с адресом:', address);
            
            // Ждем загрузки полей checkout
            setTimeout(function() {
                // Основные поля адреса доставки
                var $shippingAddress1 = $('input[name="shipping_address_1"]');
                var $shippingAddress2 = $('input[name="shipping_address_2"]');
                var $shippingCity = $('input[name="shipping_city"]');
                
                // Поля адреса плательщика
                var $billingAddress1 = $('input[name="billing_address_1"]');
                var $billingAddress2 = $('input[name="billing_address_2"]');  
                var $billingCity = $('input[name="billing_city"]');
                
                console.log('Найдено полей shipping_address_1:', $shippingAddress1.length);
                console.log('Найдено полей billing_address_1:', $billingAddress1.length);
                
                // Парсим адрес
                var parsedAddress = parseAddressForWooCommerce(address);
                
                // Заполняем поля доставки
                if ($shippingAddress1.length) {
                    $shippingAddress1.val(parsedAddress.address1);
                    $shippingAddress1.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ shipping_address_1 обновлен:', parsedAddress.address1);
                }
                
                if ($shippingAddress2.length && parsedAddress.address2) {
                    $shippingAddress2.val(parsedAddress.address2);
                    $shippingAddress2.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ shipping_address_2 обновлен:', parsedAddress.address2);
                }
                
                if ($shippingCity.length && parsedAddress.city) {
                    $shippingCity.val(parsedAddress.city);
                    $shippingCity.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ shipping_city обновлен:', parsedAddress.city);
                }
                
                // Заполняем поля плательщика
                if ($billingAddress1.length) {
                    $billingAddress1.val(parsedAddress.address1);
                    $billingAddress1.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ billing_address_1 обновлен:', parsedAddress.address1);
                }
                
                if ($billingAddress2.length && parsedAddress.address2) {
                    $billingAddress2.val(parsedAddress.address2);
                    $billingAddress2.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ billing_address_2 обновлен:', parsedAddress.address2);
                }
                
                if ($billingCity.length && parsedAddress.city) {
                    $billingCity.val(parsedAddress.city);
                    $billingCity.trigger('input').trigger('change').trigger('blur');
                    console.log('✓ billing_city обновлен:', parsedAddress.city);
                }
                
                // Принудительное обновление checkout
                setTimeout(function() {
                    $('body').trigger('update_checkout');
                    console.log('🔄 Checkout обновлен после заполнения адреса');
                }, 200);
                
            }, 100);
        };
        
        // Функция парсинга адреса для WooCommerce
        function parseAddressForWooCommerce(fullAddress) {
            var city = '';
            var address1 = fullAddress;
            var address2 = '';
            
            // Паттерны для выделения города
            var cityPatterns = [
                /^([^,]+(?:область|край|республика|округ))[,\s]+(.+)/i,
                /^(г\.\s*[^,]+)[,\s]+(.+)/i,
                /^([^,]+(?:город|посёлок|село|деревня))[,\s]+(.+)/i,
                /^(Москва|Санкт-Петербург|СПб|Московская область|Ленинградская область)[,\s]+(.+)/i
            ];
            
            for (var i = 0; i < cityPatterns.length; i++) {
                var match = fullAddress.match(cityPatterns[i]);
                if (match) {
                    city = match[1].trim();
                    address1 = match[2].trim();
                    break;
                }
            }
            
            // Паттерны для выделения квартиры/офиса
            var apartmentPatterns = [
                /^(.+),\s*(кв\.?\s*\d+|квартира\s*\d+|оф\.?\s*\d+|офис\s*\d+)$/i,
                /^(.+),\s*(\d+[А-Я]?)$/i
            ];
            
            for (var j = 0; j < apartmentPatterns.length; j++) {
                var match2 = address1.match(apartmentPatterns[j]);
                if (match2) {
                    address1 = match2[1].trim();
                    address2 = match2[2].trim();
                    break;
                }
            }
            
            console.log('Парсинг адреса:', {
                original: fullAddress,
                city: city,
                address1: address1,
                address2: address2
            });
            
            return {
                city: city,
                address1: address1,
                address2: address2
            };
        }

        // Принудительно показываем блок доставки при загрузке
        function ensureShippingFieldsVisible() {
            $('.woocommerce-shipping-fields, .shipping_address').show();
            $('[name^="shipping_"]').closest('.form-row').show();
            $('#ship-to-different-address-checkbox').prop('checked', true);
            console.log('✓ Поля доставки принудительно показаны');
        }
        
        // Показываем поля сразу и через интервалы
        ensureShippingFieldsVisible();
        setTimeout(ensureShippingFieldsVisible, 500);
        setTimeout(ensureShippingFieldsVisible, 1000);

        // Обновляем методы доставки при изменении checkout
        $(document).on('updated_checkout', function() {
            console.log('=== CHECKOUT ОБНОВЛЕН ===');
            
            // Убеждаемся что поля доставки видны
            ensureShippingFieldsVisible();
            
            // Проверяем методы доставки
            var deliveryMethods = $('#shipping_method li label, .woocommerce-shipping-methods li label');
            console.log('Найдено методов доставки:', deliveryMethods.length);
            
            deliveryMethods.each(function(index) {
                console.log('Метод доставки ' + (index + 1) + ':', $(this).text().trim());
            });
            
            // Автоматически выбираем метод доставки по карте, если он есть и рассчитана стоимость
            var customDeliveryRadio = $('input[value*="custom_delivery"]');
            if (customDeliveryRadio.length && !$('input[name="shipping_method[0]"]:checked').length) {
                customDeliveryRadio.prop('checked', true).trigger('change');
                console.log('✓ Автоматически выбран метод доставки по карте');
            }
        });
        
        // Слушаем изменения в полях адреса для дополнительного обновления
        $(document).on('change input blur', 'input[name^="shipping_"], input[name^="billing_"]', function() {
            var fieldName = $(this).attr('name');
            var fieldValue = $(this).val();
            console.log('Поле изменено:', fieldName, '=', fieldValue);
        });
    });
    </script>
    <?php
});



add_filter('woocommerce_checkout_show_ship_to_different_address', '__return_false');
add_filter('woocommerce_cart_needs_shipping_address', '__return_false');

// Убираем надпись "(необязательно)" у всех полей checkout
add_filter('woocommerce_form_field', function($field, $key, $args, $value) {
    if (strpos($field, '(необязательно)') !== false) {
        $field = str_replace('(необязательно)', '', $field);
    }
    return $field;
}, 10, 4);








add_filter('woocommerce_account_menu_items', function($items) {
    unset($items['cart']); // для меню аккаунта
    return $items;
}, 999);

add_filter('wp_nav_menu_items', function($items, $args) {
    // убираем "Cart" из всех меню
    $items = preg_replace('/<li[^>]*><a[^>]*href="[^"]*cart[^"]*"[^>]*>.*?<\/a><\/li>/i', '', $items);
    return $items;
}, 10, 2);



// Добавляем мета-поля для форм фальшбалок (категория 266)
add_action('woocommerce_product_options_general_product_data', 'add_falsebalk_shapes_fields');
function add_falsebalk_shapes_fields() {
    global $post;
    
    // Проверяем, относится ли товар к категории 266
    if (!has_term(266, 'product_cat', $post->ID)) {
        return;
    }
    
    echo '<div class="options_group falsebalk_shapes_group">';
    echo '<h3 style="padding-left: 12px; color: #3aa655; border-bottom: 2px solid #3aa655; padding-bottom: 10px; margin-bottom: 15px;">⚙️ Настройки размеров фальшбалок</h3>';

    // Получаем сохраненные данные
    $shapes_data = get_post_meta($post->ID, '_falsebalk_shapes_data', true);
    if (!is_array($shapes_data)) {
        $shapes_data = [];
    }
    
    $shapes = [
        'g' => ['label' => 'Г-образная', 'icon' => '⌐'],
        'p' => ['label' => 'П-образная', 'icon' => '⊓'],
        'o' => ['label' => 'О-образная', 'icon' => '▢']
    ];
    
    foreach ($shapes as $shape_key => $shape_info) {
        $shape_label = $shape_info['label'];
        $shape_icon = $shape_info['icon'];
        
        echo '<div style="padding: 15px; margin: 12px; border: 2px solid #e0e0e0; border-radius: 8px; background: #f9f9f9;">';
        echo '<h4 style="margin-top: 0; color: #333; font-size: 15px;">' . $shape_icon . ' ' . $shape_label . '</h4>';
        
        $current_data = isset($shapes_data[$shape_key]) ? $shapes_data[$shape_key] : [];
        
        // Checkbox для включения/отключения формы
        $enabled = isset($current_data['enabled']) ? $current_data['enabled'] : false;
        
        woocommerce_wp_checkbox([
            'id' => '_shape_' . $shape_key . '_enabled',
            'label' => 'Активировать эту форму',
            'description' => 'Отметьте, чтобы форма отображалась в калькуляторе',
            'value' => $enabled ? 'yes' : 'no',
        ]);
        
        echo '<div class="shape-params-' . $shape_key . '" style="' . (!$enabled ? 'opacity: 0.5; pointer-events: none;' : '') . '">';
        
        // === ШИРИНА ===
        echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #2196F3;">';
        echo '<h5 style="margin: 0 0 10px 0; color: #555;">Ширина (мм)</h5>';
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_width_min',
            'label' => 'Минимум',
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1'],
            'value' => isset($current_data['width_min']) ? $current_data['width_min'] : '',
            'placeholder' => '100',
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_width_max',
            'label' => 'Максимум',
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1'],
            'value' => isset($current_data['width_max']) ? $current_data['width_max'] : '',
            'placeholder' => '300',
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_width_step',
            'label' => 'Шаг',
            'type' => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '1'],
            'value' => isset($current_data['width_step']) ? $current_data['width_step'] : '50',
            'placeholder' => '50',
        ]);
        
        echo '</div></div>';
        
        // === ВЫСОТА (для П-образной - две высоты) ===
        if ($shape_key === 'p') {
            // Высота 1
            echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #4CAF50;">';
            echo '<h5 style="margin: 0 0 10px 0; color: #555;">Высота 1 (мм) - левая сторона</h5>';
            echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height1_min',
                'label' => 'Минимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height1_min']) ? $current_data['height1_min'] : '',
                'placeholder' => '100',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height1_max',
                'label' => 'Максимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height1_max']) ? $current_data['height1_max'] : '',
                'placeholder' => '300',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height1_step',
                'label' => 'Шаг',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height1_step']) ? $current_data['height1_step'] : '50',
                'placeholder' => '50',
            ]);
            
            echo '</div></div>';
            
            // Высота 2
            echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #8BC34A;">';
            echo '<h5 style="margin: 0 0 10px 0; color: #555;">Высота 2 (мм) - правая сторона</h5>';
            echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height2_min',
                'label' => 'Минимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height2_min']) ? $current_data['height2_min'] : '',
                'placeholder' => '100',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height2_max',
                'label' => 'Максимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height2_max']) ? $current_data['height2_max'] : '',
                'placeholder' => '300',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height2_step',
                'label' => 'Шаг',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height2_step']) ? $current_data['height2_step'] : '50',
                'placeholder' => '50',
            ]);
            
            echo '</div></div>';
        } else {
            // Обычная высота для Г и О форм
            echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #4CAF50;">';
            echo '<h5 style="margin: 0 0 10px 0; color: #555;">Высота (мм)</h5>';
            echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height_min',
                'label' => 'Минимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height_min']) ? $current_data['height_min'] : '',
                'placeholder' => '100',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height_max',
                'label' => 'Максимум',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height_max']) ? $current_data['height_max'] : '',
                'placeholder' => '300',
            ]);
            
            woocommerce_wp_text_input([
                'id' => '_shape_' . $shape_key . '_height_step',
                'label' => 'Шаг',
                'type' => 'number',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'value' => isset($current_data['height_step']) ? $current_data['height_step'] : '50',
                'placeholder' => '50',
            ]);
            
            echo '</div></div>';
        }
        
        // === ДЛИНА ===
        echo '<div style="background: #fff; padding: 12px; margin: 10px 0; border-radius: 5px; border-left: 3px solid #FF9800;">';
        echo '<h5 style="margin: 0 0 10px 0; color: #555;">Длина (м)</h5>';
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">';
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_length_min',
            'label' => 'Минимум',
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0.1'],
            'value' => isset($current_data['length_min']) ? $current_data['length_min'] : '',
            'placeholder' => '1.0',
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_length_max',
            'label' => 'Максимум',
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0.1'],
            'value' => isset($current_data['length_max']) ? $current_data['length_max'] : '',
            'placeholder' => '6.0',
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_shape_' . $shape_key . '_length_step',
            'label' => 'Шаг',
            'type' => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0.01'],
            'value' => isset($current_data['length_step']) ? $current_data['length_step'] : '0.5',
            'placeholder' => '0.5',
        ]);
        
        echo '</div></div>';
        
        echo '</div>'; // .shape-params
        echo '</div>'; // блок формы
    }
    
    echo '</div>';
    
    // JavaScript для включения/отключения полей
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Обработка чекбоксов включения форм
        $('input[id^="_shape_"][id$="_enabled"]').on('change', function() {
            var shapeKey = $(this).attr('id').replace('_shape_', '').replace('_enabled', '');
            var paramsBlock = $('.shape-params-' + shapeKey);
            
            if ($(this).is(':checked')) {
                paramsBlock.css({
                    'opacity': '1',
                    'pointer-events': 'auto'
                });
            } else {
                paramsBlock.css({
                    'opacity': '0.5',
                    'pointer-events': 'none'
                });
            }
        });
        
        // Валидация: макс должен быть больше мин
        $('input[id*="_min"], input[id*="_max"]').on('blur', function() {
            var fieldId = $(this).attr('id');
            var isMin = fieldId.includes('_min');
            var baseId = fieldId.replace('_min', '').replace('_max', '');
            
            var minField = $('#' + baseId + '_min');
            var maxField = $('#' + baseId + '_max');
            
            var minVal = parseFloat(minField.val());
            var maxVal = parseFloat(maxField.val());
            
            if (minVal && maxVal && minVal >= maxVal) {
                alert('⚠️ Максимальное значение должно быть больше минимального!');
                if (isMin) {
                    minField.css('border-color', 'red');
                } else {
                    maxField.css('border-color', 'red');
                }
            } else {
                minField.css('border-color', '');
                maxField.css('border-color', '');
            }
        });
    });
    </script>
    <style>
    .falsebalk_shapes_group .form-field {
        padding: 8px 0 !important;
    }
    .falsebalk_shapes_group input[type="number"] {
        max-width: 100px;
    }
    </style>
    <?php
}

// Сохраняем мета-поля - ОБНОВЛЕННАЯ ВЕРСИЯ
add_action('woocommerce_process_product_meta', 'save_falsebalk_shapes_fields');
function save_falsebalk_shapes_fields($post_id) {
    if (!has_term(266, 'product_cat', $post_id)) {
        return;
    }
    
    $shapes_data = [];
    $shapes = ['g', 'p', 'o'];
    
    foreach ($shapes as $shape_key) {
        // Проверяем, активирована ли форма
        $enabled = isset($_POST['_shape_' . $shape_key . '_enabled']) && $_POST['_shape_' . $shape_key . '_enabled'] === 'yes';
        
        if (!$enabled) {
            continue;
        }
        
        // Общие параметры
        $width_min = isset($_POST['_shape_' . $shape_key . '_width_min']) ? floatval($_POST['_shape_' . $shape_key . '_width_min']) : 0;
        $width_max = isset($_POST['_shape_' . $shape_key . '_width_max']) ? floatval($_POST['_shape_' . $shape_key . '_width_max']) : 0;
        $width_step = isset($_POST['_shape_' . $shape_key . '_width_step']) ? floatval($_POST['_shape_' . $shape_key . '_width_step']) : 50;
        
        $length_min = isset($_POST['_shape_' . $shape_key . '_length_min']) ? floatval($_POST['_shape_' . $shape_key . '_length_min']) : 0;
        $length_max = isset($_POST['_shape_' . $shape_key . '_length_max']) ? floatval($_POST['_shape_' . $shape_key . '_length_max']) : 0;
        $length_step = isset($_POST['_shape_' . $shape_key . '_length_step']) ? floatval($_POST['_shape_' . $shape_key . '_length_step']) : 0.5;
        
        // Параметры высоты зависят от формы
        $shape_data = [
            'enabled' => true,
            'width_min' => $width_min,
            'width_max' => $width_max,
            'width_step' => $width_step > 0 ? $width_step : 50,
            'length_min' => $length_min,
            'length_max' => $length_max,
            'length_step' => $length_step > 0 ? $length_step : 0.5,
        ];
        
        if ($shape_key === 'p') {
            // Для П-образной - две высоты
            $shape_data['height1_min'] = isset($_POST['_shape_' . $shape_key . '_height1_min']) ? floatval($_POST['_shape_' . $shape_key . '_height1_min']) : 0;
            $shape_data['height1_max'] = isset($_POST['_shape_' . $shape_key . '_height1_max']) ? floatval($_POST['_shape_' . $shape_key . '_height1_max']) : 0;
            $shape_data['height1_step'] = isset($_POST['_shape_' . $shape_key . '_height1_step']) ? floatval($_POST['_shape_' . $shape_key . '_height1_step']) : 50;
            
            $shape_data['height2_min'] = isset($_POST['_shape_' . $shape_key . '_height2_min']) ? floatval($_POST['_shape_' . $shape_key . '_height2_min']) : 0;
            $shape_data['height2_max'] = isset($_POST['_shape_' . $shape_key . '_height2_max']) ? floatval($_POST['_shape_' . $shape_key . '_height2_max']) : 0;
            $shape_data['height2_step'] = isset($_POST['_shape_' . $shape_key . '_height2_step']) ? floatval($_POST['_shape_' . $shape_key . '_height2_step']) : 50;
        } else {
            // Для Г и О - одна высота
            $shape_data['height_min'] = isset($_POST['_shape_' . $shape_key . '_height_min']) ? floatval($_POST['_shape_' . $shape_key . '_height_min']) : 0;
            $shape_data['height_max'] = isset($_POST['_shape_' . $shape_key . '_height_max']) ? floatval($_POST['_shape_' . $shape_key . '_height_max']) : 0;
            $shape_data['height_step'] = isset($_POST['_shape_' . $shape_key . '_height_step']) ? floatval($_POST['_shape_' . $shape_key . '_height_step']) : 50;
        }
        
        // Сохраняем только если хотя бы один параметр заполнен
        if ($width_min > 0 || $length_min > 0) {
            $shapes_data[$shape_key] = $shape_data;
        }
    }
    
    if (!empty($shapes_data)) {
        update_post_meta($post_id, '_falsebalk_shapes_data', $shapes_data);
    } else {
        delete_post_meta($post_id, '_falsebalk_shapes_data');
    }
}


// === ADDED: remove price from painting scheme name in cart and orders ===
add_filter('woocommerce_get_item_data', 'remove_price_from_painting_scheme_name', 15, 2);
function remove_price_from_painting_scheme_name($item_data, $cart_item) {
    foreach ($item_data as &$data) {
        if ($data['name'] === 'Схема покраски' || $data['name'] === 'Услуга покраски') {
            $data['value'] = preg_replace('/\s*[\(\+]?\s*\d+[\s\.,]?\d*\s*₽\s*\/\s*м[²2]\s*\)?/u', '', $data['value']);
            $data['value'] = trim($data['value']);
        }
    }
    return $item_data;
}

add_filter('woocommerce_order_item_display_meta_value', 'remove_price_from_order_painting_scheme', 10, 3);
function remove_price_from_order_painting_scheme($display_value, $meta, $item) {
    if ($meta->key === 'Схема покраски' || $meta->key === 'Услуга покраски') {
        $display_value = preg_replace('/\s*[\(\+]?\s*\d+[\s\.,]?\d*\s*₽\s*\/\s*м[²2]\s*\)?/u', '', $display_value);
        $display_value = trim($display_value);
    }
    return $display_value;
}
// === END ADDED ===



// === ADDED: Adjust price HTML for running meter / carpentry products to show 'за м²' ===
add_filter('woocommerce_get_price_html', 'pm_adjust_running_meter_price_html', 20, 2);
function pm_adjust_running_meter_price_html($price_html, $product) {
    if (!is_object($product)) return $price_html;
    $product_id = $product->get_id();
    if (!function_exists('is_running_meter_category')) {
        // cannot determine category, return original
        return $price_html;
    }
    $is_running_meter = is_running_meter_category($product_id);
    // try to detect falsebalk
    $is_falsebalk = false;
    if (function_exists('product_in_category')) {
        $is_falsebalk = product_in_category($product_id, 266);
    }
    $show_falsebalk_calc = false;
    if ($is_falsebalk) {
        $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
        if (is_array($shapes_data)) {
            foreach ($shapes_data as $shape_info) {
                if (!empty($shape_info['enabled'])) {
                    $show_falsebalk_calc = true;
                    break;
                }
            }
        }
    }
if ($is_running_meter) {
    $base_price_per_m = floatval($product->get_regular_price() ?: $product->get_price());
    if ($base_price_per_m) {
        $min_width = 0;
        $min_length = 0;
        $multiplier = 1;

        // --- Для фальшбалок ---
        if ($is_falsebalk) {
            $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
            $min_variant = null;

            if (is_array($shapes_data)) {
                foreach ($shapes_data as $shape_key => $shape) {
                    if (empty($shape['enabled'])) continue;

                    $width = floatval($shape['width_min'] ?: 100);
                    $length = floatval($shape['length_min'] ?: 1);

                    // Высота
                    if (isset($shape['height_min'])) {
                        $height = floatval($shape['height_min']);
                    } elseif (isset($shape['height1_min'], $shape['height2_min'])) {
                        $height = floatval($shape['height1_min'] + $shape['height2_min']);
                    } else {
                        $height = $width;
                    }

                    // Находим минимальную площадь
                    $area = $width * $height;
                    if ($min_variant === null || $area < $min_variant['area']) {
                        $min_variant = [
                            'width' => $width,
                            'height' => $height,
                            'length' => $length,
                            'section_form' => $shape_key,
                            'area' => $area
                        ];
                    }
                }
            }

            // Если не найден вариант, fallback
            if ($min_variant) {
                $form_multipliers = ['g'=>2, 'p'=>3, 'o'=>4];
                $multiplier = $form_multipliers[$min_variant['section_form']] ?? 1;

                $min_width = $min_variant['width'];
                $min_length = $min_variant['length'];
            } else {
                $min_width = 70;
                $min_length = 0.2;
                $multiplier = 2;
            }
        } else {
            // Для остальных столярных изделий
            $min_width = floatval(get_post_meta($product_id, '_calc_width_min', true)) ?: 100;
            $min_length = floatval(get_post_meta($product_id, '_calc_length_min', true)) ?: 1;
            $multiplier = function_exists('get_price_multiplier') ? get_price_multiplier($product_id) : 1;
        }

        $min_length = round($min_length, 2);
        $min_area = ($min_width / 1000) * $min_length * $multiplier;
        $min_price = $base_price_per_m * $min_area;

        // Вывод цены
        $should_hide_base_price = true;
        if (is_product()) {
            return '<span style="font-size:1.1em;">' . wc_price($min_price) . ' за шт.</span>';
        } else {
            return '<span style="font-size:0.85em;">' . wc_price($min_price) . ' шт.</span>';
        }
    }
}

    return $price_html;
}
// === END ADDED ===
// 1. Добавление полей фаски при редактировании категории в админке
add_action('product_cat_edit_form_fields', 'add_category_faska_fields', 10, 2);
function add_category_faska_fields($term) {
    $term_id = $term->term_id;
    $faska_types = get_term_meta($term_id, 'faska_types', true);
    if (!$faska_types) {
        $faska_types = array();
    }
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label>Типы фасок</label></th>
        <td>
            <div id="faska_types_container">
                <?php for ($i = 1; $i <= 8; $i++): 
                    $faska = isset($faska_types[$i-1]) ? $faska_types[$i-1] : array('name' => '', 'image' => '');
                ?>
                <div style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd;">
                    <h4>Фаска <?php echo $i; ?></h4>
                    <p>
                        <label>Название: 
                            <input type="text" name="faska_types[<?php echo $i-1; ?>][name]" value="<?php echo esc_attr($faska['name']); ?>" style="width: 300px;" />
                        </label>
                    </p>
                    <p>
                        <label>URL изображения: 
                            <input type="text" name="faska_types[<?php echo $i-1; ?>][image]" value="<?php echo esc_url($faska['image']); ?>" style="width: 400px;" />
                            <button type="button" class="button upload_faska_image" data-index="<?php echo $i-1; ?>">Загрузить</button>
                        </label>
                    </p>
                    <?php if ($faska['image']): ?>
                    <p><img src="<?php echo esc_url($faska['image']); ?>" style="max-width: 100px; height: auto;" /></p>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
            <p class="description">Настройте до 8 типов фасок для этой категории</p>
            
            <script>
            jQuery(document).ready(function($) {
                $('.upload_faska_image').click(function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var index = button.data('index');
                    var custom_uploader = wp.media({
                        title: 'Выберите изображение фаски',
                        button: { text: 'Использовать это изображение' },
                        multiple: false
                    }).on('select', function() {
                        var attachment = custom_uploader.state().get('selection').first().toJSON();
                        button.prev('input').val(attachment.url);
                        // Добавляем превью
                        var imgContainer = button.closest('div').find('img');
                        if (imgContainer.length) {
                            imgContainer.attr('src', attachment.url);
                        } else {
                            button.closest('div').append('<p><img src="' + attachment.url + '" style="max-width: 100px; height: auto;" /></p>');
                        }
                    }).open();
                });
            });
            </script>
        </td>
    </tr>
    <?php
}

// 2. Сохранение полей фаски при сохранении категории
add_action('edited_product_cat', 'save_category_faska_fields', 10, 2);
function save_category_faska_fields($term_id) {
    if (isset($_POST['faska_types'])) {
        $faska_types = array();
        foreach ($_POST['faska_types'] as $faska) {
            if (!empty($faska['name']) || !empty($faska['image'])) {
                $faska_types[] = array(
                    'name' => sanitize_text_field($faska['name']),
                    'image' => esc_url_raw($faska['image'])
                );
            }
        }
        update_term_meta($term_id, 'faska_types', $faska_types);
    }
}

// 3. Сохранение выбранной фаски в данные корзины
add_filter('woocommerce_add_cart_item_data', 'add_faska_to_cart', 10, 3);
function add_faska_to_cart($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['selected_faska_type'])) {
        $cart_item_data['selected_faska'] = sanitize_text_field($_POST['selected_faska_type']);
    }
    return $cart_item_data;
}

// 4. Отображение фаски в корзине
add_filter('woocommerce_get_item_data', 'display_faska_in_cart', 10, 2);
function display_faska_in_cart($item_data, $cart_item) {
    if (isset($cart_item['selected_faska'])) {
        $item_data[] = array(
            'key' => 'Тип фаски',
            'value' => $cart_item['selected_faska']
        );
    }
    return $item_data;
}

// 5. Сохранение фаски в метаданные заказа
add_action('woocommerce_checkout_create_order_line_item', 'add_faska_to_order_items', 10, 4);
function add_faska_to_order_items($item, $cart_item_key, $values, $order) {
    if (isset($values['selected_faska'])) {
        $item->add_meta_data('Тип фаски', $values['selected_faska']);
    }
}

// 6. Правильное отображение названия в админке заказа
add_filter('woocommerce_order_item_display_meta_key', 'filter_order_item_displayed_meta_key', 10, 3);
function filter_order_item_displayed_meta_key($display_key, $meta, $item) {
    if ($meta->key === 'Тип фаски') {
        $display_key = 'Тип фаски';
    }
    return $display_key;
}

// 7. Отображение фаски в письмах о заказе
add_filter('woocommerce_order_item_display_meta_value', 'filter_order_item_displayed_meta_value', 10, 3);
function filter_order_item_displayed_meta_value($display_value, $meta, $item) {
    if ($meta->key === 'Тип фаски') {
        $display_value = '<strong>' . $meta->value . '</strong>';
    }
    return $display_value;
}
