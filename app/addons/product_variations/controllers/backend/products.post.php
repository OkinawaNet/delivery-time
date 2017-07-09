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
use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

/**
 * @var string $mode
 * @var string $action
 * @var array $auth
 */

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($mode === 'manage' || $mode === 'p_subscr') {
        /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
        $product_manager = Tygh::$app['addons.product_variations.product.manager'];
        /** @var \Tygh\SmartyEngine\Core $view */
        $view = Tygh::$app['view'];

        $view->assign('product_types', $product_manager->getProductTypeNames());
    } elseif ($mode == 'add') {
        /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
        $product_manager = Tygh::$app['addons.product_variations.product.manager'];
        /** @var \Tygh\SmartyEngine\Core $view */
        $view = Tygh::$app['view'];

        $view->assign('product_types', $product_manager->getProductTypeNames($product_manager->getCreatableProductTypes()));
        $view->assign('product_type', $product_manager->getProductTypeInstance(ProductManager::PRODUCT_TYPE_SIMPLE));
    } elseif ($mode == 'update') {
        /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
        $product_manager = Tygh::$app['addons.product_variations.product.manager'];
        /** @var \Tygh\SmartyEngine\Core $view */
        $view = Tygh::$app['view'];

        /** @var array $product_data */
        $product_data = $view->getTemplateVars('product_data');

        $product_type = $product_manager->getProductTypeInstance($product_data['product_type']);

        $view->assign('product_type', $product_type);

        if ($product_data['product_type'] === ProductManager::PRODUCT_TYPE_VARIATION) {
            $parent_product_data = fn_get_product_data($product_data['parent_product_id'], $auth);
            $view->assign('parent_product_data', $parent_product_data);
        }

        $tabs = Registry::get('navigation.tabs');

        if ($product_data['product_type'] === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
            $index = array_search('options', array_keys($tabs)) + 1;

            $tabs = array_merge(
                array_slice($tabs, 0, $index, true),
                array(
                    'variations' => array(
                        'title' => __('product_variations.variations'),
                        'href' => 'product_variations.list?product_id=' . $product_data['product_id'],
                        'ajax' => true
                    )
                ),
                array_slice($tabs, $index, null, true)
            );
        }

        Registry::set('navigation.tabs', $tabs);
    } elseif ($mode == 'm_update') {
        /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
        $product_manager = Tygh::$app['addons.product_variations.product.manager'];
        /** @var \Tygh\SmartyEngine\Core $view */
        $view = Tygh::$app['view'];

        /** @var array $products */
        $products = $view->getTemplateVars('products_data');

        foreach ($products as &$product) {
            $product['type'] = $product_manager->getProductTypeInstance($product['product_type']);
        }
        unset($product);

        $view->assign('products_data', $products);
    }
}