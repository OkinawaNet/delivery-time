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
use Tygh\Shippings\RusSdek;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($mode == 'autocomplete_city') {
    $params = $_REQUEST;

    if (defined('AJAX_REQUEST') && $params['q']) {
        $lang_code = CART_LANGUAGE;
        $select = array();
        $prefix = array('гор.','г.' ,'г ', 'гор ','город ');

        $params['q'] = str_replace($prefix,'',$params['q']);
        $search = trim($params['q'])."%";
        
        if (db_has_table("rus_cities")) {

            $join = db_quote("LEFT JOIN ?:rus_cities as c ON c.city_id = d.city_id");

            $condition = db_quote(" AND c.status = ?s AND d.lang_code = ?s ", 'A', $lang_code);

            if (!empty($params['check_country']) && $params['check_country'] != 'undefined') {
                $condition .= db_quote(" AND c.country_code = ?s", $params['check_country']);

                if (!empty($params['check_state']) && $params['check_state'] != 'undefined') {
                    $state_code = db_get_field("SELECT b.code FROM ?:state_descriptions as a LEFT JOIN ?:states as b ON a.state_id = b.state_id WHERE a.state = ?s AND a.lang_code = ?s ", $params['check_state'], $lang_code);

                    if (!empty($state_code)) {
                        $condition .= db_quote(" AND c.state_code = ?s", $state_code);
                    }
                }
            }

            $cities = db_get_array("SELECT d.city, c.city_code FROM ?:rus_city_descriptions as d ?p WHERE city LIKE ?l ?p  LIMIT ?i", $join , $search , $condition, 10);

            if (!empty($cities)) {
                foreach ($cities as $city) {
                    $select[] = array(
                        'code' => $city['city_code'],
                        'value' => $city['city'],
                        'label' => $city['city'],
                    );
                }
            }
        }

        Registry::get('ajax')->assign('autocomplete', $select);
        exit();
    }

} elseif ($mode == 'sdek_get_city_data') {
    $params = $_REQUEST;

    if (defined('AJAX_REQUEST')) {
        $location['country'] = 'RU';

        if (!empty($params['check_country']) && $params['check_country'] != 'undefined') {
            $location['country'] = $params['check_country'];

            if (!empty($params['check_state']) && $params['check_state'] != 'undefined') {
                $state_code = db_get_field("SELECT b.code FROM ?:state_descriptions as a LEFT JOIN ?:states as b ON a.state_id = b.state_id WHERE a.state = ?s ", $params['check_state']);

                if (!empty($state_code)) {
                    $location['state'] = $state_code;
                }
            }
        }

        $location['city'] = $params['var_city'];

        $data = RusSdek::cityId($location);

        $city_data = array(
            'from_city_id' => $data,
        );

        Tygh::$app['view']->assign('sdek_new_city_data', $city_data);
        Tygh::$app['view']->display('addons/rus_sdek/views/shippings/components/services/sdek.tpl');
        exit;
    }

} elseif ($mode == 'select_state') {
    if (defined('AJAX_REQUEST')) {
        $states = fn_get_all_states();

        Tygh::$app['view']->assign('_country', $_REQUEST['country']);
        Tygh::$app['view']->assign('_state', $_REQUEST['state']);
        Tygh::$app['view']->assign('states', $states);
        Tygh::$app['view']->display('addons/rus_sdek/views/shippings/components/services/sdek.tpl');
        exit;
    }
}

if ($mode == 'cities_update') {
    fn_sdek_update_table_cities();

    return array(CONTROLLER_STATUS_REDIRECT, "addons.update?addon=rus_sdek");
}
