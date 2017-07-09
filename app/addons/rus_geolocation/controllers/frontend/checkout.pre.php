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
use Tygh\Session;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$cart = & Tygh::$app['session']['cart'];
$params = $_REQUEST;
$city_geolocation = isset(Tygh::$app['session']['geocity']) ? Tygh::$app['session']['geocity'] : null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($mode == 'add_profile') {
        if (isset($city_geolocation)) {
            $list_cities = fn_rus_geolocation_select_cities($city_geolocation);

            if (!empty($list_cities['city'])) {
                $_REQUEST['user_data']['b_city'] = $list_cities['city'];
                $_REQUEST['user_data']['s_city'] = $list_cities['city'];
                $_REQUEST['user_data']['b_country'] = $list_cities['code'];
                $_REQUEST['user_data']['s_country'] = $list_cities['code'];
                $_REQUEST['user_data']['b_state'] = $list_cities['state_code'];
                $_REQUEST['user_data']['s_state'] = $list_cities['state_code'];
            }
        }
    }
}

if ($mode == 'cart' || $mode == 'customer_info') {
    if (isset($city_geolocation) && empty($cart['user_data']['user_id'])) {
        $list_cities = fn_rus_geolocation_select_cities($city_geolocation);

        if (!empty($list_cities['city'])) {
            $cart['user_data']['b_city'] = $list_cities['city'];
            $cart['user_data']['s_city'] = $list_cities['city'];
            $cart['user_data']['b_country'] = $list_cities['code'];
            $cart['user_data']['s_country'] = $list_cities['code'];
            $cart['user_data']['b_state'] = $list_cities['state_code'];
            $cart['user_data']['s_state'] = $list_cities['state_code'];
        }
    }
}
