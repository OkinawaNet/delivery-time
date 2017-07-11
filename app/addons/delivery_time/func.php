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

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

function fn_delivery_time_pre_get_cart_product_data(&$hash, &$product, &$skip_promotion, &$cart, &$auth, &$promotion_amount, &$fields, &$join)
{
    $fields[] = '?:products.delivery_time';
}

function fn_delivery_time_calculate_cart_items(&$cart, &$cart_products, &$auth, &$apply_cart_promotions)
{
    $min_delivery_time = 0;

    foreach ($cart_products as $product) {

        if (
            !empty($product['delivery_time'])
            &&
            (
                (0 == $min_delivery_time)
                ||
                ($product['delivery_time'] < $min_delivery_time)
            )
        ) {
            $min_delivery_time = $product['delivery_time'];
        }
    }

    $min_delivery_time += 2 * floor($min_delivery_time / 5);

    if ($min_delivery_time > 0) {
        $cart['delivery_timestamp'] = time() + $min_delivery_time * 86400;
    }
}

function fn_delivery_time_pre_get_orders(&$params, &$fields, &$sortings, &$get_totals, &$lang_code)
{
    $fields[] = '?:orders.delivery_timestamp';
}

function fn_delivery_time_get_orders_post(&$params, &$orders)
{
    foreach ($orders as &$order) {
        if (
            0 != $order['delivery_timestamp']
            &&
            'C' != $order['status']
            &&
            time() > $order['delivery_timestamp']
        ) {
            $t = time() - $order['delivery_timestamp'];
            $days = round($t / 86400);
            $hours = ($t / 3600) % 24;

            if (!empty($days) || !empty($hours)) {
                $order['delay']['days'] = $days;
                $order['delay']['hours'] = $hours;
            }
        }
    }
}
