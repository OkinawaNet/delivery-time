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

use Tygh\Languages\Languages;
use Tygh\Registry;
use Tygh\Bootstrap;

if ( !defined('AREA') ) { die('Access denied'); }

function fn_rus_edost_install()
{
    $services = fn_get_schema('edost', 'services', 'php', true);

    foreach ($services as $service) {
        $service_id = db_query('INSERT INTO ?:shipping_services ?e', $service);
        $service['service_id'] = $service_id;

        foreach (Languages::getAll() as $service['lang_code'] => $lang_data) {
            db_query('INSERT INTO ?:shipping_service_descriptions ?e', $service);
        }
    }

    fn_edost_update_table_cities();
}

function fn_rus_edost_uninstall()
{
    $service_ids = db_get_fields('SELECT service_id FROM ?:shipping_services WHERE module = ?s', 'edost');

    if (!empty($service_ids)) {
        db_query('DELETE FROM ?:shipping_services WHERE service_id IN (?a)', $service_ids);
        db_query('DELETE FROM ?:shipping_service_descriptions WHERE service_id IN (?a)', $service_ids);
    }

    $sdek_addon = db_get_fields("SELECT addon FROM ?:addons WHERE addon = ?s", 'rus_sdek');
    $cities_addon = db_get_fields("SELECT addon FROM ?:addons WHERE addon = ?s", 'rus_cities');

    if (empty($sdek_addon) && empty($cities_addon)) {
        db_query ("DROP TABLE IF EXISTS `?:rus_cities`");
        db_query ("DROP TABLE IF EXISTS `?:rus_city_descriptions`");
    }
}

function fn_rus_edost_update_cart_by_data_post(&$cart, $new_cart_data, $auth)
{
    if (!empty($new_cart_data['select_office'])) {
        $cart['select_office'] = $new_cart_data['select_office'];
    }

}

