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
use Tygh\Navigation\LastView;
use Tygh\Registry;
use Tygh\Common\OperationResult;

use Tygh\Shippings\YandexDelivery\YandexDelivery;
use Tygh\Shippings\YandexDelivery\Objects\Order;
use Tygh\Shippings\YandexDelivery\Objects\OrderItem;
use Tygh\Shippings\YandexDelivery\Objects\Recipient;
use Tygh\Shippings\YandexDelivery\Objects\Delivery;
use Tygh\Shippings\YandexDelivery\Objects\DeliveryPoint;


if ( !defined('AREA') ) { die('Access denied'); }

function fn_yandex_delivery_install()
{
    $service = array(
        'status' => 'A',
        'module' => YD_MODULE_NAME,
        'code' => 'yandex',
        'sp_file' => '',
        'description' => 'Yandex.Delivery',
    );

    $service['service_id'] = db_get_field('SELECT service_id FROM ?:shipping_services WHERE module = ?s AND code = ?s', $service['module'], $service['code']);

    if (empty($service['service_id'])) {
        $service['service_id'] = db_query('INSERT INTO ?:shipping_services ?e', $service);
    }

    $languages = Languages::getAll();
    foreach ($languages as $lang_code => $lang_data) {

        if ($lang_code == 'ru') {
            $service['description'] = "Яндекс.Доставка";
        } else {
            $service['description'] = "Yandex.Delivery";
        }

        $service['lang_code'] = $lang_code;

        db_query('INSERT INTO ?:shipping_service_descriptions ?e', $service);
    }
}

function fn_yandex_delivery_uninstall()
{
    $service_ids = db_get_fields('SELECT service_id FROM ?:shipping_services WHERE module = ?s', YD_MODULE_NAME);
    if (!empty($service_ids)) {
        db_query('DELETE FROM ?:shipping_services WHERE service_id IN (?a)', $service_ids);
        db_query('DELETE FROM ?:shipping_service_descriptions WHERE service_id IN (?a)', $service_ids);
    }
}

function fn_yandex_delivery_calculate_cart_taxes_pre(&$cart, $cart_products, &$product_groups)
{
    if (!empty($cart['shippings_extra']['data'])) {

        foreach ($product_groups as $group_key => $group) {
            if (!empty($group['chosen_shippings'])) {
                foreach ($group['chosen_shippings'] as $shipping_key => $shipping) {
                    if ($shipping['module'] != YD_MODULE_NAME) {
                        continue;
                    }

                    $shipping_id = $shipping['shipping_id'];

                    $point_id = 0;
                    // Save point info
                    if (isset($cart['shippings_extra']['data'][$group_key][$shipping_id]['courier_point_id'])) {
                        $point_id = $cart['shippings_extra']['data'][$group_key][$shipping_id]['courier_point_id'];

                    } elseif (isset($cart['shippings_extra']['data'][$group_key][$shipping_id]['pickup_point_id'])) {
                        $point_id = $cart['shippings_extra']['data'][$group_key][$shipping_id]['pickup_point_id'];
                    }

                    if (!empty($cart['shippings_extra']['data'][$group_key]['selected_shipping']['courier_data'])) {
                        $product_groups[$group_key]['chosen_shippings'][$shipping_key]['courier_data'] = $cart['shippings_extra']['data'][$group_key]['selected_shipping']['courier_data'];
                    }

                    if (!empty($cart['shippings_extra']['data'][$group_key]['selected_shipping']['pickup_data'])) {
                        $product_groups[$group_key]['chosen_shippings'][$shipping_key]['pickup_data'] = $cart['shippings_extra']['data'][$group_key]['selected_shipping']['pickup_data'];
                    }

                    $product_groups[$group_key]['chosen_shippings'][$shipping_key]['point_id'] = $point_id;
                    $product_groups[$group_key]['chosen_shippings'][$shipping_key]['delivery'] = $cart['shippings_extra']['data'][$group_key]['selected_shipping']['delivery'];
                }
            }
        }
    }
}

