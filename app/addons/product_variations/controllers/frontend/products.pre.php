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

use Tygh\Addons\ProductVariations\Product\Manager as ProductManager;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

/**
 * @var string $mode
 * @var string $action
 * @var array $auth
 */

if ($mode == 'view' || $mode == 'quick_view') {
    $product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : null;

    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];

    $product_type = $product_manager->getProductFieldValue($product_id, 'product_type');

    if ($product_type === ProductManager::PRODUCT_TYPE_VARIATION) {
        $parent_product_id = $product_manager->getProductFieldValue($product_id, 'parent_product_id');
        $selected_options = $product_manager->getProductVariationOptionsValue($product_id);
        $combination_hash = fn_get_options_combination($selected_options);

        $params = array(
            'product_id' => $parent_product_id,
            'combination' => $combination_hash
        );

        if (isset($_REQUEST['action'])) {
            $params['action'] = $_REQUEST['action'];
        }

        return array(CONTROLLER_STATUS_REDIRECT, 'products.view?' . http_build_query($params) , true);
    }
}