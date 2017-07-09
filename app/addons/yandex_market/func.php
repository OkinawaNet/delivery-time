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

use Tygh\Registry;
use Tygh\Ym\Api;
use Tygh\Ym\OrderStatus;

/**
 * Hooks
 */

function fn_yandex_market_change_order_status(&$status_to, &$status_from, &$order_info, &$force_notification, &$order_statuses, &$place_order)
{
    if (!empty($order_info['yandex_market'])) {
        if ($place_order) {
            $force_notification = array('C' => false); // We don't know real email yet
        } else {
            $status_obj = new OrderStatus($order_info);
            $res = $status_obj->change($status_to, $status_from);
            if ($res === false) {
                $status_to = $status_from; // Prevent
            }
        }
    }
}

function fn_yandex_market_api_handle_request(&$api, &$authorized)
{
    $api_namespace = 'ym/';

    $request = $api->getRequest();
    $resource = $request->getResource();

    if (strpos($resource, $api_namespace) === 0) {
        // Prepare resource
        $resource = substr($resource, strlen($api_namespace));
        $resource = trim($resource, '/');

        $method = $request->getMethod();
        $data = $request->getData();
        $accept_type = $request->getAcceptType();

        $ym_api = new Api($resource, $method, $data, $accept_type);
        $ym_api->handleRequest();
    }
}

function fn_yandex_market_shippings_get_shippings_list_conditions(&$group, &$shippings, &$fields, &$join, &$condition, &$order_by)
{
    $fields[] = '?:shippings.ym_shipping_type';
    $fields[] = '?:shippings.ym_outlet_ids';
    $fields[] = '?:shippings.ym_from_date';
    $fields[] = '?:shippings.ym_to_date';
    $fields[] = '?:shippings.ym_order_before';
}

function fn_yandex_market_place_order(&$order_id, &$action, &$order_status, &$cart, &$auth)
{
    if (!empty($cart['yandex_market'])) {
        fn_yandex_market_update_order_ym_data($order_id, $cart['yandex_market']);
    }
}

function fn_yandex_market_get_order_info(&$order, &$additional_data)
{
    if (!empty($additional_data['Y'])) {
        $order['yandex_market'] = unserialize($additional_data['Y']);
    }
}

/**
 * \Hooks
 */


/**
 * Handlers
 */

function fn_yandex_market_info_deprecated()
{
    return __('yandex_market.info_deprecated');
}

function fn_yandex_market_clear_url_info()
{
    $storefront_url = Registry::get('config.http_location');
    if (fn_allowed_for('ULTIMATE')) {
        if (Registry::get('runtime.company_id') || Registry::get('runtime.simple_ultimate')) {
            $company = Registry::get('runtime.company_data');
            $storefront_url = 'http://' . $company['storefront'];
        } else {
            $storefront_url = '';
        }
    }

    if (!empty($storefront_url)) {
        $yml_available_in_customer = __('yml_available_in_customer', array(
            '[http_location]' => $storefront_url,
            '[yml_url]' => fn_url('yandex_market.view', 'C', 'http'),
        ));
    } else {
        $yml_available_in_customer = '';
    }

    return __('yml_clear_cache_info', array(
        '[clear_cache_url]' =>  fn_url('addons.update?addon=yandex_market?cc'),
        '[yml_available_in_customer]' => $yml_available_in_customer
    ));

}

function fn_yandex_market_purchase_get_info()
{
    return __('yandex_market.settings_info');
}

function fn_yandex_market_get_order_statuses_for_setting()
{
    static $data;

    if (empty($data)) {
        $data = array(
            '' => ' -- '
        );

        foreach (fn_get_statuses(STATUSES_ORDER) as $status) {
            $data[$status['status']] = $status['description'];
        }
    }

    return $data;
}

function fn_settings_variants_addons_yandex_market_order_status_unpaid()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_processing()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_delivery()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_pickup()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_delivered()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_processing_expired()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_replacing_order()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_shop_failed()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_user_changed_mind()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_user_not_paid()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_user_refused_delivery()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_user_refused_product()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_user_refused_quality()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_settings_variants_addons_yandex_market_order_status_unreachable()
{
    return fn_yandex_market_get_order_statuses_for_setting();
}