function fn_yandex_delivery_create_yandex_order($yandex_params)
{
    $yd = YandexDelivery::init($yandex_params['shipping_id']);
    $order_info = fn_get_order_info($yandex_params['order_id']);

    if (!empty($order_info['shipping'])) {

        $delivery = fn_yandex_delivery_build_delivery($yandex_params, $order_info);
        $order = fn_yandex_delivery_build_order($yandex_params, $order_info);
        $recipient = fn_yandex_delivery_build_recipient($yandex_params, $order_info);
        $delivery_point = fn_yandex_delivery_build_delivery_point($order_info);

        $yandex_order = $yd->createOrder($order, $recipient, $delivery, $delivery_point);

        $yandex_shipment = array();
        if (!empty($yandex_order['status']) && $yandex_order['status'] == 'DRAFT') {
            $yandex_data = array(
                'yandex_order_id' => $yandex_order['id'],
                'date' => date('Y-m-d', fn_date_to_timestamp($yandex_params['date']))
            );

            $yandex_data['type'] = 'withdraw';
            if ($yandex_params['type'] == 'warehouse_import' || $yandex_params['type'] == 'delivery_import') {
                $yandex_data['type'] = 'import';
            }

            $yandex_shipment = $yd->confirmSenderOrders($yandex_data);

            $result = new OperationResult();
            $result->setSuccess(true);
            if (!empty($yandex_shipment['error'])) {
                foreach ($yandex_shipment['error'] as $message) {
                    if (is_array($message)) {
                        $result->setErrors($message);
                    }
                }

                $errors = $result->getErrors();

                if (!empty($errors)) {
                    $result->setSuccess(false);
                    $result->showNotifications();
                }
            }

            if ($result->isSuccess()) {
                fn_yandex_delivery_update_shipment($yandex_params, $yandex_order, $order_info);
            }

            fn_yandex_delivery_update_orders(array($yandex_order['id']));
        }

        return array($yandex_order, $yandex_shipment);
    }

    return false;
}

function fn_yandex_delivery_build_delivery($yandex_params, $order_info)
{
    $delivery = new Delivery();
    $shipping = reset($order_info['shipping']);

    if (fn_yandex_delivery_check_type_delivery($shipping['service_params'])) {
        $delivery->pickuppoint = $shipping['courier_data']['delivery_id'];
        $delivery->delivery = $shipping['courier_data']['delivery_id'];
    } else {
        $delivery->pickuppoint = $shipping['pickup_data']['id'];
        $delivery->delivery = $shipping['pickup_data']['delivery_id'];
    }

    if (!empty($shipping['delivery'])) {
        $delivery->direction = $shipping['delivery']['direction'];
        $delivery->tariff = $shipping['delivery']['tariffId'];

        $interval = reset($shipping['delivery']['deliveryIntervals']);
        $delivery->interval = $interval['id'];

        $delivery->to_yd_warehouse = 0;
        if ($yandex_params['type'] == 'warehouse_import' || $yandex_params['type'] == 'warehouse_withdraw') {
            $delivery->to_yd_warehouse = 1;
        }
    }

    return $delivery;
}

