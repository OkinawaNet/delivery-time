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

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$cart = & $_SESSION['cart'];

if (!empty($_REQUEST['select_yd_store'])) {
    foreach ($_REQUEST['select_yd_store'] as $g_id => $select) {
        foreach ($select as $s_id => $o_id) {
            $cart['shippings_extra']['data'][$g_id][$s_id]['pickup_point_id'] = $o_id;
        }
    }
}

if (!empty($_REQUEST['select_yd_courier'])) {
    foreach ($_REQUEST['select_yd_courier'] as $g_id => $select) {
        foreach ($select as $s_id => $o_id) {
            $cart['shippings_extra']['data'][$g_id][$s_id]['courier_point_id'] = $o_id;
        }
    }
}