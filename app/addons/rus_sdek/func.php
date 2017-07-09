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
use Tygh\Http;
use Tygh\Languages\Languages;
use Tygh\Shippings\Shippings;
use Tygh\Bootstrap;
use Tygh\Navigation\LastView;

if ( !defined('AREA') ) { die('Access denied'); }

function fn_rus_sdek_install()
{
    $service = array(
        'status' => 'A',
        'module' => 'sdek',
        'code' => '1',
        'sp_file' => '',
        'description' => 'СДЭК',
    );
    
    $service['service_id'] = db_query('INSERT INTO ?:shipping_services ?e', $service);

    foreach (Languages::getAll() as $service['lang_code'] => $lang_data) {
        db_query('INSERT INTO ?:shipping_service_descriptions ?e', $service);
    }

    fn_sdek_update_table_cities();
}

function fn_rus_sdek_uninstall()
{
    $service_ids = db_get_fields('SELECT service_id FROM ?:shipping_services WHERE module = ?s', 'sdek');
    db_query('DELETE FROM ?:shipping_services WHERE service_id IN (?a)', $service_ids);
    db_query('DELETE FROM ?:shipping_service_descriptions WHERE service_id IN (?a)', $service_ids);

    $cities_addon = db_get_fields("SELECT addon FROM ?:addons WHERE addon = ?s", 'rus_cities');
    $edost_addon = db_get_fields("SELECT addon FROM ?:addons WHERE addon = ?s", 'rus_edost');

    if (empty($cities_addon) && empty($edost_addon)) {
        db_query ("DROP TABLE IF EXISTS `?:rus_cities`");
        db_query ("DROP TABLE IF EXISTS `?:rus_city_descriptions`");
    }

    db_query('DROP TABLE IF EXISTS ?:rus_cities_sdek');
    db_query('DROP TABLE IF EXISTS ?:rus_city_sdek_descriptions');
    db_query('DROP TABLE IF EXISTS ?:rus_sdek_products');
    db_query('DROP TABLE IF EXISTS ?:rus_sdek_register');
    db_query('DROP TABLE IF EXISTS ?:rus_sdek_status');
    db_query('DROP TABLE IF EXISTS ?:rus_sdek_history_status');
    db_query('DROP TABLE IF EXISTS ?:rus_sdek_call_recipient');
    db_query('DROP TABLE IF EXISTS ?:rus_sdek_call_courier');
}

function fn_rus_sdek_update_cart_by_data_post(&$cart, $new_cart_data, $auth)
{
    if (!empty($new_cart_data['select_office'])) {
        $cart['select_office'] = $new_cart_data['select_office'];
    }
}