function fn_yandex_delivery_build_order($yandex_params, $order_info)
{
    $order = new Order();

    $shipping = reset($order_info['shipping']);
    if (!empty($yandex_params['discount'])) {
        $yandex_params['amount_prepaid'] += $yandex_params['discount'];
    }

    $product_groups = reset($order_info['product_groups']);
    $package_size = YandexDelivery::getSizePackage($product_groups['package_info'], $shipping['service_params']);

    $order->num = $order_info['order_id'];
    $order->requisite = $yandex_params['requisite_id'];
    $order->warehouse = $yandex_params['warehouse_id'];
    $order->delivery_cost = $shipping['rate'];
    $order->assessed_value = !empty($yandex_params['assessed_value']) ? $yandex_params['assessed_value'] : 0;
    $order->amount_prepaid = !empty($yandex_params['amount_prepaid']) ? $yandex_params['amount_prepaid'] : 0;
    $order->comment = $yandex_params['comment'];

    $order->shipment_date = date('Y-m-d', fn_date_to_timestamp($yandex_params['date']));
    $order->shipment_type = (fn_yandex_delivery_check_type_delivery($shipping['service_params'])) ? YD_DELIVERY_COURIER : YD_DELIVERY_PICKUP;

    $weight = $product_groups['package_info']['W'] * Registry::get('settings.General.weight_symbol_grams') / 1000;
    $order->weight = sprintf('%.3f', $weight);
    $order->length = !empty($package_size['length']) ? $package_size['length'] : 10;
    $order->width = !empty($package_size['width']) ? $package_size['width'] : 10;
    $order->height = !empty($package_size['height']) ? $package_size['height'] : 10;

    $params = array(
        'shipment_id' => $yandex_params['shipment_id'],
        'advanced_info' => true
    );

    list($shipment_data,) = fn_get_shipments_info($params);
    $shipment_data = reset($shipment_data);

    foreach ($order_info['products'] as $item_id => $item) {
        if (isset($shipment_data['products'][$item_id])) {
            $order_info['products'][$item_id]['amount'] = $shipment_data['products'][$item_id];
        } else {
            $order_info['products'][$item_id]['amount'] = 0;
        }
    }

    foreach ($shipment_data['products'] as $product_code => $amount) {
        if (isset($order_info['products'][$product_code])) {
            $item = new OrderItem();

            $product_data = $order_info['products'][$product_code];

            $item->id = $product_data['product_id'];
            $item->article = $product_data['product_code'];
            $item->name = $product_data['product'];
            $item->quantity = $amount;
            $item->cost = $product_data['price'];

            $order->appendItem($item);
        }
    }

    return $order;
}

function fn_yandex_delivery_build_recipient($yandex_params, $order_info)
{
    $recipient = new Recipient();

    $recipient->first_name = !empty($order_info['s_firstname']) ? $order_info['s_firstname'] : $order_info['firstname'];
    $recipient->last_name = !empty($order_info['s_lastname']) ? $order_info['s_lastname'] : $order_info['lastname'];
    $recipient->middle_name = '-';
    $recipient->email = $order_info['email'];

    if (!empty($yandex_params['phone'])) {
        $recipient->phone = $yandex_params['phone'];
    } elseif (!empty($order_info['s_phone'])) {
        $recipient->phone = $order_info['s_phone'];
    } elseif (!empty($order_info['phone'])) {
        $recipient->phone = $order_info['phone'];
    }

    return $recipient;
}

function fn_yandex_delivery_build_delivery_point($order_info)
{
    $delivery_point = new DeliveryPoint();
    $shipping = reset($order_info['shipping']);

    if (!fn_yandex_delivery_check_type_delivery($shipping['service_params']) && $shipping['pickup_data']['type'] == 'PICKUPPOINT') {
        $address = $shipping['pickup_data']['address'];

        if (empty($shipping['pickup_data']['id'])) {
            $delivery_point->city = !empty($order_info['s_city']) ? $order_info['s_city'] : $order_info['city'];
            $delivery_point->street = $address['street'];
        }

    } else {
        if (!empty($order_info['s_address'])) {
            $address = $order_info['s_address'];
        } else {
            $address = $order_info['b_address'];
        }

        $delivery_point->city = !empty($order_info['s_city']) ? $order_info['s_city'] : $order_info['city'];
        $delivery_point->index = !empty($order_info['s_zipcode']) ? $order_info['s_zipcode'] : $order_info['zipcode'];
        $delivery_point->street = $address;
    }

    $delivery_point->house = '-';

    return $delivery_point;
}

