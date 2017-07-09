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
use Tygh\Bootstrap;

if ( !defined('AREA') ) { die('Access denied'); }

function fn_rus_cities_install()
{
    fn_cities_update_table_cities();
}

function fn_rus_cities_uninstall()
{
    $sdek_addon = db_get_fields("SELECT addon FROM ?:addons WHERE addon = ?s", 'rus_sdek');
    $edost_addon = db_get_fields("SELECT addon FROM ?:addons WHERE addon = ?s", 'rus_edost');

    if (empty($sdek_addon) && empty($edost_addon)) {
        db_query ("DROP TABLE IF EXISTS `?:rus_cities`");
        db_query ("DROP TABLE IF EXISTS `?:rus_city_descriptions`");
    }
}

function fn_get_cities($params = array(), $items_per_page = 0, $lang_code = CART_LANGUAGE)
{
    // Set default values to input params
    $default_params = array (
        'page' => 1,
        'items_per_page' => $items_per_page
    );

    $params = array_merge($default_params, $params);

    $fields = array(
        'c.city_id',
        'c.country_code',
        'c.state_code',
        'c.city_code',
        'c.sdek_city_code',
        'c.status',
        'cd.city',
    );

    $condition = '';
    if (!empty($params['only_avail'])) {
        $condition .= db_quote(" AND c.status = ?s", 'A');
    }

    if (!empty($params['q'])) {
        $condition .= db_quote(" AND cd.city LIKE ?l", '%' . $params['q'] . '%');
    }

    if (!empty($params['state_code'])) {
        $condition .= db_quote(" AND c.state_code = ?s", $params['state_code']);
    }

    if (!empty($params['country_code'])) {
        $condition .= db_quote(" AND c.country_code = ?s", $params['country_code']);
    }

    $join = "LEFT JOIN ?:rus_city_descriptions as cd ON cd.city_id = c.city_id AND cd.lang_code = ?s ";

    $limit = '';

    if (!empty($params['items_per_page'])) {
        $params['total_items'] = db_get_field("SELECT count(*) FROM ?:rus_cities as c $join WHERE 1 ?p", $lang_code,  $condition);
        $limit = db_paginate($params['page'], $params['items_per_page']);
    }

    $cities = db_get_array(
        "SELECT " . implode(', ', $fields) . " FROM ?:rus_cities as c $join WHERE 1 ?p ORDER BY cd.city $limit",
    $lang_code, $condition);

    foreach ($cities as &$city) {
        if (empty($city['city'])) {
            $city['city'] = db_get_field("SELECT city FROM ?:rus_city_descriptions WHERE city_id = ?i AND lang_code = 'ru'", $city['city_id']);
        }
    }

    return array($cities, $params);
}

function fn_get_all_cities($avail_only = true, $lang_code = CART_LANGUAGE)
{
    $avail_cond = ($avail_only == true) ? " WHERE a.status = 'A' AND b.status = 'A'" : '';

    $countries = db_get_hash_multi_array("SELECT a.country_code, a.code as state_id, b.code, c.city, b.city_id FROM ?:states as a " .
        "LEFT JOIN ?:rus_cities as b ON b.state_id = a.state_id " .
        "LEFT JOIN ?:rus_city_descriptions as c ON c.city_id = b.city_id AND c.lang_code = ?s " .
        "$avail_cond ORDER BY a.country_code, b.code, c.city", array('country_code'), $lang_code);

    $rus_countries = db_get_hash_array("SELECT city_id, city FROM ?:rus_city_descriptions WHERE lang_code = ?s", 'city_id', 'ru');

    $cities = array();

    foreach ($countries as $c_code => $states) {
        foreach ($states as $city) {
            if (!empty($city['city_id'])) {
                $cities[$c_code][$city['state_id']][] = array(
                    'code' => $city['code'],
                    'city' => empty($city['city']) ? $rus_countries[$city['city_id']]['city'] : $city['city']
                );
            }
        }
    }

    return $cities;
}

function fn_update_city($city_data, $city_id = 0, $lang_code = DESCR_SL)
{
    if (empty($city_id)) {
        if (!empty($city_data['city']) && !empty($city_data['city_code'])) {
            $city_data['city_id'] = $city_id = db_query("REPLACE INTO ?:rus_cities ?e", $city_data);

            foreach (fn_get_translation_languages() as $city_data['lang_code'] => $_v) {
                db_query('REPLACE INTO ?:rus_city_descriptions ?e', $city_data);
            }
        }

    } else {
        if (!empty($city_data['city']) && !empty($city_data['city_code'])) {
            db_query("UPDATE ?:rus_cities SET ?u WHERE city_id = ?i", $city_data, $city_id);

            $exist = db_get_field("SELECT city_id FROM ?:rus_city_descriptions WHERE city_id = ?i AND lang_code = ?s", $city_id, $lang_code);

            if ($exist)
                db_query("UPDATE ?:rus_city_descriptions SET ?u WHERE city_id = ?i AND lang_code = ?s", $city_data, $city_id, $lang_code);
            else {
                $city_data['city_id'] = $city_id;
                $city_data['lang_code'] = $lang_code;
                db_query("INSERT INTO ?:rus_city_descriptions ?e", $city_data);
            }
        }
    }

    return $city_id;
}

function fn_cities_update_table_cities()
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

        $add_path = Registry::get('config.dir.root') . '/app/addons/rus_cities/database/cities_description.csv';
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

                                    break;
                                }

                                if ($d_city['city_code'] == $data_city['city_code']) {
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['city_id'] = $d_city['city_id'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['country_code'] = $d_city['country_code'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['state_code'] = $d_city['state_code'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['status'] = $d_city['status'];
                                    $update_cities_description[$data_city['city_code']][$data_city['lang_code']]['sdek_city_code'] = $d_city['sdek_city_code'];

                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        $add_path = Registry::get('config.dir.root') . '/app/addons/rus_cities/database/cities.csv';
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

    fn_set_notification('N', __('notice'), __('addons.cities.text_update_cities'));
}