function fn_rus_edost_calculate_cart_taxes_pre(&$cart, $cart_products, &$product_groups)
{
    if (!empty($cart['shippings_extra'])) {
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

                        if($shipping['module'] != 'edost') {
                            continue;
                        }

                        if (!empty($cart['shippings_extra']['data'][$group_key][$shipping_id])) {
                            $shippings_extra = $cart['shippings_extra']['data'][$group_key][$shipping_id];
                            $product_groups[$group_key]['chosen_shippings'][$shipping_key]['data'] = $shippings_extra;

                            if (!empty($select_office[$group_key][$shipping_id])) {
                                $office_id = $select_office[$group_key][$shipping_id];
                                $product_groups[$group_key]['chosen_shippings'][$shipping_key]['office_id'] = $office_id;

                                if (!empty($shippings_extra['office'][$office_id])) {
                                    $office_data = $shippings_extra['office'][$office_id];
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
                foreach ($shippings as $shipping_id => $shipping_data) {
                    if (!empty($product_groups[$group_key]['shippings'][$shipping_id]['module'])) 
                    {                    
                        $module = $product_groups[$group_key]['shippings'][$shipping_id]['module'];
                        if (!empty($shipping_data) && $module == 'edost') {
                            $product_groups[$group_key]['shippings'][$shipping_id]['data'] = $shipping_data;
                        }
                    }

                }
            }
        }

        if (!empty($cart['shippings_extra']['rates'])) {
            foreach ($cart['shippings_extra']['rates'] as $group_key => $shippings) {
                foreach ($shippings as $shipping_id => $shipping) {
                    if (!empty($shipping['day']) && !empty($product_groups[$group_key]['shippings'][$shipping_id])) {
                        $product_groups[$group_key]['shippings'][$shipping_id]['delivery_time'] = $shipping['day'];
                    }
                }
            }
        }
    }

    if (!empty($cart['payment_id'])) {
        $payment_info = fn_get_payment_method_data($cart['payment_id']);

        if (strpos($payment_info['template'], 'edost_cod.tpl')) {
            $cart['shippings_extra']['sum'] = array(
                'pricediff' => 0,
                'transfer' => 0,
                'total' => 0
            );

            foreach ($product_groups as $group_key => $group) {
                foreach ($group['shippings'] as $shipping_id => $shipping) {
                    if (!empty($cart['shippings_extra']['rates'][$group_key][$shipping_id]['pricecash'])) {
                        $cart['product_groups'][$group_key]['shippings'][$shipping_id]['rate'] = $cart['shippings_extra']['rates'][$group_key][$shipping_id]['pricecash'];
                        $product_groups[$group_key]['shippings'][$shipping_id]['rate'] = $cart['shippings_extra']['rates'][$group_key][$shipping_id]['pricecash'];
                    }

                    if (!empty($cart['shipping'][$shipping_id])) {
                        $cart['shipping'][$shipping_id]['rate'] = $cart['shippings_extra']['rates'][$group_key][$shipping_id]['pricecash'];
                        $cart['shipping'][$shipping_id]['rates'] = 1;
                    }
                }

                if (!empty($group['chosen_shippings'])) {
                    foreach ($group['chosen_shippings'] as $shipping_key => $shipping) {
                        $shipping_id = $shipping['shipping_id'];

                        if (!empty($cart['shippings_extra']['rates'][$group_key][$shipping_id]['pricecash'])) {
                            $cart['product_groups'][$group_key]['shippings'][$shipping_id]['rate'] = $cart['shippings_extra']['rates'][$group_key][$shipping_id]['pricecash'];
                            $cart['shippings_extra']['sum']['pricediff'] += $cart['shippings_extra']['rates'][$group_key][$shipping_id]['pricediff'];
                        }

                        $cart['shippings_extra']['sum']['transfer'] += $cart['shippings_extra']['rates'][$group_key][$shipping_id]['transfer'];

                        if (!empty($cart['shippings_extra']['rates'][$group_key][$shipping['shipping_id']]['pricecash'])) {
                            $product_groups[$group_key]['chosen_shippings'][$shipping_key]['rate'] = $cart['shippings_extra']['rates'][$group_key][$shipping['shipping_id']]['pricecash'];
                            $cart['shipping_cost'] = $cart['shippings_extra']['rates'][$group_key][$shipping['shipping_id']]['pricecash'];
                            $cart['display_shipping_cost'] = $cart['shipping_cost'];
                        }
                    }

                    $cart['shippings_extra']['sum']['total'] = $cart['shippings_extra']['sum']['transfer'] + $cart['shippings_extra']['sum']['pricediff'];
                }

            }

        }

        $_SESSION['shipping_hash'] = fn_get_shipping_hash($cart['product_groups']);
    }
}

function fn_edost_update_table_cities()
{
    $update_cities = array();
    $insert_cities = array();
    $update_cities_description = array();
    $u_cities_description = array();
    $_cities_description = array();
    $data = array();
    $max_line_size = 165536;
    $delimiter = ',';

    if (db_has_table("rus_cities")) {
        $cities = db_get_hash_array(
            "SELECT city_id, country_code, state_code, city_code, status, sdek_city_code "
            . "FROM ?:rus_cities",
            "city_code"
        );

        $all_cities = db_get_array(
            "SELECT a.city_id, a.country_code, a.state_code, a.city_code, a.status, a.sdek_city_code, b.city "
            . "FROM ?:rus_cities as a LEFT JOIN ?:rus_city_descriptions as b ON a.city_id = b.city_id"
        );

        $add_path = Registry::get('config.dir.root') . '/app/addons/rus_edost/database/cities_description.csv';
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

                    if (!empty($data_city['city_code'])) {
                        $u_cities_description[$data_city['city_code']][$data_city['lang_code']] = array(
                            'city_id' => '',
                            'lang_code' => $data_city['lang_code'],
                            'city' => $data_city['city']
                        );

                        if (!empty($all_cities)) {
                            $update_cities_description[$data_city['city_code']][$data_city['lang_code']] = array(
                                'city_id' => '',
                                'lang_code' => $data_city['lang_code'],
                                'city' => $data_city['city']
                            );
                            $_city = mb_strtolower(trim($data_city['city']));

                            foreach ($all_cities as &$d_city) {

                                if (preg_match("/\b" . $_city . "\b/ui", mb_strtolower(trim($d_city['city'])))) {
                                    $d_city['city_code'] = $data_city['city_code'];

                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['city_id'] = $d_city['city_id'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['country_code'] = $d_city['country_code'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['state_code'] = $d_city['state_code'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['status'] = $d_city['status'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['sdek_city_code'] = $d_city['sdek_city_code'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['city'] = $_city;

                                    break;
                                }

                                if ($d_city['city_code'] == $data_city['city_code']) {
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['city_id'] = $d_city['city_id'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['country_code'] = $d_city['country_code'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['state_code'] = $d_city['state_code'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['status'] = $d_city['status'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['sdek_city_code'] = $d_city['sdek_city_code'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['city'] = $_city;

                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        $add_path = Registry::get('config.dir.root') . '/app/addons/rus_edost/database/cities.csv';
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

                    if (!empty($cities[$data_city['city_code']])) {
                        $city_id = $cities[$data_city['city_code']]['city_id'];
                        $status = $cities[$data_city['city_code']]['status'];
                        $sdek_city_code = $cities[$data_city['city_code']]['sdek_city_code'];

                        $update_cities[] = array(
                            'city_id' => $city_id,
                            'country_code' => $data_city['country_code'],
                            'state_code' => $data_city['state_code'],
                            'city_code' => $data_city['city_code'],
                            'status' => $status,
                            'sdek_city_code' => $sdek_city_code
                        );

                    } elseif (!empty($update_cities_description[$data_city['city_code']])) {
                        $d_city = reset($update_cities_description[$data_city['city_code']]);
                        $city_id = $d_city['city_id'];
                        $status = (!empty($d_city['status'])) ? $d_city['status'] : 'A';
                        $sdek_city_code = (!empty($d_city['sdek_city_code'])) ? $d_city['sdek_city_code'] : '';

                        if (!empty($all_cities)) {
                            foreach ($all_cities as &$a_city) {
                                if (preg_match("/\b" . $d_city['city'] . "\b/ui", mb_strtolower(trim($a_city['city']))) && $d_city['state_code'] == $data_city['state_code'] && $d_city['country_code'] == $data_city['country_code']) {
                                    $city_id = $d_city['city_id'];
                                    $status = $d_city['status'];
                                    $sdek_city_code = $d_city['sdek_city_code'];

                                    break;
                                }
                            }
                        }

                        $update_cities[] = array(
                            'city_id' => $city_id,
                            'country_code' => $data_city['country_code'],
                            'state_code' => $data_city['state_code'],
                            'city_code' => $data_city['city_code'],
                            'status' => $status,
                            'sdek_city_code' => $sdek_city_code
                        );

                    } else {
                        $insert_cities[] = array(
                            'country_code' => $data_city['country_code'],
                            'state_code' => $data_city['state_code'],
                            'city_code' => $data_city['city_code'],
                            'status' => 'A',
                            'sdek_city_code' => ''
                        );
                    }
                }
            }
        }

        if (!empty($insert_cities)) {
            db_query("REPLACE INTO ?:rus_cities ?m", $insert_cities);
        }

        if (!empty($update_cities)) {
            db_query("REPLACE INTO ?:rus_cities ?m", $update_cities);
        }

        $cities = db_get_hash_array(
            "SELECT city_id, country_code, state_code, city_code, status "
            . "FROM ?:rus_cities",
            "city_code"
        );

        foreach ($u_cities_description as $c_code => $des_city) {
            if (!empty($cities[$c_code])) {
                foreach ($des_city as $lang_code => $d_city) {
                    $_cities_description[] = array(
                        'city_id' => $cities[$c_code]['city_id'],
                        'lang_code' => $lang_code,
                        'city' => $d_city['city']
                    );
                }
            }
        }

        if (!empty($_cities_description)) {
            db_query("REPLACE INTO ?:rus_city_descriptions ?m", $_cities_description);
        }
    }

    fn_set_notification('N', __('notice'), __('addons.edost.text_update_cities'));
}