function fn_yandex_delivery_create_shipment_post($shipment_data, $order_info, $group_key, $all_products, $shipment_id)
{
    $shipping_module = '';
    if (empty($shipment_data['carrier'])) {
        $shipping = reset($order_info['shipping']);
        $shipping_module = $shipping['module'];
    }

    if ($shipment_data['carrier'] == 'yandex' || $shipping_module == YD_MODULE_NAME) {
        $order = array(
            'shipment_id' => $shipment_id,
        );

        db_query('INSERT INTO ?:yd_orders ?e', $order);
    }
}

function fn_yndex_delivery_get_yandex_order_data($order_info, $shipments)
{
    $yandex_order_data = array();
    $assessed_value_shipments = array();
    $amount_prepaid_shipments = array();

    $yandex_shipment_exists = false;
    if (!empty($shipments)) {
        foreach ($shipments as $shipment) {
            if ($shipment['carrier'] == 'yandex') {
                $yandex_shipment_exists = true;
            } else {
                continue;
            }

            $shipment_id = $shipment['shipment_id'];

            $assessed_value_shipments[$shipment['shipment_id']] = 0;
            foreach ($shipment['products'] as $item_id => $amount) {
                if (isset($order_info['products'][$item_id])) {
                    $assessed_value_shipments[$shipment_id] += $order_info['products'][$item_id]['price'] * $amount;
                }
            }

            $amount_prepaid_shipments[$shipment_id] = $assessed_value_shipments[$shipment_id] + $order_info['shipping_cost'];
        }
    }

    if ($yandex_shipment_exists) {
        $product_group = reset($order_info['product_groups']);
        $shipping = reset($product_group['chosen_shippings']);

        $yd = YandexDelivery::init($shipping['shipping_id']);
        $yandex_order_data['senders'] = $yd->getSendersList();
        $yandex_order_data['warehouses'] = $yd->getWarehousesList();
        $yandex_order_data['requisites'] = $yd->getRequisiteList();

        $yandex_order_data['assessed_value'] = $assessed_value_shipments;
        $yandex_order_data['amount_prepaid'] = $amount_prepaid_shipments;
        $yandex_order_data['phone'] = !empty($order_info['s_phone']) ? $order_info['s_phone'] : $order_info['phone'];

        list($yandex_order_data['orders'], ) = fn_yandex_delivery_get_orders(array('order_id' => $order_info['order_id']));
    }

    return $yandex_order_data;
}

function fn_yandex_delivery_add_shipment($order_info, $shipment_data, $shipment_id, $yandex_order_data)
{
    if (!empty($yandex_order_data)) {
        $statuses = fn_yandex_delivery_get_statuses();

        $order_status_id = 0;
        if (array_key_exists($yandex_order_data['status'], $statuses)) {
            $order_status_id = $statuses[$yandex_order_data['status']]['yd_status_id'];
        }

        $order = array(
            'shipment_id' => $shipment_id,
            'yandex_id' => $yandex_order_data['id'],
            'yandex_full_num' => $yandex_order_data['full_num'],
            'status' => $order_status_id,
        );

        db_query('INSERT INTO ?:yd_orders ?e', $order);
    }
}

