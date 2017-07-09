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

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$params = $_REQUEST;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
}

if ($mode == 'autocomplete_city') {
    $params = $_REQUEST;

    $data_country = db_get_fields("SELECT code FROM ?:countries WHERE status = 'A'");

    $all_countries = db_get_hash_array("SELECT code, country FROM ?:country_descriptions", "code");
    $all_states = db_get_hash_multi_array("SELECT a.code, a.country_code, b.state FROM ?:states as a LEFT JOIN ?:state_descriptions as b ON a.state_id = b.state_id WHERE lang_code = ?s", array("country_code", "code"), CART_LANGUAGE);

    if (defined('AJAX_REQUEST') && $params['q'] && !empty($data_country)) {
        $select = array();
        $prefix = array('гор.','г.' ,'г ', 'гор ','город ');

        $params['q'] = str_replace($prefix,'',$params['q']);

        $table = '?:rus_cities';
        $table_description = '?:rus_city_descriptions';

        $search = trim($params['q'])."%";

        $join = db_quote("LEFT JOIN $table as c ON c.city_id = d.city_id");

        $condition = db_quote(" AND c.status = ?s", 'A');

        $data_states = db_get_fields("SELECT code FROM ?:states WHERE country_code IN (?a) ", $data_country);
        $condition .= db_quote(" AND c.state_code IN (?a) ", $data_states);

        $cities = db_get_array("SELECT c.country_code, c.state_code, d.city, c.city_code FROM $table_description as d ?p WHERE city LIKE ?l AND lang_code = ?s  ?p  LIMIT ?i", $join, $search, CART_LANGUAGE, $condition, 10);

        if (!empty($cities)) {
            foreach ($cities as $city) {
                $country = (!empty($all_countries[$city['country_code']]['country'])) ? $all_countries[$city['country_code']]['country'] : '';
                $state = (!empty($all_states[$city['country_code']][$city['state_code']]['state'])) ? $all_states[$city['country_code']][$city['state_code']]['state'] : '';

                $select[] = array(
                    'code' => $city['city_code'],
                    'value' => $city['city'],
                    'label' => $city['city'] . ' / (' . $country . ', ' . $state . ')'
                );
            }
        }

        Tygh::$app['ajax']->assign('autocomplete', $select);
        exit();
    }
}

if ($mode == 'popup_geo') {
    $data_cities = '1';

    $list_geo_cities = trim(Registry::get('addons.rus_geolocation.list_cities_geolocation'));

    if (!empty($list_geo_cities)) {
        $cities_params = '';
        $condition = 'WHERE 1';
        $u_params = '';

        $data_countries = db_get_fields(
            "SELECT countries.code " . 
            "FROM ?:countries as countries LEFT JOIN ?:country_descriptions as country_descriptions ON countries.code = country_descriptions.code " . 
            "WHERE countries.status = 'A'"
        );

        if (!empty($data_countries)) {
            $data_states = db_get_fields(
                "SELECT c1.code " . 
                "FROM ?:states as c1 LEFT JOIN ?:state_descriptions as c2 ON c1.state_id = c2.state_id " . 
                "WHERE c1.status = 'A' AND country_code IN (?a)", $data_countries
            );

            if (!empty($data_states)) {
                $cities = explode("\n", $list_geo_cities);
                foreach ($cities as $city) {
                    $city = trim($city);
                    $cities_params .= db_quote('?p a.city = ?s', $u_params, $city);
                    $u_params = ' OR ';
                }
                $condition .= db_quote(' AND (?p) ', $cities_params);
                $condition .= db_quote(" AND b.status = 'A' ");
                $condition .= db_quote(" AND b.country_code IN (?a) AND b.state_code IN (?a) ", $data_countries, $data_states);

                $city_geolocation = (!empty(\Tygh::$app['session']['geocity'])) ? \Tygh::$app['session']['geocity'] : '';

                $list_cities = db_get_hash_array(
                    "SELECT city_code, city FROM ?:rus_city_descriptions as a LEFT JOIN ?:rus_cities as b ON a.city_id = b.city_id " . 
                    "$condition ", 'city'
                );
            }
        }

        $data_cities = (!empty($list_cities)) ? $list_cities : '1';
    } else {
        $data_cities = fn_rus_geolocation_list_destinations_cities();
    }
    if (!empty($_REQUEST['geocity'])) {
        \Tygh::$app['session']['geocity'] = $_REQUEST['geocity'];
    }

    Tygh::$app['view']->assign('data_cities', $data_cities);
}

if ($mode == 'product_shipping_list') {
    if (!empty(\Tygh::$app['session']['geocity'])) {
        $city_geolocation = \Tygh::$app['session']['geocity'];
        $shipping_methods = array();
        $show_data = false;
        $shippings = 0;

        $data_city = fn_rus_geolocation_select_cities($city_geolocation);
        if (!empty($data_city)) {
            $user_data = array(
                'b_country' => $data_city['code'],
                'b_state' => $data_city['state_code'],
                'b_city' => $data_city['city'],
                's_country' => $data_city['code'],
                's_state' => $data_city['state_code'],
                's_city' => $data_city['city']
            );

            $cart['user_data'] = $user_data;
            $show_data = true;

            $data_product[$params['product_id']] = fn_get_product_data($params['product_id'], $auth);

            if (!empty($data_product[$params['product_id']]['shipping_params'])) {
                $data_product[$params['product_id']]['shipping_params'] = unserialize($data_product[$params['product_id']]['shipping_params']);
            }

            $data_product[$params['product_id']]['amount'] = 1;
            $d_product = fn_add_product_to_cart($data_product, $cart, $auth);
            $cart['total'] = $data_product[$params['product_id']]['price'];
            $cart['original_subtotal'] = $data_product[$params['product_id']]['price'];
            $cart['display_subtotal'] = $data_product[$params['product_id']]['price'];
            $cart['subtotal'] = $data_product[$params['product_id']]['price'];
            $cart['total'] = $data_product[$params['product_id']]['price'];
            $cart['amount'] = 1;

            $d_calculate = fn_rus_geolocation_calculate_rate_shipping_product($cart, $data_product);
            $shipping_methods = (!empty($d_calculate['shippings'])) ? $d_calculate['shippings'] : array();
        }

        Tygh::$app['view']->assign('shipping_methods', $shipping_methods);
        Tygh::$app['view']->assign('show_data', $show_data);

    }
}