function fn_yandex_market_oauth_info()
{
    if (
        !fn_string_not_empty(Registry::get('addons.yandex_market.ym_application_id'))
        || !fn_string_not_empty(Registry::get('addons.yandex_market.ym_application_password'))
    ) {
        return __('yandex_market.aouth_info_part1', array(
            '[callback_uri]' => fn_url('ym_tools.oauth')
        ));
    } else {
        $client_id = Registry::get('addons.yandex_market.ym_application_id');

        return __('yandex_market.aouth_info_part2', array(
            '[auth_uri]' => "https://oauth.yandex.ru/authorize?response_type=code&client_id=" . $client_id,
            '[edit_app_uri]' => "https://oauth.yandex.ru/client/edit/" . $client_id,
        ));
    }
}

/**
 * \Handlers
 */


/**
 * Functions
 */

function fn_yandex_market_addon_install()
{
    // Order statuses
    $statuses = array(
        array(
            'status' => 'X',
            'is_default' => 'N',
            'description' => __('yandex_market.status_pickup'),
            'email_subj' => __('yandex_market.status_pickup'),
            'email_header' => __('yandex_market.status_pickup'),
            'params' => array(
                'color' => '#6aa84f',
                'notify' => 'Y',
                'notify_department' => 'N',
                'repay' => 'N',
                'inventory' => 'D',
            ),
        ),
        array(
            'status' => 'W',
            'is_default' => 'N',
            'description' => __('yandex_market.status_delivered'),
            'email_subj' => __('yandex_market.status_delivered'),
            'email_header' => __('yandex_market.status_delivered'),
            'params' => array(
                'color' => '#76a5af',
                'notify' => 'N',
                'notify_department' => 'N',
                'repay' => 'N',
                'inventory' => 'D',
            ),
        ),
    );

    foreach ($statuses as $status) {
        $exists = db_get_field(
            "SELECT status_id FROM ?:statuses WHERE status = ?s AND type = ?s",
            $status['status'], STATUSES_ORDER
        );
        if (!$exists) {
            fn_update_status('', $status, STATUSES_ORDER);
        }
    }
}

function fn_get_market_categories()
{
    return fn_get_schema('yandex_market', 'categories');
}

function fn_yandex_market_get_shipping_types($with_lang = false)
{
    $types = array(
        'delivery',
        'pickup',
        'post',
    );

    if ($with_lang) {
        $data = array();
        foreach ($types as $type) {
            $data[$type] = __('yandex_market.shipping_type_' . $type);
        }

        return $data;
    }

    return $types;
}

function fn_yandex_market_update_order_ym_data($order_id, $data)
{
    db_query("REPLACE INTO ?:order_data ?e", array(
        'order_id' => $order_id,
        'type' => 'Y', // Yandex market
        'data' => serialize($data),
    ));
}

function fn_yandex_auth_error($msg)
{
    header('WWW-Authenticate: Basic realm="Authorization required"');
    header('HTTP/1.0 401 Unauthorized');
    fn_echo($msg);
    exit;
}

function fn_yandex_auth()
{
    if (!empty($_SERVER['PHP_AUTH_USER'])) {

        $_data = array(
            'user_login' => $_SERVER['PHP_AUTH_USER'],
            'password' => $_SERVER['PHP_AUTH_PW'],
        );

        $_auth = array();
        list($status, $user_data, $user_login, $password, $salt) = fn_auth_routines($_data, $_auth);

        if (
            !empty($user_data)
            && $user_data['status'] == 'A'
            && in_array($user_data['user_type'], array('A', 'V'))
            && $user_data['password'] == fn_generate_salted_password($_SERVER['PHP_AUTH_PW'], $salt)
        ) {
            return $user_data;
        }

    }

    fn_yandex_auth_error(__("error"));
}

/**
 * \Functions
 */