function fn_yandex_delivery_update_shipment($yandex_params, $yandex_order_data, $order_info)
{
    if (!empty($yandex_order_data)) {
        $shipment_id = $yandex_params['shipment_id'];
        $statuses = fn_yandex_delivery_get_statuses();

        $order_status_id = 0;
        if (array_key_exists($yandex_order_data['status'], $statuses)) {
            $order_status_id = $statuses[$yandex_order_data['status']]['yd_status_id'];
        }

        $order = array(
            'yandex_id' => $yandex_order_data['id'],
            'yandex_full_num' => $yandex_order_data['full_num'],
            'status' => $order_status_id,
        );

        $yandex_order = db_get_array("SELECT * FROM ?:yd_orders WHERE shipment_id = ?i", $shipment_id);

        if (empty($yandex_order)) {
            $order['shipment_id'] = $shipment_id;

            db_query('INSERT INTO ?:yd_orders ?e', $order);
        } else {

            db_query('UPDATE ?:yd_orders SET ?u WHERE shipment_id = ?i', $order, $shipment_id);
        }

        db_query('UPDATE ?:shipments SET tracking_number = ?s, timestamp = ?i WHERE shipment_id = ?i', $yandex_order_data['full_num'], fn_date_to_timestamp($yandex_params['date']), $shipment_id);

        if (!empty($yandex_params['notify_user']) && $yandex_params['notify_user'] == 'Y') {

            list($shipments) = fn_get_shipments_info(array('shipment_id' => $shipment_id, 'advanced_info' => true));
            $shipment = reset($shipments);
            $shipment['timestamp'] = $shipment['shipment_timestamp'];

            $mailer = Tygh::$app['mailer'];
            $mailer->send(array(
                'to' => $order_info['email'],
                'from' => 'company_orders_department',
                'data' => array(
                    'shipment' => $shipment,
                    'order_info' => $order_info,
                ),
                'template_code' => 'shipment_products',
                'tpl' => 'shipments/shipment_products.tpl', // this parameter is obsolete and is used for back compatibility
                'company_id' => $order_info['company_id'],
            ), 'C', $order_info['lang_code']);
        }
    }
}

/**
 * Checks if there is at least one order that has a corresponding Yandex.Delivery order.
 *
 * @return bool True if at least one order exist
 */
function fn_yandex_delivery_check_orders()
{
    $shipment_id = db_get_field("SELECT shipment_id FROM ?:yd_orders LIMIT 1");

    return !empty($shipment_id);
}

function fn_yandex_delivery_get_shipments($params, &$fields_list, &$joins, &$condition, $group)
{
    if (isset($params['search_yandex_order'])) {
        $fields_list = array_merge($fields_list, array(
            '?:yd_orders.shipment_id',
            '?:yd_orders.yandex_id',
            '?:yd_orders.yandex_full_num',
            '?:yd_statuses.yd_status_code'
        ));

        $joins = array_merge($joins, array(
            'INNER JOIN ?:yd_orders ON ?:yd_orders.shipment_id = ?:shipments.shipment_id',
            'LEFT JOIN ?:yd_statuses ON ?:yd_orders.status = ?:yd_statuses.yd_status_id',
        ));

        if (!empty($params['yd_status'])) {
            $condition .= db_quote(' AND yd_status_code = ?s', $params['yd_status']);
        }

        if (!empty($params['yd_order_id'])) {
            $condition .= db_quote(' AND ?:yd_orders.yandex_full_num LIKE ?l', $params['yd_order_id'] . "%");
        }
    }
}

function fn_yandex_delivery_get_orders($params = array(), $items_per_page = 0)
{
    $params = LastView::instance()->update('shipments_yandex', $params);
    $params['search_yandex_order'] = true;

    list($shipments, $search) = fn_get_shipments_info($params, $items_per_page);

    $yd_order_statuses = fn_yandex_delivery_get_statuses();
    $ya_orders = array();
    foreach ($shipments as $order) {
        if (array_key_exists($order['yd_status_code'], $yd_order_statuses)) {
            $order['status_name'] = $yd_order_statuses[$order['yd_status_code']]['yd_status_name'];
            $ya_orders[$order['shipment_id']] = $order;
        }
    }

    return array($ya_orders, $search);
}

function fn_yandex_delivery_get_statuses()
{
    static $statuses = null;

    if (!isset($statuses)) {
        $statuses = db_get_hash_array('SELECT s.yd_status_id, s.yd_status_code, sd.yd_status_name, sd.yd_status_info'
            . ' FROM ?:yd_statuses as s LEFT JOIN ?:yd_status_descriptions as sd USING(yd_status_id)' , 'yd_status_code');
    }

    return $statuses;
}

