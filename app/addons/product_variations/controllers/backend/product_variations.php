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

use Tygh\Languages\Languages;
use Tygh\Enum\ProductTracking;
use Tygh\Addons\ProductVariations\Product\Manager as ProductManager;
use Tygh\Storage;


if (!defined('BOOTSTRAP')) { die('Access denied'); }

/**
 * @var string $mode
 * @var string $action
 * @var array $auth
 */

if ($mode == 'generate') {
    /** @var ProductManager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $product_id = (int) $_REQUEST['product_id'];
    $options_variant_ids = isset($_REQUEST['options_variant_ids']) ? (array) $_REQUEST['options_variant_ids'] : array();
    $variations_data = isset($_REQUEST['variations_data']) ? (array) $_REQUEST['variations_data'] : array();
    $combinations = $selected_variant_ids = $selected_options_ids = array();
    $step = 'select_options_variants';

    $product_data = fn_get_product_data($product_id, $auth);

    if (empty($product_data)) {
        return array(CONTROLLER_STATUS_NO_PAGE);
    }

    $product_options = fn_get_product_options($product_id, CART_LANGUAGE, true);

    if (empty($product_options)) {
        return array(CONTROLLER_STATUS_NO_PAGE);
    }

    foreach ($options_variant_ids as $option_id => $variant_ids) {
        if (isset($product_options[$option_id])) {
            foreach ($variant_ids as $variant_id) {
                if (isset($product_options[$option_id]['variants'][$variant_id])) {
                    $selected_options_ids[$option_id] = $option_id;
                    $selected_variant_ids[$option_id][$variant_id] = $variant_id;
                }
            }
        }
    }

    if (count($selected_options_ids) > 0) {
        $options_combinations = fn_get_options_combinations($selected_options_ids, $selected_variant_ids);

        if (!empty($options_combinations)) {
            $step = 'options_combinations';
            $index = 0;

            foreach ($options_combinations as $selected_options) {
                $index++;
                $variation_code = $product_manager->getVariationCode($product_id, $selected_options);
                $combinations[$variation_code] = fn_product_variations_get_variation_by_selected_options(
                    $product_data,
                    $product_options,
                    $selected_options,
                    $index
                );
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        foreach ($combinations as $variation_code => &$combination) {
            if (isset($variations_data[$variation_code])) {
                $combination = array_merge(
                    $combination,
                    array_filter($variations_data[$variation_code])
                );
            } else {
                unset($combinations[$variation_code]);
            }

            unset($combination);
        }

        fn_product_variations_generate($product_id, $combinations, $selected_options_ids);

        return array(CONTROLLER_STATUS_REDIRECT, "products.manage?parent_product_id={$product_id}&product_type=" . ProductManager::PRODUCT_TYPE_VARIATION);
    }

    /** @var \Tygh\SmartyEngine\Core $view */
    $view = Tygh::$app['view'];

    $view->assign('product_data', $product_data);
    $view->assign('product_options', $product_options);
    $view->assign('combinations', $combinations);
    $view->assign('step', $step);
} elseif ($mode === 'list') {
    /** @var \Tygh\SmartyEngine\Core $view */
    $view = Tygh::$app['view'];

    $product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;

    if (empty($product_id)) {
        return array(CONTROLLER_STATUS_NO_PAGE);
    }

    $params = array_merge($_REQUEST, array(
        'product_type' => ProductManager::PRODUCT_TYPE_VARIATION,
        'parent_product_id' => $product_id
    ));

    list($products, $search) = fn_get_products($params);
    fn_gather_additional_products_data($products, array('get_icon' => true, 'get_detailed' => true, 'get_options' => false, 'get_discounts' => false));

    $view
        ->assign('product_id', $product_id)
        ->assign('products', $products)
        ->assign('search', $search);
} elseif ($mode === 'convert') {
    /** @var ProductManager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $product_id = isset($_REQUEST['product_id']) ?  (int) $_REQUEST['product_id'] : 0;

    if ($product_id <= 0) {
        return array(CONTROLLER_STATUS_NO_PAGE);
    }

    $product_data = fn_get_product_data($product_id, $auth);

    if (empty($product_data) || $product_data['product_type'] !== ProductManager::PRODUCT_TYPE_SIMPLE) {
        return array(CONTROLLER_STATUS_NO_PAGE);
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        fn_product_variations_convert_to_configurable_product($product_id);

        fn_set_notification('N', __('notice'), __('product_variations.convert_to_configurable_product_success'));
    }

    return array(CONTROLLER_STATUS_REDIRECT, "products.update?product_id={$product_id}");
}

/**
 * Generates variations of a product and saves those variations to the database.
 *
 * @param int   $product_id     Product identifier
 * @param array $combinations   List of available combinations
 * @param array $options_ids    List of option identifier
 */
function fn_product_variations_generate($product_id, $combinations, array $options_ids)
{
    $languages = Languages::getAll();
    $product_row = db_get_row('SELECT * FROM ?:products WHERE product_id = ?i', $product_id);

    $product_variation_ids = db_get_fields('SELECT product_id FROM ?:products  WHERE parent_product_id = ?i', $product_id);

    foreach ($product_variation_ids as $product_variation_id) {
        fn_delete_product($product_variation_id);
    }

    foreach ($combinations as $variation_code => $combination) {
        fn_product_variations_save_variation($product_row, $combination, $languages);
    }

    db_query(
        'UPDATE ?:products SET product_type = ?s, variation_options = ?s WHERE product_id = ?i',
        ProductManager::PRODUCT_TYPE_CONFIGURABLE, json_encode(array_values($options_ids)), $product_id
    );
}

/**
 * Saves product variation by product combination.
 *
 * @param array $parent_product_data    Parent product data
 * @param array $combination            Product combination data
 * @param array $languages              List of languages
 *
 * @return int
 */
function fn_product_variations_save_variation($parent_product_data, array $combination, $languages)
{
    $data = array_merge($parent_product_data, array(
        'product_id' => null,
        'tracking' => ProductTracking::TRACK_WITHOUT_OPTIONS,
        'product_type' => ProductManager::PRODUCT_TYPE_VARIATION,
        'parent_product_id' => $parent_product_data['product_id'],
        'variation_code' => $combination['variation'],
        'variation_options' => json_encode($combination['selected_options']),
        'timestamp' => time(),
        'list_price' => $combination['list_price'],
        'weight' => $combination['weight'],
        'amount' => empty($combination['amount']) ? 1 : $combination['amount'],
        'product_code' => $combination['code'],
    ));

    $product_variation_id = db_query('INSERT INTO ?:products ?e', $data);

    fn_update_product_prices($product_variation_id, array(
        'price' => $combination['price'],
        'prices' => array()
    ));

    foreach ($languages as $lang_code => $lang) {
        $description_data = array(
            'product_id' => $product_variation_id,
            'company_id' => $data['company_id'],
            'lang_code' => $lang_code,
            'product' => $combination['name']
        );

        db_query('INSERT INTO ?:product_descriptions ?e', $description_data);
    }

    return $product_variation_id;
}

/**
 * Gets variation by selected options.
 *
 * @param array $product            Product data
 * @param array $product_options    Product options
 * @param array $selected_options   List of selected options
 * @param int   $index              Current variation index
 *
 * @return array
 */
function fn_product_variations_get_variation_by_selected_options($product, $product_options, $selected_options, $index = 0)
{
    /** @var ProductManager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $name_part = array($product['product']);
    $variation_code = $product_manager->getVariationCode($product['product_id'], $selected_options);
    $options = array();

    foreach ($selected_options as $option_id => $variant_id) {
        $option_id = (int) $option_id;
        $variant_id = (int) $variant_id;

        $option = $product_options[$option_id];
        $option['value'] = $variant_id;

        $variant = $product_options[$option_id]['variants'][$variant_id];

        $name_part[] = $option['option_name'] . ': ' .$variant['variant_name'];
        $options[$option_id] = $option;
    }

    $combination = array(
        'name' => implode(', ', $name_part),
        'price' => $product['price'],
        'list_price' => $product['list_price'],
        'weight' => $product['weight'],
        'amount' => empty($product['amount']) ? 1 : $product['amount'],
        'code' => !empty($product['product_code']) ? $product['product_code'] . $index : '',
        'options' => $options,
        'selected_options' => $selected_options,
        'variation' => $variation_code
    );

    return $combination;
}

/**
 * Converts product to configurable product.
 *
 * @param int $product_id Product identifier
 */
function fn_product_variations_convert_to_configurable_product($product_id)
{
    $auth = array();

    $product = fn_get_product_data($product_id, $auth);
    $languages = Languages::getAll();
    $product_options = fn_get_product_options($product_id, CART_LANGUAGE, true);
    $product_row = db_get_row('SELECT * FROM ?:products WHERE product_id = ?i', $product_id);
    $product_variation_ids = db_get_fields('SELECT product_id FROM ?:products  WHERE parent_product_id = ?i', $product_id);
    $product_exceptions = fn_get_product_exceptions($product_id);

    foreach ($product_variation_ids as $product_variation_id) {
        fn_delete_product($product_variation_id);
    }

    $options_ids = array();
    $inventory_combinations = db_get_array('SELECT * FROM ?:product_options_inventory WHERE product_id = ?i', $product_id);
    $index = 0;

    foreach ($inventory_combinations as $item) {
        $index++;
        $selected_options = array();
        $parts = array_chunk(explode('_', $item['combination']), 2);

        foreach ($parts as $part) {
            $selected_options[$part[0]] = $part[1];
        }

        $combination = fn_product_variations_get_variation_by_selected_options(
            $product,
            $product_options,
            $selected_options,
            $index
        );

        if (!empty($item['product_code'])) {
            $combination['code'] = $item['product_code'];
        }

        if (!empty($item['amount'])) {
            $combination['amount'] = $item['amount'];
        }

        $is_allow = true;

        if ($product_row['exceptions_type'] == 'F') {
            foreach ($product_exceptions as $exception) {

                foreach ($exception['combination'] as $option_id => &$variant_id) {
                    if ($variant_id == OPTION_EXCEPTION_VARIANT_ANY || $variant_id == OPTION_EXCEPTION_VARIANT_NOTHING) {
                        $variant_id = isset($combination['selected_options'][$option_id]) ? $combination['selected_options'][$option_id] : null;
                    }
                }
                unset($variant_id);

                if ($exception['combination'] == $combination['selected_options']) {
                    $is_allow = false;
                    break;
                }
            }
        } elseif ($product_row['exceptions_type'] == 'A') {
            $is_allow = false;

            foreach ($product_exceptions as $exception) {

                foreach ($exception['combination'] as $option_id => &$variant_id) {
                    if ($variant_id == OPTION_EXCEPTION_VARIANT_ANY) {
                        $variant_id = isset($combination['selected_options'][$option_id]) ? $combination['selected_options'][$option_id] : null;
                    }
                }
                unset($variant_id);

                if ($exception['combination'] == $combination['selected_options']) {
                    $is_allow = true;
                    break;
                }
            }
        }

        if (!$is_allow) {
            continue;
        }

        $variation_id = fn_product_variations_save_variation($product_row, $combination, $languages);

        $image = fn_get_image_pairs($item['combination_hash'], 'product_option', 'M', true, true);

        if ($image) {
            $detailed = $icons = array();
            $pair_data = array(
                'type' => 'M'
            );

            if (!empty($image['icon'])) {
                $tmp_name = fn_create_temp_file();
                Storage::instance('images')->export($image['icon']['relative_path'], $tmp_name);
                $name = fn_basename($image['icon']['image_path']);

                $icons[$image['pair_id']] = array(
                    'path' => $tmp_name,
                    'size' => filesize($tmp_name),
                    'error' => 0,
                    'name' => $name
                );

                $pair_data['image_alt'] = empty($image['icon']['alt']) ? '' : $image['icon']['alt'];
            }

            if (!empty($image['detailed'])) {
                $tmp_name = fn_create_temp_file();
                Storage::instance('images')->export($image['detailed']['relative_path'], $tmp_name);
                $name = fn_basename($image['detailed']['image_path']);

                $detailed[$image['pair_id']] = array(
                    'path' => $tmp_name,
                    'size' => filesize($tmp_name),
                    'error' => 0,
                    'name' => $name
                );

                $pair_data['detailed_alt'] = empty($image['detailed']['alt']) ? '' : $image['detailed']['alt'];
            }

            $pairs_data = array(
                $image['pair_id'] => $pair_data
            );

            fn_update_image_pairs($icons, $detailed, $pairs_data, $variation_id, 'product');
        }
    }

    if (!empty($selected_options)) {
        $options_ids = array_keys($selected_options);
    }

    db_query(
        'UPDATE ?:products SET product_type = ?s, variation_options = ?s WHERE product_id = ?i',
        ProductManager::PRODUCT_TYPE_CONFIGURABLE, json_encode(array_values($options_ids)), $product_id
    );

    fn_delete_product_option_combinations($product_id);
    db_query('DELETE FROM ?:product_options_exceptions WHERE product_id = ?i', $product_id);
}