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

if ($mode === 'view') {
    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];
    /** @var \Tygh\SmartyEngine\Core $view */
    $view = Tygh::$app['view'];

    /** @var array $products */
    $products = $view->getTemplateVars('products');

    foreach ($products as &$product) {
        if (!isset($product['product_features'])) {
            $product['product_features'] = fn_get_product_features_list($product);
            $product = fn_product_variations_merge_features($product);
        }
    }
    unset($product);

    $view->assign('products', $products);
}