function fn_yandex_delivery_get_public_status($status_id, $lang_code = CART_LANGUAGE)
{
    static $statuses = null;

    if (!isset($statuses)) {
        $statuses = db_get_hash_array('SELECT s.yd_status_id, sd.yd_status_info'
            . ' FROM ?:yd_statuses as s LEFT JOIN ?:yd_status_descriptions as sd USING(yd_status_id)'
            . ' WHERE lang_code = ?s', 'yd_status_id', $lang_code);
    }

    return isset($statuses[$status_id]) ? $statuses[$status_id]['yd_status_info'] : '';
}

function fn_yandex_delivery_get_status_by_id($status_id, $lang_code = CART_LANGUAGE)
{
    static $statuses = null;

    if (!isset($statuses)) {
        $statuses = db_get_hash_array('SELECT s.yd_status_id, sd.yd_status_name'
            . ' FROM ?:yd_statuses as s LEFT JOIN ?:yd_status_descriptions as sd USING(yd_status_id)'
            . ' WHERE lang_code = ?s', 'yd_status_id', $lang_code);
    }

    return isset($statuses[$status_id]) ? $statuses[$status_id]['yd_status_name'] : '';
}

function fn_yandex_delivery_delete_shipments($shipment_ids, $result)
{
    $yd_order_ids = db_get_fields('SELECT yandex_id FROM ?:yd_orders WHERE shipment_id IN (?n)', $shipment_ids);
    if (!empty($yd_order_ids)) {
        $yd = YandexDelivery::init();

        foreach ($yd_order_ids as $yandex_id) {
            if (empty($yandex_id)) {
                continue;
            }

            $yandex_order_data = $yd->getOrderInfo($yandex_id, true);
            if ($yandex_order_data['status'] != 'CANCELED') {
                $yd->deleteOrder($yandex_id);
            }
        }
    }

    db_query('DELETE FROM ?:yd_orders WHERE shipment_id IN (?n)', $shipment_ids);
    db_query('DELETE FROM ?:yd_order_statuses WHERE yandex_id IN (?n)', $yd_order_ids);
}

function fn_yandex_delivery_get_order_statuses($order_id, $group_by_shipment = true)
{
    fn_yandex_delivery_update_order_statuses($order_id);

    $fields = array(
        '?:yd_order_statuses.yandex_id',
        '?:yd_order_statuses.order_id',
        '?:yd_orders.shipment_id',
        '?:yd_order_statuses.status',
        '?:yd_order_statuses.timestamp',
        '?:yd_status_descriptions.yd_status_name',
        '?:yd_status_descriptions.yd_status_info',
    );

    $join = db_quote('INNER JOIN ?:yd_orders USING(yandex_id)');
    $join .= db_quote(' INNER JOIN ?:yd_status_descriptions ON ?:yd_order_statuses.status = ?:yd_status_descriptions.yd_status_id');

    $condition = ' AND shipment_id != 0';
    if (!empty($order_id)) {
        $condition .= db_quote(" AND order_id = ?i", $order_id);
    }

    $fields = implode(', ', $fields);
    if ($group_by_shipment) {
        $yandex_order_statuses = db_get_hash_array("SELECT $fields FROM ?:yd_order_statuses $join WHERE 1 $condition ORDER BY ?:yd_order_statuses.timestamp ASC", 'shipment_id');
    } else {
        $yandex_order_statuses = db_get_array("SELECT $fields FROM ?:yd_order_statuses $join WHERE 1 $condition ORDER BY ?:yd_order_statuses.timestamp DESC");
    }

    foreach ($yandex_order_statuses as &$order) {
        $order['time'] = fn_date_format($order['timestamp'], Registry::get('settings.Appearance.date_format'));
    }

    return $yandex_order_statuses;
}

function fn_yandex_delivery_update_orders($ids)
{
    $yd = YandexDelivery::init();
    $yd_order_statuses = fn_yandex_delivery_get_statuses();

    foreach ($ids as $id) {
        $yandex_order_data = $yd->getOrderInfo($id, true);

        if (!empty($yandex_order_data)) {
            $status = $yd_order_statuses[$yandex_order_data['status']];
            db_query('UPDATE ?:yd_orders SET status = ?i WHERE yandex_id = ?i', $status['yd_status_id'], $yandex_order_data['id']);
        }
    }
}