function fn_rus_sdek_calculate_cart_taxes_pre(&$cart, $cart_products, &$product_groups)
{

    if (!empty($cart['shippings_extra']['data'])) {
        if (!empty($cart['select_office'])) {
            $select_office = $cart['select_office'];

        } elseif (!empty($_REQUEST['select_office'])) {
            $select_office = $cart['select_office'] = $_REQUEST['select_office'];
        }

        if (!empty($select_office)) {
            foreach ($product_groups as $group_key => $group) {
                if (!empty($group['chosen_shippings'])) {
                    foreach ($group['chosen_shippings'] as $shipping_key => $shipping) {
                        $shipping_id = $shipping['shipping_id'];

                        if($shipping['module'] != 'sdek') {
                            continue;
                        }

                        if (!empty($cart['shippings_extra']['data'][$group_key][$shipping_id])) {
                            $shippings_extra = $cart['shippings_extra']['data'][$group_key][$shipping_id];
                            $product_groups[$group_key]['chosen_shippings'][$shipping_key]['data'] = $shippings_extra;
                            if (!empty($select_office[$group_key][$shipping_id])) {
                                $office_id = $select_office[$group_key][$shipping_id];
                                $product_groups[$group_key]['chosen_shippings'][$shipping_key]['office_id'] = $office_id;

                                if (!empty($shippings_extra['offices'][$office_id])) {
                                    $office_data = $shippings_extra['offices'][$office_id];
                                    $product_groups[$group_key]['chosen_shippings'][$shipping_key]['office_data'] = $office_data;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!empty($cart['shippings_extra']['data'])) {
            foreach ($cart['shippings_extra']['data'] as $group_key => $shippings) {
                foreach ($shippings as $shipping_id => $shippings_extra) {
                    if (!empty($product_groups[$group_key]['shippings'][$shipping_id]['module'])) {
                        $module = $product_groups[$group_key]['shippings'][$shipping_id]['module'];

                        if ($module == 'sdek' && !empty($shippings_extra)) {
                            $product_groups[$group_key]['shippings'][$shipping_id]['data'] = $shippings_extra;

                            if (!empty($shippings_extra['delivery_time'])) {
                                $product_groups[$group_key]['shippings'][$shipping_id]['delivery_time'] = $shippings_extra['delivery_time'];
                            }
                        }
                    }
                }
            }
        }

        foreach ($product_groups as $group_key => $group) {
            if (!empty($group['chosen_shippings'])) {
                foreach ($group['chosen_shippings'] as $shipping_key => $shipping) {
                    $shipping_id = $shipping['shipping_id'];
                    $module = $shipping['module'];

                    if ($module == 'sdek' && !empty($cart['shippings_extra']['data'][$group_key][$shipping_id])) {
                        $shipping_extra = $cart['shippings_extra']['data'][$group_key][$shipping_id];
                        $product_groups[$group_key]['chosen_shippings'][$shipping_key]['data'] = $shipping_extra;
                    }
                }
            }
        }
    }
}

/**
 * Calculates shipping costs for SDEK.
 *
 * @param array order_info The array with the order data.
 * @param array shipping_info The array with the shipping method data.
 * @param array shipment_info The array with the shipment data.
 *
 * @return The calculated shipping cost.
 */
function fn_sdek_calculate_cost_by_shipment($order_info, $shipping_info, $shipment_info)
{
    $total = $weight = 0;
    $length = $width = $height = SDEK_DEFAULT_DIMENSIONS;
    $sum_rate = 0;

    $shipping_info['module'] = $shipment_info['carrier'];

    $symbol_grams = Registry::get('settings.General.weight_symbol_grams');

    foreach ($shipment_info['products'] as $item_id => $amount) {
        $product = $order_info['products'][$item_id];

        $total += $product['subtotal'];

        $product_extra = db_get_row("SELECT shipping_params, weight FROM ?:products WHERE product_id = ?i", $product['product_id']);

        $product_weight = fn_sdek_check_weight($product_extra['weight'], $symbol_grams);

        $p_ship_params = unserialize($product_extra['shipping_params']);

        $package_length = empty($p_ship_params['box_length']) ? $length : $p_ship_params['box_length'];
        $package_width = empty($p_ship_params['box_width']) ? $width : $p_ship_params['box_width'];
        $package_height = empty($p_ship_params['box_height']) ? $height : $p_ship_params['box_height'];
        $weight_ar = fn_expand_weight($product_weight);
        $weight = $weight_ar['plain'];

        $params_product['weight'] = $weight;
        $params_product['length'] = $package_length;
        $params_product['width'] = $package_width;
        $params_product['height'] = $package_height;

        foreach ($order_info['product_groups'] as $product_groups) {
            if (!empty($product_groups['products'][$item_id])) {
                $products[$item_id] = $product_groups['products'][$item_id];
                $products[$item_id] = array_merge($products[$item_id], $params_product);
                $products[$item_id]['amount'] = $amount;
            }

            $shipping_info['package_info'] = $product_groups['package_info'];
        }
    }

    $data_package = Shippings::groupProductsList($products, $shipping_info['package_info']['location']);
    $data_package = reset($data_package);
    $shipping_info['package_info_full'] = $data_package['package_info_full'];
    $shipping_info['package_info'] = $data_package['package_info_full'];

    $sum_rate = Shippings::calculateRates(array($shipping_info));
    $sum_rate = reset($sum_rate);
    $result = $sum_rate['price'];

    return $result;
}

function fn_sdek_get_name_customer($order_info)
{
    $firstname = $lastname = "";

    if (!empty($order_info['lastname'])) {
        $lastname = $order_info['lastname'];

    } elseif (!empty($order_info['s_lastname'])) {
        $lastname = $order_info['s_lastname'];

    } elseif (!empty($order_info['b_lastname'])) {
        $lastname = $order_info['b_lastname'];
    }

    if (!empty($order_info['firstname'])) {
        $firstname = $order_info['firstname'];

    } elseif (!empty($order_info['s_firstname'])) {
        $firstname = $order_info['s_firstname'];

    } elseif (!empty($order_info['b_firstname'])) {
        $firstname = $order_info['b_firstname'];
    }

    return $lastname . ' ' . $firstname;
}

function fn_sdek_get_phone_customer($order_info)
{
    $phone = '-';

    if (!empty($order_info['phone'])) {
        $phone = $order_info['phone'];

    } elseif (!empty($order_info['s_phone'])) {
        $phone = $order_info['s_phone'];

    } elseif (!empty($order_info['b_phone'])) {
        $phone = $order_info['b_phone'];
    }

    if (empty($phone)) {
        $phone = '-';
    }

    return $phone;
}

function fn_sdek_get_data_auth($data_auth, $b_country, $s_country, $currency_sdek)
{
    if ($b_country != 'RU' && $s_country != 'RU') {
        $data_auth['ForeignDelivery'] = 1;

        if (!empty($currency_sdek[$s_country])) {
            $data_auth['Currency'] = $currency_sdek[$s_country];

        } elseif (!empty($currency_sdek[$b_country])) {
            $data_auth['Currency'] = $currency_sdek[$b_country];

        } else {
            $data_auth['Currency'] = CART_PRIMARY_CURRENCY;
        }
    }

    return $data_auth;
}

function fn_sdek_get_product_data($sdek_products, $data_product, $order_info, $shipment_id, $amount, $symbol_grams)
{
    $ware_key = (!empty($data_product['product_code'])) ? $data_product['product_code'] : $data_product['product_id'];

    $sdek_product = array(
        'ware_key' => $ware_key,
        'order_id' => $order_info['order_id'],
        'product' => $data_product['product'],
        'amount' => $amount,
        'shipment_id' => $shipment_id
    );

    $product_weight = db_get_field("SELECT weight FROM ?:products WHERE product_id = ?i", $data_product['product_id']);

    if (!empty($data_product['product_options'])) {
        $product_options = array();
        foreach($data_product['product_options'] as $_options) {
            $product_options[$_options['option_id']] = $_options['value'];
        }

        $product_weight = fn_apply_options_modifiers($product_options, $product_weight, 'W');
    }

    $product_weight = fn_sdek_check_weight($product_weight, $symbol_grams);

    $sdek_product['weight'] = $product_weight;

    if (!empty($data_product['price']) && $data_product['price'] != 0) {
        $sdek_product['price'] = $data_product['price'] - ($data_product['price'] / $order_info['subtotal'] * $order_info['subtotal_discount']);
        $sdek_product['total'] = $sdek_product['price'] * $amount;
    }

    if (!empty($sdek_products[$ware_key]) && ($sdek_product['price'] != $sdek_products[$ware_key]['price'])) {
        $ware_key = (!empty($data_product['item_id'])) ? $data_product['item_id'] : $data_product['product_id'];
        $sdek_product['ware_key'] = $ware_key;
    }

    if (!empty($sdek_products[$ware_key])) {
        $sdek_products[$ware_key]['amount'] += $sdek_product['amount'];
        $sdek_products[$ware_key]['price'] = $sdek_product['price'];
        $sdek_products[$ware_key]['total'] += $sdek_product['total'];
        $sdek_products[$ware_key]['weight'] += $sdek_product['weight'];
    } else {
        $sdek_products[$ware_key] = $sdek_product;
    }

    if (empty($sdek_products[$ware_key]['price'])){
        $sdek_products[$ware_key]['price'] = "0.00";
    }

    if (empty($sdek_products[$ware_key]['total'])) {
        $sdek_products[$ware_key]['total'] = "0.00";
    }

    return array($sdek_products, $product_weight);
}

function fn_sdek_get_price_by_currency($price, $data_auth, $currencies, $default_currency)
{
    if (!empty($data_auth['Currency'])) {
        if (!empty($currencies[$data_auth['Currency']])) {
            $price = fn_format_price_by_currency($price, $data_auth['Currency'], $default_currency);
        }
    }

    if ($price == 0) {
        $price = '0.00';
    }

    return $price;
}

function fn_sdek_get_data_product_xml($product, $sdek_info)
{
    $payment = '0.00';
    if (!empty($sdek_info['use_imposed']) && $sdek_info['use_imposed'] == 'Y') {
        $payment = (!empty($sdek_info['CashDelivery'])) ? $sdek_info['CashDelivery'] : '0.00';

        if (!empty($sdek_info['use_product']) && $sdek_info['use_product'] == 'Y') {
            $payment += $product['price'];
        }
    }

    $product_for_xml = array (
        'WareKey' => $product['ware_key'],
        'Cost' => $product['price'],
        'Payment' => $payment,
        'Amount' => $product['amount'],
        'Comment' => $product['product']
    );

    return $product_for_xml;
}

function fn_rus_sdek_calculate_cart_items(&$cart, &$cart_products, &$auth)
{
    foreach ($cart_products as &$product) {
        if ($product['weight'] == 0) {
            $product['weight'] = round(100 / Registry::get('settings.General.weight_symbol_grams'), 3);
        }
    }
}

function fn_sdek_update_table_cities()
{
    $update_cities = array();
    $update_cities_description = array();
    $insert_cities = array();
    $insert_cities_description = array();
    $data = array();
    $max_line_size = 165536;
    $delimiter = ',';
    $add_path = Registry::get('config.dir.root') . '/app/addons/rus_sdek/database/cities_sdek.csv';

    if (db_has_table("rus_cities")) {
        $cities = db_get_hash_array(
            "SELECT a.city_id, a.country_code, a.state_code, a.city_code, a.sdek_city_code, a.status, b.city "
            . "FROM ?:rus_cities as a LEFT JOIN ?:rus_city_descriptions as b ON a.city_id = b.city_id",
            "city"
        );

        $sdek_cities = db_get_hash_array(
            "SELECT a.city_id, a.country_code, a.state_code, a.city_code, a.sdek_city_code, a.status, b.city "
            . "FROM ?:rus_cities as a LEFT JOIN ?:rus_city_descriptions as b ON a.city_id = b.city_id",
            "sdek_city_code"
        );

        $states = db_get_array(
            "SELECT a.code, b.state "
            . "FROM ?:states as a LEFT JOIN ?:state_descriptions as b ON a.state_id = b.state_id",
            "code"
        );

        if (file_exists($add_path) == true) {
            $f = fopen($add_path, 'rb');

            if ($f) {
                $import_schema = fgetcsv($f, $max_line_size, $delimiter);
                $schema_size = sizeof($import_schema);
                $skipped_lines = array();
                $line_it = 1;

                while (($data = fn_fgetcsv($f, $max_line_size, $delimiter)) !== false) {
                    $line_it ++;
                    if (fn_is_empty($data)) {
                        continue;
                    }

                    if (sizeof($data) != $schema_size) {
                        $skipped_lines[] = $line_it;
                        continue;
                    }

                    $data = str_replace(array('\r', '\n', '\t', '"'), '', $data);
                    $data_city = array_combine($import_schema, Bootstrap::stripSlashes($data));
                    $p_data_state = (!empty($data_city['state'])) ? mb_strtolower(trim($data_city['state'])) : '';
                    $p_data_state = str_replace(array('(', ')'), '', $p_data_state);
                    $sdek_city = trim($data_city['city']);

                    $sdek_state = '';
                    if (!empty($data_city['state_code'])) {
                        $sdek_state = $data_city['state_code'];

                    } elseif (!empty($states) && !empty($p_data_state)) {
                        foreach ($states as $state) {
                            $p_state = str_replace(array('(', ')'), '', mb_strtolower($state['state']));

                            if (preg_match("/\b" . $p_data_state . "\b/ui", $p_state)) {
                                $sdek_state = $state['code'];
                                continue;
                            }
                        }
                    }

                    $sdek_data = array();
                    if (!empty($cities[$sdek_city]) && $cities[$sdek_city]['country_code'] == $data_city['country_code'] && (empty($sdek_state) || ($sdek_state == $cities[$sdek_city]['state_code']))) {
                        $sdek_data = $cities[$sdek_city];
                    }

                    if (!empty($sdek_cities[$data_city['sdek_city_code']])) {
                        $sdek_data = $sdek_cities[$data_city['sdek_city_code']];
                    }

                    if (!empty($data_city['city_code']) && !empty($sdek_data)) {
                        $sdek_data['city_id'] = (!empty($sdek_data['city_id'])) ? $sdek_data['city_id'] : '';
                        $sdek_data['status'] = (!empty($sdek_data['status'])) ? $sdek_data['status'] : 'A';
                        $sdek_data['city_code'] = $data_city['city_code'];
                    }

                    if (!empty($sdek_data)) {
                        $update_cities[] = array(
                            'city_id' => $sdek_data['city_id'],
                            'country_code' => $data_city['country_code'],
                            'state_code' => $sdek_state,
                            'city_code' => $sdek_data['city_code'],
                            'status' => $sdek_data['status'],
                            'sdek_city_code' => $data_city['sdek_city_code']
                        );

                        $update_cities_description[$data_city['sdek_city_code']] = array(
                            'city_id' => $sdek_data['city_id'],
                            'lang_code' => 'ru',
                            'city' => $sdek_city
                        );

                    } else {
                        $insert_cities[] = array(
                            'country_code' => $data_city['country_code'],
                            'state_code' => $sdek_state,
                            'city_code' => $data_city['city_code'],
                            'status' => 'A',
                            'sdek_city_code' => $data_city['sdek_city_code']
                        );

                        $insert_cities_description[$data_city['sdek_city_code']] = array(
                            'lang_code' => 'ru',
                            'city' => $sdek_city
                        );
                    }
                }
            }
        }

        if (!empty($update_cities)) {
            db_query("REPLACE INTO ?:rus_cities ?m", $update_cities);
        }

        if (!empty($update_cities_description)) {
            db_query("REPLACE INTO ?:rus_city_descriptions ?m", $update_cities_description);
        }

        if (!empty($insert_cities)) {
            db_query("REPLACE INTO ?:rus_cities ?m", $insert_cities);
        }

        $sdek_cities = db_get_hash_array(
            "SELECT city_id, sdek_city_code FROM ?:rus_cities", "sdek_city_code"
        );

        foreach ($insert_cities_description as $key_sdek => $_description) {
            if (!empty($sdek_cities[$key_sdek])) {
                $insert_cities_description[$key_sdek]['city_id'] = $sdek_cities[$key_sdek]['city_id'];
            }
        }

        if (!empty($insert_cities_description)) {
            db_query("REPLACE INTO ?:rus_city_descriptions ?m", $insert_cities_description);
        }
    }

    fn_set_notification('N', __('notice'), __('shippings.sdek.text_update_cities'));
}

/**
 * Checks if the data about shipment statuses exists.
 *
 * @return boolean  Returns true if the data about shipment statuses exists; returns
 *                  false otherwise
 */
function fn_sdek_delivery_check_orders()
{
    $status_id = db_get_field("SELECT status_id FROM ?:rus_sdek_history_status LIMIT 1");

    return !empty($status_id);
}

/**
 * Gets the data of specific shipment statuses; the passed parameters determine,
 * the data of which statuses will be returned.
 *
 * @param array  $params          The parameters by which the shipment statuses
 *                                will be selected
 * @param int    $items_per_page  The maximum number of statuses to appear on one page
 * @param string $lang_code       The language code
 *
 * array  Returns an array of shipment statuses, and the parameters of the selected
 *        statuses
 */
function fn_rus_sdek_get_status($params = array(), $items_per_page = 0, $lang_code = CART_LANGUAGE)
{
    $condition = 'WHERE 1 ';
    $_view = 'sdek_status';
    $params = LastView::instance()->update($_view, $params);

    $default_params = array (
        'page' => 1,
        'items_per_page' => $items_per_page
    );

    $params = array_merge($default_params, $params);

    if (!empty($params['time_from'])) {
        $condition .= db_quote(" AND timestamp >= ?i ", strtotime($params['time_from']));

        if (!empty($params['time_to'])) {
            $condition .= db_quote(" AND timestamp < ?i ", strtotime($params['time_to']));
        }
    }

    if (!empty($params['sdek_order_id'])) {
        $condition .= db_quote(" AND order_id = ?i ", $params['sdek_order_id']);
    }

    $join = db_quote(" LEFT JOIN ?:rus_cities as b ON a.city_code = b.sdek_city_code ");
    $join .= db_quote(" LEFT JOIN ?:rus_city_descriptions as c ON b.city_id = c.city_id AND c.lang_code = ?s ", $lang_code);

    $sort_by = !empty($params['sort_by']) ? $params['sort_by'] : 'order_id';
    $sort = 'asc';
    if (!empty($params['sort_order'])) {
        $sort = $params['sort_order'];
        $params['sort_order'] = ($params['sort_order'] == 'asc') ? 'desc' : 'asc';
        $params['sort_order_rev'] = $params['sort_order'];
    } else {
        $params['sort_order'] = 'asc';
        $params['sort_order_rev'] = 'asc';
    }

    $limit = '';
    if (!empty($params['items_per_page'])) {
        $params['total_items'] = db_get_field("SELECT COUNT(*) FROM ?:rus_sdek_history_status ?p", $condition);
        $limit = db_paginate($params['page'], $params['items_per_page']);
    }

    $data_status = db_get_array("SELECT a.*, c.city FROM ?:rus_sdek_history_status as a ?p ?p ORDER BY ?p $sort $limit", $join, $condition, $sort_by);

    return array($data_status, $params);
}

/**
 * Sets the weight of a product/order to 100 grams in the specified weight measurement unit
 * when the product/order doesn't have weight.
 *
 * @param float|int  $weight        The weight of a product or order.
 * @param float|int  $symbol_grams  Grams in the unit of weight defined by the weight symbol.
 *
 * @return float|int The weight of a product or order.
 */
function fn_sdek_check_weight($weight, $symbol_grams)
{
    if (empty($weight) || $weight == 0) {
        $weight = (100 / $symbol_grams);
    }

    return $weight;
}
