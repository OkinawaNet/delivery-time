<?php
/***************************************************************************
 *                                                                          *
 *   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 *                                                                          *
 ****************************************************************************
 * PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
 * "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

use Tygh\Registry;
use Tygh\Addons\ProductVariations\Product\Manager as ProductManager;
use Tygh\Addons\ProductVariations\Product\AdditionalDataLoader;
use Tygh\Enum\ProductTracking;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

/**
 * Returns a value indicating whether the give value is "empty".
 *
 * The value is considered "empty", if one of the following conditions is satisfied:
 *
 * - it is `null`,
 * - an empty string (`''`),
 * - a string containing only whitespace characters,
 * - or an empty array.
 *
 * @param mixed $value
 *
 * @return bool if the value is empty
 */
function fn_product_variations_value_is_empty($value)
{
    return $value === '' || $value === array() || $value === null || is_string($value) && trim($value) === '';
}

/**
 * Combines the features of a parent product and the features of the selected product variation.
 *
 * @param array     $product    Product data
 * @param string    $display_on Filter by display on field
 *
 * @return array
 */
function fn_product_variations_merge_features($product, $display_on = 'C')
{
    if (empty($product['variation_product_id'])) {
        return $product;
    }

    $product_variation = array(
        'product_id' => $product['variation_product_id'],
        'category_ids' => $product['category_ids']
    );

    // if $product from fn_get_product_data
    if (isset($product['detailed_params']['info_type']) && $product['detailed_params']['info_type'] === 'D') {
        $params = array(
            'category_ids' => fn_get_category_ids_with_parent($product['category_ids']),
            'product_id' => $product['variation_product_id'],
            'product_company_id' => !empty($product['company_id']) ? $product['company_id'] : 0,
            'statuses' => AREA == 'C' ? array('A') : array('A', 'H'),
            'variants' => true,
            'plain' => false,
            'display_on' => AREA == 'A' ? '' : 'product',
            'existent_only' => (AREA != 'A'),
            'variants_selected_only' => true
        );

        list($product_features) = fn_get_product_features($params, 0, CART_LANGUAGE);

        if (!empty($product['product_features'])) {
            foreach ($product['product_features'] as $feature_id => &$item) {
                if (!isset($product_features[$feature_id])) {
                    continue;
                }

                if (isset($item['subfeatures'])) {
                    $item['subfeatures'] = array_replace($item['subfeatures'], $product_features[$feature_id]['subfeatures']);
                } else {
                    $item = array_replace($item, $product_features[$feature_id]);
                }
            }
            unset($item);
        } else {
            $product['product_features'] = $product_features;
        }

        if (isset($product['header_features'])) {
            $header_features = fn_get_product_features_list($product_variation, 'H');

            $product['header_features'] = fn_array_elements_to_keys($product['header_features'], 'feature_id');
            $header_features = fn_array_elements_to_keys($header_features, 'feature_id');
            $product['header_features'] = array_replace($product['header_features'], $header_features);

            $product['header_features'] = array_values($product['header_features']);
        }
    } else {
        $product_features = fn_get_product_features_list($product_variation, $display_on);
        $product_features = fn_array_elements_to_keys($product_features, 'feature_id');
        $product['product_features'] = fn_array_elements_to_keys($product['product_features'], 'feature_id');

        $product['product_features'] = array_replace($product['product_features'], $product_features);
        $product['product_features'] = array_values($product['product_features']);
    }

    return $product;
}

/**
 * Hook handler: after delete all product option.
 */
function fn_product_variations_poptions_delete_product_post($product_id)
{
    $child_products = db_get_fields(
        'SELECT product_id FROM ?:products WHERE parent_product_id = ?i AND product_type = ?s',
        $product_id, ProductManager::PRODUCT_TYPE_VARIATION
    );

    foreach ($child_products as $child_product_id) {
        fn_delete_product($child_product_id);
    }
}

/**
 * Hook handler: before selecting products.
 */