function fn_yandex_delivery_update_order_statuses($order_id)
{
    $shipments_ids = db_get_fields('SELECT shipment_id FROM ?:shipment_items WHERE order_id = ?i GROUP BY shipment_id', $order_id);
    foreach ($shipments_ids as $shipment_id) {
        fn_yandex_delivery_update_order_statuses_by_shipment($shipment_id, $order_id);
    }
}

function fn_yandex_delivery_update_order_statuses_by_shipment($shipment_id, $order_id = 0)
{
    $yd = YandexDelivery::init();
    $statuses = fn_yandex_delivery_get_statuses();

    $yandex_order_id = db_get_field('SELECT yandex_id FROM ?:yd_orders WHERE shipment_id = ?i', $shipment_id);

    if (!empty($yandex_order_id)) {
        $yandex_order_statuses = $yd->getSenderOrderStatuses($yandex_order_id, true);

        if (!empty($yandex_order_statuses)) {

            if (empty($order_id)) {
                $order_id = db_get_field('SELECT order_id FROM ?:shipment_items WHERE shipment_id = ?i', $shipment_id);
            }

            foreach ($yandex_order_statuses as &$order) {
                $dateTime = new DateTime($order['time']);

                $data = array(
                    'yandex_id' => $yandex_order_id,
                    'order_id' => $order_id,
                    'timestamp' => $dateTime->format('U')
                );

                if (isset($statuses[$order['uniform_status']])) {
                    $status_data = $statuses[$order['uniform_status']];
                    $data['status'] = $status_data['yd_status_id'];
                }

                db_query('INSERT INTO ?:yd_order_statuses ?e ON DUPLICATE KEY UPDATE status=status', $data);
            }

            $last_timestamp = db_get_field('SELECT MAX(`timestamp`) FROM ?:yd_order_statuses WHERE yandex_id = ?i', $yandex_order_id);
            $last_status_id = db_get_field('SELECT status FROM ?:yd_order_statuses WHERE yandex_id = ?i AND timestamp = ?i', $yandex_order_id, $last_timestamp);
            db_query('UPDATE ?:yd_orders SET status = ?i WHERE yandex_id = ?i', $last_status_id, $yandex_order_id);
        }
    }
}

/**
 * Returns shippings array of yandex delivery
 *
 * @return array Shippings of yandex delivery
 */
function fn_yandex_delivery_get_shippings()
{
    $joins = db_quote('INNER JOIN ?:shipping_services as ss ON s.service_id = ss.service_id');
    return db_get_hash_array("SELECT * FROM ?:shippings as s $joins WHERE module = ?s", 'shipping_id', YD_MODULE_NAME);
}

/**
 * Returns shippings ids of yandex delivery
 *
 * @return array Ids of shippings of yandex delivery
 */
function fn_yandex_delivery_get_shippings_ids()
{
    $shippings = fn_yandex_delivery_get_shippings();
    return array_keys($shippings);
}

/**
 * Hook for adding information about the shipping method
 *
 * @param array $order Order data
 * @param array $additional_data Addition information of order
 *
 * @return boolean true
 */
function fn_yandex_delivery_get_order_info(&$order, $additional_data)
{
    if (!empty($order['shipping'])) {
        if (is_array($order['shipping'])) {
            $shipping = reset($order['shipping']);
        } else {
            $shipping = $order['shipping'];
        }

        if ($shipping['module'] != YD_MODULE_NAME) {
            return true;
        }

        if (fn_yandex_delivery_check_type_delivery($shipping['service_params'])) {
            $courier_data = $shipping['courier_data'];

            $order['courier_data'] = array(
                'delivery_name' => $shipping['delivery']['delivery_name'],
                'name' => $courier_data['delivery_name'],
            );

        } else {
            $pickup_data = $shipping['pickup_data'];

            $order['pickup_data'] = array(
                'delivery_name' => $shipping['delivery']['delivery_name'],
                'name' => $pickup_data['name'],
                'full_address' => $pickup_data['full_address'],
                'city' => $pickup_data['location_name'],
            );

            $order['pickup_data'] = array_merge($order['pickup_data'], $pickup_data['address']);
        }
    }

    return true;
}

