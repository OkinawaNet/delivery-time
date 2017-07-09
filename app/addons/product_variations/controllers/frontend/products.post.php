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
    /** @var \Tygh\Addons\ProductVariations\Product\Manager $product_manager */
    $product_manager = Tygh::$app['addons.product_variations.product.manager'];
    /** @var \Tygh\SmartyEngine\Core $view */
    $view = Tygh::$app['view'];

    /** @var array $product */
    $product = $view->getTemplateVars('product');

    if ($product['product_type'] === ProductManager::PRODUCT_TYPE_CONFIGURABLE) {
        $product = fn_product_variations_merge_features($product);

        $view->assign('product', $product);

        $variation_id = $product['variation_product_id'];
        $user_id = isset(Tygh::$app['session']['auth']['user_id']) ? Tygh::$app['session']['auth']['user_id'] : 0;
        $is_subscribed = false;

        if (
            isset(Tygh::$app['session']['product_notifications']['product_ids'])
            && in_array($variation_id, Tygh::$app['session']['product_notifications']['product_ids'])
        ) {
            $is_subscribed = true;
        } elseif (empty($user_id) && !empty(Tygh::$app['session']['product_notifications']['email'])) {
            $is_subscribed = db_get_field(
                'SELECT subscription_id FROM ?:product_subscriptions WHERE product_id = ?i AND email = ?s',
                $variation_id,
                Tygh::$app['session']['product_notifications']['email']
            );
        } elseif (!empty($user_id)) {
            $is_subscribed = db_get_field(
                'SELECT subscription_id FROM ?:product_subscriptions WHERE product_id = ?i AND user_id = ?i',
                $variation_id,
                $user_id
            );
        }

        $view->assign('product_notification_enabled', $is_subscribed ? 'Y' : 'N');
    }
}