function fn_product_variations_get_products_pre(&$params, $items_per_page, $lang_code)
{
    /** @var ProductManager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    if (!isset($params['product_type'])) {
        $params['product_type'] = $product_manager->getCreatableProductTypes();
    }

    if (!isset($params['parent_product_id'])) {
        $params['parent_product_id'] = 0;
    }

    if (!isset($params['variation_code'])) {
        $params['variation_code'] = null;
    }

    $params['product_type'] = (array) $params['product_type'];
    $params['parent_product_id'] = array_filter((array) $params['parent_product_id']);
}

/**
 * Hook handler: before selecting products.
 */
function fn_product_variations_get_products($params, $fields, $sortings, &$condition, &$join, $sorting, $group_by, $lang_code, $having)
{
    if (!fn_product_variations_value_is_empty($params['product_type'])) {
        if (is_array($params['product_type'])) {
            $condition .= db_quote(' AND products.product_type IN (?a)', $params['product_type']);
        } else {
            $condition .= db_quote(' AND products.product_type = ?s', $params['product_type']);
        }
    }

    if (!fn_product_variations_value_is_empty($params['parent_product_id'])) {
        if (is_array($params['parent_product_id'])) {
            $condition .= db_quote(' AND products.parent_product_id IN (?n)', $params['parent_product_id']);
        } else {
            $condition .= db_quote(' AND products.parent_product_id = ?i', $params['parent_product_id']);
        }
    }

    if (!fn_product_variations_value_is_empty($params['variation_code'])) {
        if (is_array($params['variation_code'])) {
            $condition .= db_quote(' AND products.variation IN (?a)', $params['variation_code']);
        } else {
            $condition .= db_quote(' AND products.variation = ?s', $params['variation_code']);
        }
    }

    // FIXME Dirty hack
    if (in_array(ProductManager::PRODUCT_TYPE_VARIATION, $params['product_type'], true)
        && strpos($join, 'ON products_categories.product_id = products.product_id') !== false
    ) {
        $join = str_replace(
            'ON products_categories.product_id = products.product_id',
            'ON products_categories.product_id = products.parent_product_id OR products_categories.product_id = products.product_id',
            $join
        );
    }
}

/**
 * Hook handler: change SQL parameters for product data select
 */
function fn_product_variations_get_product_data($product_id, $field_list, &$join, $auth, $lang_code, $condition)
{
    // FIXME Dirty hack
    $join = str_replace(
        'ON ?:products_categories.product_id = ?:products.product_id',
        'ON ?:products_categories.product_id = ?:products.parent_product_id OR ?:products_categories.product_id = ?:products.product_id',
        $join
    );
}

/**
 * Hook handler: particularize product data
 */
function fn_product_variations_get_product_data_post(&$product_data, $auth, $preview, $lang_code)
{
    if (empty($product_data)) {
        return;
    }

    if ($product_data['product_type'] === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        $product_data['amount'] = db_get_field(
            'SELECT MAX(amount) FROM ?:products WHERE parent_product_id = ?i AND product_type = ?s',
            $product_data['product_id'], ProductManager::PRODUCT_TYPE_VARIATION
        );

        $product_data['detailed_params']['is_preview'] = $preview;
    } elseif ($product_data['product_type'] === ProductManager::PRODUCT_TYPE_VARIATION) {
        if (fn_allowed_for('ULTIMATE')) {
            $product_data['shared_product'] = fn_ult_is_shared_product($product_data['parent_product_id']);
            $product_data['shared_between_companies'] = fn_ult_get_shared_product_companies($product_data['parent_product_id']);
        }

        // Skip creating seo name
        $product_data['seo_name'] = $product_data['product_id'];
    }
}

/**
 * Hook handler: on clone product.
 */
function fn_product_variations_clone_product_data($product_id, $data, &$is_cloning_allowed)
{
    if ($data['product_type'] === ProductManager::PRODUCT_TYPE_VARIATION) {
        $is_cloning_allowed = false;
    }
}

/**
 * Hook handler: changes before gathering additional products data
 */
function fn_product_variations_gather_additional_products_data_params($product_ids, $params, &$products, $auth, $products_images, $additional_images, $product_options, $has_product_options, $has_product_options_links)
{
    $loader = new AdditionalDataLoader(
        $products, $params, $auth, CART_LANGUAGE, Tygh::$app['addons.product_variations.product.manager'], Tygh::$app['db']
    );

    Registry::set('product_variations_loader', $loader);

    foreach ($products as &$product) {
        if (!isset($product['product_type'])) {
            $product['product_type'] = null;
        }

        if ($product['product_type'] === ProductManager::PRODUCT_TYPE_CONFIGURABLE && $params['get_options']) {
            $options = isset($product_options[$product['product_id']]) ? $product_options[$product['product_id']] : array();
            $product = $loader->setOptions($product, $options);
        }
    }
}

/**
 * Hook handler: changes before gathering product options.
 */
function fn_product_variations_gather_additional_product_data_before_options(&$product, $auth, &$params)
{
    if ($product['product_type'] === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        $params['get_options'] = false;
        /** @var AdditionalDataLoader $loader */
        $loader = Registry::get('product_variations_loader');

        $product = $loader->loadBaseData($product);
    }
}

/**
 * Hook handler: add additional data to product
 */
function fn_product_variations_gather_additional_product_data_post(&$product, $auth, &$params)
{
    /** @var AdditionalDataLoader $loader */
    $loader = Registry::get('product_variations_loader');

    if ($product['product_type'] === ProductManager::PRODUCT_TYPE_CONFIGURABLE
        && ($params['get_features'] || isset($product['detailed_params']['info_type']) && $product['detailed_params']['info_type'] === 'D')
    ) {
        $product = $loader->loadFeatures($product);
    }

    $base_params = $loader->getParams();
    $params['get_options'] = $base_params['get_options'];

    if (isset($product['detailed_params'])) {
        $product['detailed_params']['get_options'] = $params['get_options'];
    }
}

/**
 * Hook handler: on before product features saved.
 */
function fn_product_variations_update_product_features_value_pre($product_id, $product_features, $add_new_variant, $lang_code, $params, &$category_ids)
{
    /** @var ProductManager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $product_type = $product_manager->getProductFieldValue($product_id, 'product_type');

    if ($product_type === ProductManager::PRODUCT_TYPE_VARIATION) {
        $parent_product_id = $product_manager->getProductFieldValue($product_id, 'parent_product_id');

        $id_paths = db_get_fields(
            'SELECT ?:categories.id_path FROM ?:products_categories '
            . 'LEFT JOIN ?:categories ON ?:categories.category_id = ?:products_categories.category_id '
            . 'WHERE product_id = ?i',
            $parent_product_id
        );

        $category_ids = array_unique(explode('/', implode('/', $id_paths)));
    }
}

/**
 * Hook handler: on get additional information on change options request.
 */
function fn_product_variations_get_additional_information($product, $product_data)
{
    if ($product && $product['product_type'] === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        /** @var \Tygh\SmartyEngine\Core $view */
        $view = Tygh::$app['view'];

        /* [Product tabs] */
        $tabs = \Tygh\BlockManager\ProductTabs::instance()->getList(
            '',
            $product['product_id'],
            DESCR_SL
        );

        foreach ($tabs as $tab_id => $tab) {
            if ($tab['status'] == 'D') {
                continue;
            }
            if (!empty($tab['template'])) {
                $tabs[$tab_id]['html_id'] = fn_basename($tab['template'], '.tpl');
            } else {
                $tabs[$tab_id]['html_id'] = 'product_tab_' . $tab_id;
            }

            if ($tab['show_in_popup'] != 'Y') {
                Registry::set('navigation.tabs.' . $tabs[$tab_id]['html_id'], array (
                    'title' => $tab['name'],
                    'js' => true
                ));
            }
        }

        $view->assign('tabs', $tabs);
        /* [/Product tabs] */
    }
}

/**
 * Hook handler: on reorder product.
 */
function fn_product_variations_reorder_product($order_info, $cart, $auth, $product, $amount, &$price, $zero_price_action)
{
    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];
    $product_id = $product['product_id'];

    $product_type = isset($product['product_type']) ? $product['product_type'] : $product_manager->getProductFieldValue($product_id, 'product_type');

    if ($product_type === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        $variation_id = $product_manager->getVariationId($product_id, (array) $product['product_options']);

        if ($variation_id) {
            $price = fn_get_product_price($variation_id, $amount, $auth);
        }
    }
}

/**
 * Hook handler: on update product.
 */
function fn_product_variations_update_product_pre(&$product_data, $product_id, $lang_code, &$can_update)
{
    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    if (!empty($product_id)) {
        $current_product_data = db_get_row('SELECT * FROM ?:products WHERE product_id = ?i', $product_id);
    }

    if (isset($product_data['parent_product_id'])) {
        $parent_product_id = (int) $product_data['parent_product_id'];
    } elseif (!empty($current_product_data)) {
        $parent_product_id = (int) $current_product_data['parent_product_id'];
    }

    if (!empty($parent_product_id)) {
        $parent_product_data = db_get_row('SELECT * FROM ?:products WHERE product_id = ?i', $parent_product_id);
    }

    if (isset($product_data['product_type'])) {
        $product_type = $product_data['product_type'];
    } elseif (!empty($current_product_data)) {
        $product_type = $current_product_data['product_type'];
    }

    if (!empty($product_type) && $product_type === ProductManager::PRODUCT_TYPE_VARIATION) {
        $current_variation_options = isset($current_product_data['variation_options']) ? json_decode($current_product_data['variation_code']) : null;
        $variation_options = isset($product_data['variation_options']) ? $product_data['variation_options'] : null;

        if (empty($parent_product_data)) {
            fn_set_notification('E', __('error'), __('product_variations.error.product_variation_must_have_parent_product'));
            $can_update = false;
            return;
        }

        if (empty($product_id) && empty($variation_options)) {
            fn_set_notification('E', __('error'), __('product_variations.error.product_variation_must_have_variation_options'));
            $can_update = false;
            return;
        } elseif ($variation_options && $variation_options !== $current_variation_options) {
            $variant_ids = array_values($variation_options);
            $option_ids = json_decode($parent_product_data['variation_options'], true);
            $variation_code = $product_manager->getVariationCode($parent_product_data['product_id'], $variation_options);

            $cnt = db_get_field(
                'SELECT COUNT(*) AS cnt FROM ?:product_option_variants WHERE variant_id IN (?n) AND option_id IN (?n)',
                $variant_ids,
                $option_ids
            );

            if ($cnt != count($variant_ids)) {
                fn_set_notification('E', __('error'), __('product_variations.error.invalid_variation_options_array'));
                $can_update = false;
                return;
            }

            $exist_variation_product_id = db_get_field('SELECT product_id FROM ?:products WHERE variation_code = ?s', $variation_code);

            if ($exist_variation_product_id) {
                fn_set_notification('E', __('error'), __('product_variations.error.product_variation_already_exists'));
                $can_update = false;
                return;
            }

            $product_data['variation_code'] = $variation_code;
            $product_data['variation_options'] = json_encode($variation_options);
        }
    }
}

/**
 * Hook handler: on applying product options rules
 */
function fn_product_variations_apply_options_rules_post(&$product)
{
    $product['options_update'] = true;
}

/**
 * Hook handler: on gets product code.
 */
function fn_product_variations_get_product_code($product_id, $selected_options, &$product_code)
{
    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $product_type = $product_manager->getProductFieldValue($product_id, 'product_type');

    if ($product_type === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        $variation_id = $product_manager->getVariationId($product_id, $selected_options);

        if ($variation_id) {
            $product_code = $product_manager->getProductFieldValue($variation_id, 'product_code');
        }
    }
}

/**
 * Hook handler: on before gets product data on add product to cart.
 */
function fn_product_variations_pre_get_cart_product_data($hash, $product, $skip_promotion, $cart, $auth, $promotion_amount, &$fields, $join)
{
    $fields[] = '?:products.product_type';
    $fields[] = '?:products.variation_options';
}

/**
 * Hook handler: on gets product data on add product to cart.
 */
function fn_product_variations_get_cart_product_data($product_id, &$_pdata, $product, $auth, &$cart, $hash)
{
    $cart['products'][$hash]['product_type'] = $_pdata['product_type'];

    if ($_pdata['product_type'] !== ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        return;
    }

    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $selected_options = isset($product['product_options']) ? $product['product_options'] : array();
    $amount = !empty($product['amount_total']) ? $product['amount_total'] : $product['amount'];

    $variation_id = $product_manager->getVariationId($product_id, $selected_options);

    if ($variation_id) {
        $product_type_instance = $product_manager->getProductTypeInstance($_pdata['product_type']);

        $_pdata['price'] = fn_get_product_price($variation_id, $amount, $auth);
        $_pdata['tracking'] = ProductTracking::TRACK_WITHOUT_OPTIONS;
        $_pdata['in_stock'] = $product_manager->getProductFieldValue($variation_id, 'amount');

        if (!isset($_pdata['extra'])) {
            $_pdata['extra'] = array();
        }

        $_pdata['extra']['variation_product_id'] = $variation_id;

        if (!isset($product['stored_price']) || $product['stored_price'] !== 'Y') {
            $_pdata['base_price'] = $_pdata['price'];
        }

        foreach ($_pdata as $key => $value) {
            if ($product_type_instance->isFieldMergeable($key) && $key !== 'amount') {
                $_pdata[$key] = $product_manager->getProductFieldValue($variation_id, $key);
            }
        }
    }
}

/**
 * Hook handler: on update product quantity.
 */
function fn_product_variations_update_product_amount_pre(&$product_id, $amount, $product_options, $sign, &$tracking, &$current_amount, &$product_code)
{
    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $product_type = $product_manager->getProductFieldValue($product_id, 'product_type');

    if ($product_type === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        $variation_id = $product_manager->getVariationId($product_id, $product_options);

        if ($variation_id) {
            $product_id = $variation_id;
            $current_amount  = $product_manager->getProductFieldValue($variation_id, 'amount');
            $product_code  = $product_manager->getProductFieldValue($variation_id, 'product_code');
            $tracking = ProductTracking::TRACK_WITHOUT_OPTIONS;
        }
    }
}

/**
 * Hook handler: on checks product quantity in stock.
 */
function fn_product_variations_check_amount_in_stock_before_check($product_id, $amount, $product_options, $cart_id, $is_edp, $original_amount, $cart, $update_id, &$product, &$current_amount)
{
    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $product_type = $product_manager->getProductFieldValue($product_id, 'product_type');

    if ($product_type === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        $product_type_instance = $product_manager->getProductTypeInstance($product_type);
        $variation_id = $product_manager->getVariationId($product_id, $product_options);

        if ($variation_id) {
            $current_amount = $product_manager->getProductFieldValue($variation_id, 'amount');
            $avail_since = $product_manager->getProductFieldValue($variation_id, 'avail_since');

            if (!empty($avail_since) && TIME < $avail_since) {
                $current_amount = 0;
            }

            foreach ($product as $key => $value) {
                if ($product_type_instance->isFieldMergeable($key)) {
                    $product[$key] = $product_manager->getProductFieldValue($variation_id, $key);
                }
            }

            if (!empty($cart['products']) && is_array($cart['products'])) {
                foreach ($cart['products'] as $key => $item) {
                    if ($key != $cart_id && $item['product_id'] == $product_id) {
                        if (isset($item['extra']['variation_product_id'])) {
                            $item_variation_id = $item['extra']['variation_product_id'];
                        } else {
                            $item_variation_id = $product_manager->getVariationId($product_id, $item['product_options']);
                        }

                        if ($item_variation_id == $variation_id) {
                            $current_amount -= $item['amount'];
                        }
                    }
                }
            }
        }
    }
}

/**
 * Hook handler: on checks product price on add product to cart.
 */
function fn_product_variations_add_product_to_cart_get_price($product_data, $cart, $auth, $update, $_id, &$data, $product_id, $amount, &$price, $zero_price_action, $allow_add)
{
    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $product_type = $product_manager->getProductFieldValue($product_id, 'product_type');
    $data['extra']['product_type'] = $product_type;

    if ($product_type === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        $variation_id = $product_manager->getVariationId($product_id, $data['product_options']);

        if ($variation_id) {
            $data['extra']['variation_product_id'] = $variation_id;
            $price = fn_get_product_price($variation_id, $amount, $auth);
        }
    }
}

/**
 * Hook handler: on add product to cart.
 */
function fn_product_variations_add_to_cart(&$cart, $product_id, $_id)
{
    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $product_type = $product_manager->getProductFieldValue($product_id, 'product_type');
    $cart['products'][$_id]['product_type'] = $product_type;
}

/**
 * Hook handler: on gets product image pairs.
 */
function fn_product_variations_get_cart_product_icon($product_id, $product_data, $selected_options, &$image)
{
    if (!empty($selected_options)) {
        /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
        $product_manager = Tygh::$app['addons.product_variations.product.manager'];

        $product_type = $product_manager->getProductFieldValue($product_id, 'product_type');

        if ($product_type === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
            $variation_id = $product_manager->getVariationId($product_id, $selected_options);
            $variation_image = fn_get_image_pairs($variation_id, 'product', 'M', true, true);

            if (!empty($variation_image)) {
                $image = $variation_image;
            }
        }
    }
}

/**
 * Hook handler: on creates order details.
 */
function fn_product_variations_create_order_details($order_id, $cart, &$order_details, $extra)
{
    if (!empty($extra['product_options'])) {
        /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
        $product_manager = Tygh::$app['addons.product_variations.product.manager'];

        $product_id = $order_details['product_id'];
        $product_type = $product_manager->getProductFieldValue($product_id, 'product_type');
        $extra['product_type'] = $product_type;

        if ($product_type === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
            $order_details['product_code'] = fn_get_product_code($product_id, $extra['product_options']);
        }

        $order_details['extra'] = serialize($extra);
    }
}

/**
 * Hook handler: on data feed export.
 */
function fn_product_variations_data_feeds_export($datafeed_id, &$options, &$pattern, $fields, $datafeed_data)
{
    if (!empty($datafeed_data['export_options']['product_types'])) {
        $product_types = $datafeed_data['export_options']['product_types'];
        $pattern['product_types'] = $product_types;

        if (in_array(ProductManager::PRODUCT_TYPE_VARIATION, $product_types)
            && !in_array(ProductManager::PRODUCT_TYPE_CONFIGURABLE, $product_types)
        ) {
            $product_types[] = ProductManager::PRODUCT_TYPE_CONFIGURABLE;
        }

        $pattern['condition']['conditions']['product_type'] = $product_types;
    } else {
        $pattern['product_types'] = array();
    }

    unset($options['product_types']);
}


/**
 * Hook handler: on before dispatch displayed
 */
function fn_product_variations_dispatch_before_display()
{
    $controller = Registry::get('runtime.controller');
    $mode = Registry::get('runtime.mode');

    if (AREA !== 'A' || $controller !== 'products' || $mode !== 'update') {
        return;
    }

    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];
    /** @var \Tygh\SmartyEngine\Core $view */
    $view = Tygh::$app['view'];

    /** @var array $product_data */
    $product_data = $view->getTemplateVars('product_data');

    $product_type = $product_manager->getProductTypeInstance($product_data['product_type']);

    $tabs = Registry::get('navigation.tabs');

    foreach ($tabs as $key => $tab) {
        if (!$product_type->isTabAvailable($key)) {
            unset($tabs[$key]);
        }
    }

    Registry::set('navigation.tabs', $tabs);
}

/**
 * Hook handler: on update cart products
 */
function fn_product_variations_update_cart_products_post(&$cart)
{
    foreach ($cart['products'] as &$product) {
        if (!empty($product['product_options'])) {
            /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
            $product_manager = Tygh::$app['addons.product_variations.product.manager'];
            $product_type = $product_manager->getProductFieldValue($product['product_id'], 'product_type');
            $product['extra']['product_type'] = $product_type;

            if ($product_type === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
                $variation_id = $product_manager->getVariationId($product['product_id'], $product['product_options']);

                if ($variation_id) {
                    $product['extra']['variation_product_id'] = $variation_id;
                }
            }
        }
    }
}