function fn_yandex_delivery_realtime_services_process_response_post($result, $shipping_key, $shipping, $rate)
{
    static $yandex_delivery = array();

    if ($shipping->_shipping_info['module'] == YD_MODULE_NAME && isset($rate['data'])) {
        $group_key = isset($shipping->_shipping_info['keys']['group_key']) ? $shipping->_shipping_info['keys']['group_key'] : 0;
        $shipping_id = isset($shipping->_shipping_info['keys']['shipping_id']) ? $shipping->_shipping_info['keys']['shipping_id'] : 0;
        $selected_point = $rate['data']['selected_point'];

        if (fn_yandex_delivery_check_type_delivery($shipping->_shipping_info['service_params'])) {
            $courier_points = $rate['data']['courier_points'];
            $yandex_delivery[$group_key][$shipping_id]['courier_points'] = $courier_points;
            $yandex_delivery[$group_key][$shipping_id]['courier_delivery'] = 'Y';

        } else {
            $pickup_points = $rate['data']['pickup_points'];
            $yandex_delivery[$group_key][$shipping_id]['pickup_points'] = $pickup_points;
            $yandex_delivery[$group_key][$shipping_id]['courier_delivery'] = 'N';
        }

        if (!empty(Tygh::$app['session']['cart']['chosen_shipping'][$group_key])) {
            $chosen_shipping = Tygh::$app['session']['cart']['chosen_shipping'][$group_key];
        } else {
            $chosen_shipping = $shipping->_shipping_info['shipping_id'];
        }

        $yandex_delivery[$group_key][$shipping_id]['selected_point'] = $selected_point;
        $yandex_delivery[$group_key][$shipping_id]['deliveries'] = $rate['data']['deliveries'];

        Tygh::$app['view']->assign('yandex_delivery', $yandex_delivery);

        if (isset($pickup_points[$selected_point])) {
            Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['pickup_point_id'] = $selected_point;
            $delivery_id = $pickup_points[$selected_point]['delivery_id'];
            $delivery = isset($rate['data']['deliveries'][$delivery_id]) ? $rate['data']['deliveries'][$delivery_id] : reset($rate['data']['deliveries']);

            if ($shipping->_shipping_info['shipping_id'] == $chosen_shipping) {
                Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key]['selected_shipping']['pickup_point_id'] = $selected_point;
                Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key]['selected_shipping']['pickup_data'] = $pickup_points[$selected_point];
                Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key]['selected_shipping']['delivery'] = $delivery;
            }

        } elseif (isset($courier_points[$selected_point])) {
            Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['courier_point_id'] = $selected_point;
            $delivery_id = $courier_points[$selected_point]['delivery_id'];
            $delivery = isset($rate['data']['deliveries'][$delivery_id]) ? $rate['data']['deliveries'][$delivery_id] : reset($rate['data']['deliveries']);

            if ($shipping->_shipping_info['shipping_id'] == $chosen_shipping) {
                Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key]['selected_shipping']['courier_point_id'] = $selected_point;
                Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key]['selected_shipping']['courier_data'] = $courier_points[$selected_point];
                Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key]['selected_shipping']['delivery'] = $delivery;
            }
        }
    }
}

/**
 * Checks the type_delivery parameter.
 *
 * @param array $service_params The settings of the shipping method in CS-Cart.
 *
 * @return void
 */
function fn_yandex_delivery_check_type_delivery($shipping_params)
{
    if (!isset($shipping_params['type_delivery'])) {
        return false;
    }

    if ($shipping_params['type_delivery'] == 'courier') {
        return true;
    }

    return false;
}
