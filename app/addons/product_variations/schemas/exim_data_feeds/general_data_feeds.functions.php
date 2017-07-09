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
 * 'copyright.txt' FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

use Tygh\Addons\ProductVariations\Product\Manager as ProductManager;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

/**
 * Prepares product data.
 *
 * @param array $data       Raw result of exported products.
 * @param array $result     Formatted result of exported products.
 * @param array $multi_lang List of exported languages.
 * @param array $pattern    Exim schema.
 */
function fn_product_variations_exim_data_feeds_processing_by_product_type($data, &$result, $multi_lang, $pattern)
{
    static $last_parent_product = null;

    foreach ($result as $key => &$items) {
        foreach ($items as $lang_code => &$product) {
            $data_item = $data[$key][$lang_code];

            if ($data_item['product_type'] === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
                $last_parent_product = $product;
            } elseif ($data_item['product_type'] === ProductManager::PRODUCT_TYPE_VARIATION) {
                foreach ($product as $field => $value) {
                    if (empty($value) && isset($last_parent_product[$field])) {
                        $product[$field] = $last_parent_product[$field];
                    }
                }
            }

            unset($product);
        }
    }

    unset($items);
}

/**
 * Prepares product data. Checks available product type and removed it if is not available.
 *
 * @param array $data       Raw result of exported products.
 * @param array $result     Formatted result of exported products.
 * @param array $multi_lang List of exported languages.
 * @param array $pattern    Exim schema.
 */
function fn_product_variations_exim_data_feeds_pre_processing_check_product_types($data, &$result, $multi_lang, $pattern)
{
    $product_types = $pattern['product_types'];

    foreach ($result as $key => &$items) {
        foreach ($items as $lang_code => &$product) {
            $data_item = $data[$key][$lang_code];

            if (!in_array($data_item['product_type'], $product_types)) {
                unset($result[$key]);
                break 2;
            }
        }
        unset($product);
    }
    unset($items);
}