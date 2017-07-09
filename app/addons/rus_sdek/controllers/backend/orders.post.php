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
use Tygh\Shippings\RusSdek;
use Tygh\Mailer;
use Tygh\Shippings\Shippings;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$sdek_delivery = fn_get_schema('sdek', 'sdek_delivery', 'php', true);
$currency_sdek = fn_get_schema('sdek', 'currency_sdek', 'php', true);
$currencies = Registry::get('currencies');
$symbol_grams = Registry::get('settings.General.weight_symbol_grams');
$company_city = Registry::get('runtime.company_data.city');
$company_address = Registry::get('runtime.company_data.address');
$company_name = Registry::get('runtime.company_data.company');
$company_phone = Registry::get('runtime.company_data.phone');
$params = $_REQUEST;

$calendar_format = "d/m/Y";
if (Registry::get('settings.Appearance.calendar_date_format') == 'month_first') {
    $calendar_format = "m/d/Y";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($params['order_id'])) {
        $order_info = fn_get_order_info($params['order_id'], false, true, true, true);
        $default_currency = (!empty($order_info['secondary_currency'])) ? $order_info['secondary_currency'] : CART_PRIMARY_CURRENCY;
    } else {
        return array(CONTROLLER_STATUS_OK, 'orders.manage');
    }

    if ($mode == 'sdek_order_delivery') {
        if (empty($params['add_sdek_info'])) {
            return false;
        }

        foreach ($params['add_sdek_info'] as $shipment_id => $sdek_info) {
            list($_shipments, $search) = fn_get_shipments_info(array('order_id' => $params['order_id'], 'advanced_info' => true, 'shipment_id' => $shipment_id));

            $shipment = reset($_shipments);

            $params_shipping = array(
                'shipping_id' => $shipment['shipping_id'],
                'Date' => date("Y-m-d", $shipment['shipment_timestamp'])
            );

            $data_auth = RusSdek::dataAuth($params_shipping);

            if (empty($data_auth)) {
                continue;
            }

            $order_for_sdek = $sdek_info['Order'];

            $order_for_sdek['RecipientName'] = fn_sdek_get_name_customer($order_info);
            $order_for_sdek['Phone'] = fn_sdek_get_phone_customer($order_info);

            $data_auth = fn_sdek_get_data_auth($data_auth, $order_info['b_country'], $order_info['s_country'], $currency_sdek);

            if (!empty($data_auth['ForeignDelivery']) && $data_auth['ForeignDelivery']) {
                $order_for_sdek['SellerAddress'] = $company_city . ', ' . $company_address;
                $order_for_sdek['ShipperName'] = $company_name;
                $order_for_sdek['ShipperAddress'] = $order_for_sdek['SellerAddress'];
            }

            $sdek_products = array();
            $weight = 0;
            foreach ($shipment['products'] as $item_id => $amount) {
                list($sdek_products, $product_weight) = fn_sdek_get_product_data($sdek_products, $order_info['products'][$item_id], $order_info, $shipment_id, $amount, $symbol_grams);

                $product_weight = fn_sdek_check_weight($product_weight, $symbol_grams);

                $weight = $weight + ($product_weight * $amount);
            }

            $weight = fn_sdek_check_weight($weight, $symbol_grams);

            $order_for_sdek['SellerName'] = $company_name;

            $data_auth['Number'] = $params['order_id'] . '_' . $shipment_id;

            $data_auth['OrderCount'] = "1";

            $xml = RusSdek::arraySimpleXml('DeliveryRequest', $data_auth, 'open');

            $order_for_sdek['Number'] = $params['order_id'] . '_' . $shipment_id;
            $order_for_sdek['DateInvoice'] = date("Y-m-d", $shipment['shipment_timestamp']);
            $order_for_sdek['RecipientEmail'] = $order_info['email'];
            $order_for_sdek['DeliveryRecipientCost'] = (!empty($order_for_sdek['DeliveryRecipientCost'])) ? $order_for_sdek['DeliveryRecipientCost'] : "0.00";

            $recipient_cost = fn_sdek_get_price_by_currency($order_for_sdek['DeliveryRecipientCost'], $data_auth, $currencies, $default_currency);
            if (!empty($recipient_cost)) {
                $order_for_sdek['DeliveryRecipientCost'] = $recipient_cost;
            }

            $xml .= RusSdek::arraySimpleXml('Order', $order_for_sdek, 'open');

            if (!empty($sdek_info['Address'])) {
                $xml .= RusSdek::arraySimpleXml('Address', $sdek_info['Address']);
            }

            $sdek_barcode = (!empty($sdek_info['barcode'])) ? $sdek_info['barcode'] : "_";

            $weight_grams = $weight * $symbol_grams;

            $package_for_xml = array (
                'Number' => $shipment_id,
                'BarCode' => $sdek_barcode,
                'Weight' => $weight_grams
            );
            $xml .= RusSdek::arraySimpleXml('Package', $package_for_xml, 'open');

            foreach ($sdek_products as $product) {
                $product_for_xml = fn_sdek_get_data_product_xml($product, $sdek_info);

                $product_for_xml['Cost'] = fn_sdek_get_price_by_currency($product['price'], $data_auth, $currencies, $default_currency);
                $product_for_xml['Payment'] = fn_sdek_get_price_by_currency($product_for_xml['Payment'], $data_auth, $currencies, $default_currency);

                $product['weight'] = fn_sdek_check_weight($product['weight'], $symbol_grams);

                $product_for_xml['Weight'] = $product['weight'] * $symbol_grams;

                if (!empty($data_auth['ForeignDelivery']) && $data_auth['ForeignDelivery']) {
                    $product_for_xml['CostEx'] = $product_for_xml['Cost'];
                    $product_for_xml['PaymentEx'] = $product_for_xml['Payment'];
                }

                $xml .= RusSdek::arraySimpleXml('Item', $product_for_xml);
            }

            $xml .= '</Package>';

            if (!empty($sdek_info['Schedule']['TimeBeg']) && !empty($sdek_info['Schedule']['TimeEnd'])) {
                $xml .= '<Schedule>';

                if (!empty($sdek_info['Schedule']['DeliveryRecipientCost'])) {
                    $recipient_cost = $sdek_info['Schedule']['DeliveryRecipientCost'];
                    unset($sdek_info['Schedule']['DeliveryRecipientCost']);
                }
                $count_schedule = db_get_field("SELECT count(*) FROM ?:rus_sdek_call_recipient ");
                $sdek_info['Schedule']['ID'] = $count_schedule + 1;
                $sdek_info['Schedule']['Date'] = DateTime::createFromFormat($calendar_format, $sdek_info['Schedule']['Date'])->format('Y-m-d');
                $xml .= RusSdek::arraySimpleXml('Attempt', $sdek_info['Schedule']);

                $call_recipient = array(
                    'order_id' => $params['order_id'],
                    'shipment_id' => $shipment_id,
                    'timestamp' => TIME,
                    'shipment_date' => $sdek_info['Schedule']['Date'],
                    'timebag' => $sdek_info['Schedule']['TimeBeg'],
                    'timeend' => $sdek_info['Schedule']['TimeEnd'],
                    'recipient_name' => $sdek_info['Schedule']['RecipientName'],
                    'phone' => $sdek_info['Schedule']['Phone'],
                    'call_comment' => $sdek_info['Schedule']['Comment'],
                    'recipient_cost' => $recipient_cost,
                );

                if (!empty($sdek_delivery[$sdek_info['Order']['TariffTypeCode']]['terminals']) && $sdek_delivery[$sdek_info['Order']['TariffTypeCode']]['terminals'] == 'N') {
                    $call_recipient['address'] = $sdek_info['Address']['Street'];
                } else {
                    $call_recipient['pvz_code'] = $sdek_info['Address']['PvzCode'];
                }

                $xml .= '</Schedule>';
            }

            if (!empty($sdek_info['CallCourier']['Date']) && !empty($sdek_info['CallCourier']['TimeBeg']) && !empty($sdek_info['CallCourier']['TimeEnd'])) {
                $xml .= '<CallCourier>';

                $sdek_info['CallCourier']['Date'] = DateTime::createFromFormat($calendar_format, $sdek_info['CallCourier']['Date'])->format('Y-m-d');
                $sdek_info['CallCourier']['SendCityCode'] = $sdek_info['Order']['SendCityCode'];
                $sdek_info['CallCourier']['SendPhone'] = $company_phone;
                $sdek_info['CallCourier']['SenderName'] = $company_name;
                $xml .= RusSdek::arraySimpleXml('Call', $sdek_info['CallCourier'], 'open');

                $address_send = array(
                    'Street' => $company_address,
                    'House' => '-',
                    'Flat' => '-',
                );
                $xml .= RusSdek::arraySimpleXml('SendAddress', $address_send);

                $call_courier = array(
                    'order_id' => $params['order_id'],
                    'shipment_id' => $shipment_id,
                    'timestamp' => TIME,
                    'call_courier_date' => $sdek_info['CallCourier']['Date'],
                    'timebag' => $sdek_info['CallCourier']['TimeBeg'],
                    'timeend' => $sdek_info['CallCourier']['TimeEnd'],
                    'lunch_timebag' => $sdek_info['CallCourier']['LunchBeg'],
                    'lunch_timeend' => $sdek_info['CallCourier']['LunchEnd'],
                    'weight' => $weight_grams,
                    'comment_courier' => $sdek_info['CallCourier']['Comment'],
                );

                $xml .= '</Call>';
                $xml .= '</CallCourier>';
            }

            $xml .= '</Order>';
            $xml .= '</DeliveryRequest>';

            $response = RusSdek::xmlRequest('http://gw.edostavka.ru:11443/new_orders.php', $xml, $data_auth);

            $result = RusSdek::resultXml($response);

            if (empty($result['error'])) {

                $register_data = array(
                    'order_id' => $params['order_id'],
                    'shipment_id' => $shipment_id,
                    'dispatch_number' => $result['number'],
                    'data' => date("Y-m-d", $shipment['shipment_timestamp']),
                    'data_xml' => $xml,
                    'timestamp' => TIME,
                    'status' => 'S',
                    'tariff' => $sdek_info['Order']['TariffTypeCode'],
                    'file_sdek' => $shipment_id . '/' . $params['order_id'] . '.pdf',
                    'notes' => $sdek_info['Order']['Comment'],
                    'use_product' => 'N',
                    'use_imposed' => '',
                    'barcode' => '',
                    'cash_delivery' => '0.00',
                );

                if (!empty($result['number'])) {
                    db_query('UPDATE ?:shipments SET tracking_number = ?s WHERE shipment_id = ?i', $result['number'], $shipment_id);

                    if (!empty($params['notify_user']) && $params['notify_user'] == 'Y') {
                        //$order_info = fn_get_order_info($params['order_id'], false, true, true, true);
                        //fn_order_notification($order_info);
                        $mailer = Tygh::$app['mailer'];

                        $shipment = array(
                            'shipment_id' => $shipment_id,
                            'timestamp' => $shipment['shipment_timestamp'],
                            'shipping' => db_get_field('SELECT shipping FROM ?:shipping_descriptions WHERE shipping_id = ?i AND lang_code = ?s', $shipment['shipping_id'], $order_info['lang_code']),
                            'tracking_number' => $result['number'],
                            'carrier_info' => Shippings::getCarrierInfo($shipment['carrier'], $result['number']),
                            'comments' => $shipment['comments'],
                            'products' => $shipment['products'],
                        );

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

                if (!empty($sdek_delivery[$sdek_info['Order']['TariffTypeCode']]['terminals']) && ($sdek_delivery[$sdek_info['Order']['TariffTypeCode']]['terminals'] == 'N')) {
                    $register_data['address'] = $sdek_info['Address']['Street'];
                } else {
                    $register_data['address_pvz'] = $sdek_info['Address']['PvzCode'];
                }

                if (isset($sdek_info['use_product']) && $sdek_info['use_product'] != 'N') {
                    $register_data['use_product'] = 'Y';
                }

                if (isset($sdek_info['use_imposed']) && $sdek_info['use_imposed'] != 'N') {
                    $register_data['use_imposed'] = 'Y';
                }

                if (!empty($sdek_info['barcode'])) {
                    $register_data['barcode'] = $sdek_info['barcode'];
                }

                if (!empty($sdek_info['CashDelivery'])) {
                    $register_data['cash_delivery'] = $sdek_info['CashDelivery'];
                }

                $register_id = db_get_field('SELECT register_id FROM ?:rus_sdek_register WHERE order_id = ?i AND shipment_id = ?i ', $params['order_id'], $shipment_id);

                if (empty($register_id)) {
                    $register_id = db_query('INSERT INTO ?:rus_sdek_register ?e', $register_data);
                } else {
                    db_query('UPDATE ?:rus_sdek_register SET ?u WHERE register_id = ?i', $register_data, $register_id);
                }

                foreach ($sdek_products as $sdek_product) {
                    $sdek_product['register_id'] = $register_id;
                    db_query('INSERT INTO ?:rus_sdek_products ?e', $sdek_product);
                }

                if (!empty($call_recipient)) {
                    db_query('INSERT INTO ?:rus_sdek_call_recipient ?e', $call_recipient);
                }

                if (!empty($call_courier)) {
                    db_query('INSERT INTO ?:rus_sdek_call_courier ?e', $call_courier);
                }

                fn_sdek_get_ticket_order($data_auth, $params['order_id'], $shipment_id);
            }

            $date_status = RusSdek::orderStatusXml($data_auth, $params['order_id'], $shipment_id);

            RusSdek::addStatusOrders($date_status);
        }

    } elseif ($mode == 'sdek_order_status') {
        foreach ($params['add_sdek_info'] as $shipment_id => $sdek_info) {
            list($_shipments) = fn_get_shipments_info(array('order_id' => $params['order_id'], 'advanced_info' => true, 'shipment_id' => $shipment_id));
            $shipment = reset($_shipments);
            $params_shipping = array(
                'shipping_id' => $shipment['shipping_id'],
                'Date' => date("Y-m-d", $shipment['shipment_timestamp']),
            );
            $data_auth = RusSdek::dataAuth($params_shipping);
            if (empty($data_auth)) {
                continue;
            }
            $date_status = RusSdek::orderStatusXml($data_auth, $params['order_id'], $shipment_id);
            RusSdek::addStatusOrders($date_status);
        }

    } elseif ($mode == 'call_sdek') {
        foreach ($params['add_sdek_info'] as $shipment_id => $sdek_info) {
            if (!empty($sdek_info['Schedule']['Date']) || !empty($sdek_info['Schedule']['TimeBeg']) || !empty($sdek_info['Schedule']['TimeEnd'])) {
                list($_shipments) = fn_get_shipments_info(array('order_id' => $params['order_id'], 'advanced_info' => true, 'shipment_id' => $shipment_id));
                $shipment = reset($_shipments);
                $params_shipping = array(
                    'shipping_id' => $shipment['shipping_id'],
                    'Date' => date("Y-m-d", $shipment['shipment_timestamp']),
                );
                $data_auth = RusSdek::dataAuth($params_shipping);
                if (empty($data_auth)) {
                    continue;
                }

                $count_schedule = db_get_field("SELECT count(*) FROM ?:rus_sdek_call_recipient ");
                $sdek_info['Schedule']['ID'] = $count_schedule + 1;
                $sdek_info['Schedule']['Date'] = DateTime::createFromFormat($calendar_format, $sdek_info['Schedule']['Date'])->format('Y-m-d');
                $call_recipient = array(
                    'order_id' => $params['order_id'],
                    'shipment_id' => $shipment_id,
                    'timestamp' => TIME,
                    'shipment_date' => $sdek_info['Schedule']['Date'],
                    'timebag' => $sdek_info['Schedule']['TimeBeg'],
                    'timeend' => $sdek_info['Schedule']['TimeEnd'],
                    'recipient_name' => $sdek_info['Schedule']['RecipientName'],
                    'phone' => $sdek_info['Schedule']['Phone'],
                    'call_comment' => $sdek_info['Schedule']['Comment'],
                    'recipient_cost' => '0.00'
                );

                $data_auth['OrderCount'] = "1";
                $xml = RusSdek::arraySimpleXml('ScheduleRequest', $data_auth, 'open');

                $order_for_sdek['Number'] = $params['order_id'] . '_' . $shipment_id;
                $order_for_sdek['Date'] = date("Y-m-d", $shipment['shipment_timestamp']);
                $xml .= RusSdek::arraySimpleXml('Order', $order_for_sdek, 'open');

                $sdek_info['Schedule'] = array_diff($sdek_info['Schedule'], array('', '0.00'));
                $xml .= RusSdek::arraySimpleXml('Attempt', $sdek_info['Schedule']);

                if (!empty($sdek_info['Address'])) {
                    $xml .= RusSdek::arraySimpleXml('Address', $sdek_info['Address']);
                }

                $xml .= '</Order>';
                $xml .= '</ScheduleRequest>';

                $response = RusSdek::xmlRequest('http://gw.edostavka.ru:11443/new_schedule.php', $xml, $data_auth);

                $result = RusSdek::resultXml($response);

                if (empty($result['error'])) {
                    if (!empty($sdek_delivery[$sdek_info['Order']['TariffTypeCode']]['terminals']) && ($sdek_delivery[$sdek_info['Order']['TariffTypeCode']]['terminals'] == 'Y')) {
                        $call_recipient['pvz_code'] = $sdek_info['Address']['PvzCode'];
                    } else {
                        $call_recipient['address'] = $sdek_info['Address']['Street'];
                    }

                    if (!empty($sdek_info['Schedule']['DeliveryRecipientCost'])) {
                        $call_recipient['recipient_cost'] = $sdek_info['Schedule']['DeliveryRecipientCost'];
                    }

                    $call_recipient = array_diff($call_recipient, array('', '0.00'));
                    $call_id = db_get_field('SELECT call_id FROM ?:rus_sdek_call_recipient WHERE order_id = ?i AND shipment_id =?i ', $params['order_id'], $shipment_id);
                    if (!empty($call_id)) {
                        db_query('UPDATE ?:rus_sdek_call_recipient SET ?u WHERE order_id = ?i AND shipment_id =?i ', $call_recipient, $params['order_id'], $shipment_id);
                    } else {
                        db_query('INSERT INTO ?:rus_sdek_call_recipient ?e', $call_recipient);
                    }

                    fn_sdek_get_ticket_order($data_auth, $params['order_id'], $shipment_id);
                }

                $date_status = RusSdek::orderStatusXml($data_auth, $params['order_id'], $shipment_id);

                RusSdek::addStatusOrders($date_status);
            }

            if (!empty($sdek_info['CallCourier']['Date']) && !empty($sdek_info['CallCourier']['TimeBeg']) && !empty($sdek_info['CallCourier']['TimeEnd'])) {
                list($_shipments) = fn_get_shipments_info(array('order_id' => $params['order_id'], 'advanced_info' => true, 'shipment_id' => $shipment_id));
                $shipment = reset($_shipments);
                $params_shipping = array(
                    'shipping_id' => $shipment['shipping_id'],
                    'Date' => date("Y-m-d", $shipment['shipment_timestamp']),
                );
                $data_auth = RusSdek::dataAuth($params_shipping);
                if (empty($data_auth)) {
                    continue;
                }

                $total_weight = db_get_field("SELECT SUM(weight) FROM ?:rus_sdek_products WHERE order_id = ?i AND shipment_id = ?i", $params['order_id'], $shipment_id);
                if (!empty($total_weight) && $total_weight != 0) {
                    $total_weight = $total_weight;
                } else {
                    $total_weight = SDEK_DEFAULT_WEIGHT;
                }

                $sdek_info['CallCourier'] = array_diff($sdek_info['CallCourier'], array('', '0.00'));
                unset($data_auth['Number']);
                $data_auth['CallCount'] = "1";
                $xml = RusSdek::arraySimpleXml('CallCourier', $data_auth, 'open');

                $sdek_info['CallCourier']['Date'] = DateTime::createFromFormat($calendar_format, $sdek_info['CallCourier']['Date'])->format('Y-m-d');
                $sdek_info['CallCourier']['SendCityCode'] = $sdek_info['Order']['SendCityCode'];
                $sdek_info['CallCourier']['SendPhone'] = $company_phone;
                $sdek_info['CallCourier']['SenderName'] = $company_name;
                $sdek_info['CallCourier']['Weight'] = $total_weight * $symbol_grams;
                $xml .= RusSdek::arraySimpleXml('Call', $sdek_info['CallCourier'], 'open');

                $address_send = array(
                    'Street' => $company_address,
                    'House' => '-',
                    'Flat' => '-',
                );
                $xml .= RusSdek::arraySimpleXml('Address', $address_send);

                $xml .= '</Call>';
                $xml .= '</CallCourier>';

                $response = RusSdek::xmlRequest('http://gw.edostavka.ru:11443/call_courier.php', $xml, $data_auth);

                $result = RusSdek::resultXml($response);

                if (empty($result['error'])) {
                    $call_courier = array(
                        'order_id' => $params['order_id'],
                        'shipment_id' => $shipment_id,
                        'timestamp' => TIME,
                        'call_courier_date' => $sdek_info['CallCourier']['Date'],
                        'timebag' => $sdek_info['CallCourier']['TimeBeg'],
                        'timeend' => $sdek_info['CallCourier']['TimeEnd'],
                        'weight' => $total_weight,
                    );

                    $call_courier['lunch_timebag'] = !empty($sdek_info['CallCourier']['LunchBeg']) ? $sdek_info['CallCourier']['LunchBeg'] : '';
                    $call_courier['lunch_timeend'] = !empty($sdek_info['CallCourier']['LunchEnd']) ? $sdek_info['CallCourier']['LunchEnd'] : '';
                    $call_courier['comment_courier'] = !empty($sdek_info['CallCourier']['Comment']) ? $sdek_info['CallCourier']['Comment'] : '';

                    db_query('INSERT INTO ?:rus_sdek_call_courier ?e', $call_courier);
                }

                $date_status = RusSdek::orderStatusXml($data_auth, $params['order_id'], $shipment_id);

                RusSdek::addStatusOrders($date_status);
            }
        }
    }

    if ($mode == 'update_details') {
        $order_info = fn_get_order_info($params['order_id'], false, true, true);
        $force_notification = fn_get_notification_rules($params);

        if (!empty($force_notification['C']) && !empty($params['update_shipping'])) {
            foreach ($params['update_shipping'] as $shipping) {
                foreach ($shipping as $shipment_id => $shipment_data) {
                    if ($shipment_data['carrier'] == 'sdek' && !empty($shipment_id)) {
                        $d_shipment = db_get_row("SELECT * FROM ?:shipments WHERE shipment_id = ?i ", $shipment_id);
                        $products = db_get_hash_array("SELECT item_id, amount FROM ?:shipment_items WHERE order_id = ?i AND shipment_id = ?i ", 'item_id', $params['order_id'], $shipment_id);

                        foreach ($products as $item_id => $product) {
                            $shipment_data['products'][$item_id] = $product['amount'];
                        }

                        $shipment = array(
                            'shipment_id' => $shipment_id,
                            'timestamp' => $d_shipment['timestamp'],
                            'shipping' => db_get_field('SELECT shipping FROM ?:shipping_descriptions WHERE shipping_id = ?i AND lang_code = ?s', $d_shipment['shipping_id'], $order_info['lang_code']),
                            'tracking_number' => $shipment_data['tracking_number'],
                            'carrier' => $shipment_data['carrier'],
                            'comments' => $d_shipment['comments'],
                            'items' => $shipment_data['products'],
                        );

                        Mailer::sendMail(array(
                            'to' => $order_info['email'],
                            'from' => 'company_orders_department',
                            'data' => array(
                                'shipment' => $shipment,
                                'order_info' => $order_info,
                            ),
                            'tpl' => 'shipments/shipment_products.tpl',
                            'company_id' => $order_info['company_id'],
                        ), 'C', $order_info['lang_code']);
                    }
                }
            }
        }
    }

    $url = fn_url("orders.details&order_id=" . $params['order_id'], 'A', 'current');
    if (defined('AJAX_REQUEST') && !empty($url)) {
        Registry::get('ajax')->assign('force_redirection', $url);
        exit;
    }

    return array(CONTROLLER_STATUS_OK, $url);
}

if ($mode == 'details') {
    $params = $_REQUEST;
    $order_info = Tygh::$app['view']->getTemplateVars('order_info');

    $sdek_info = $sdek_pvz = false;
    if (!empty($order_info['shipping'])) {
        foreach ($order_info['shipping'] as $shipping) {
            if (($shipping['module'] == 'sdek') && !empty($shipping['office_id'])) {
                $sdek_pvz = $shipping['office_id'];
            }
        }
    }

    list($all_shipments) = fn_get_shipments_info(array('order_id' => $params['order_id'], 'advanced_info' => true));

    if (!empty($all_shipments)) {

        $sdek_shipments = $data_shipments = array();

        foreach ($all_shipments as $key => $_shipment) {
            if ($_shipment['carrier'] == 'sdek') {
                $sdek_shipments[] = $_shipment;
            }
        }

        if (!empty($sdek_shipments)) {

            $offices = array();
            $location['country'] = (!empty($order_info['s_country'])) ? $order_info['s_country'] : $order_info['b_country'];
            $location['state'] = (!empty($order_info['s_state'])) ? $order_info['s_state'] : $order_info['b_state'];
            $location['city'] = (!empty($order_info['s_city'])) ? $order_info['s_city'] : $order_info['b_city'];
            $rec_city_code = RusSdek::cityId($location);

            $lastname = "";
            if (!empty($order_info['lastname'])) {
                $lastname = $order_info['lastname'];

            } elseif (!empty($order_info['s_lastname'])) {
                $lastname = $order_info['s_lastname'];

            } elseif (!empty($order_info['b_lastname'])) {
                $lastname = $order_info['b_lastname'];
            }
            $firstname = "";
            if (!empty($order_info['firstname'])) {
                $firstname = $order_info['firstname'];

            } elseif (!empty($order_info['s_firstname'])) {
                $firstname = $order_info['s_firstname'];

            } elseif (!empty($order_info['b_firstname'])) {
                $firstname = $order_info['b_firstname'];
            }

            $fio = $lastname . ' ' . $firstname;

            $phone = "";
            if (!empty($order_info['phone'])) {
                $phone = $order_info['phone'];

            } elseif (!empty($order_info['s_phone'])) {
                $phone = $order_info['s_phone'];

            } elseif (!empty($order_info['b_phone'])) {
                $phone = $order_info['b_phone'];
            }

            $data_status = array();

            foreach ($sdek_shipments as $key => $shipment) {
                $data_sdek = db_get_row("SELECT * FROM ?:rus_sdek_register WHERE order_id = ?i and shipment_id = ?i", $shipment['order_id'], $shipment['shipment_id']);

                $data_shipping = fn_get_shipping_info($shipment['shipping_id'], DESCR_SL);

                $module = db_get_field("SELECT module FROM ?:shipping_services WHERE service_id = ?i", $data_shipping['service_id']);

                if (!empty($data_shipping['service_params']) && ($module == 'sdek')) {

                    if (!empty($data_sdek)) {
                        $cost = 0;

                        if (!empty($data_sdek['data_xml'])) {
                            $data_xml = simplexml_load_string($data_sdek['data_xml']);

                            if (!empty($data_xml->Order->attributes()->DeliveryRecipientCost)) {
                                $cost = strval($data_xml->Order->attributes()->DeliveryRecipientCost);
                            }
                        }

                        if (!empty($cost)) {
                            $data_sdek['delivery_cost'] = $cost;
                        }

                        $sdek_barcode = $shipment['shipping_id'];
                        if (!empty($data_xml->Order->Package->attributes()->BarCode)) {
                            $sdek_barcode = strval($data_xml->Order->Package->attributes()->BarCode);
                            $sdek_barcode = str_replace('_', '', $sdek_barcode);
                        }

                        $data_sdek['barcode'] = $sdek_barcode;
                        $data_sdek['comments'] = $data_sdek['notes'];
                        $data_sdek['tariff_id'] = $data_sdek['tariff'];

                        $data_shipments[$shipment['shipment_id']] = $data_sdek;

                        $data_status = db_get_row("SELECT status, timestamp FROM ?:rus_sdek_status WHERE order_id = ?i AND shipment_id = ?i ORDER BY timestamp DESC", $params['order_id'], $shipment['shipment_id']);

                    } else {

                        $cost = fn_sdek_calculate_cost_by_shipment($order_info, $data_shipping, $shipment);

                        $data_shipments[$shipment['shipment_id']] = array(
                            'order_id' => $shipment['order_id'],
                            'comments' => $shipment['comments'],
                            'delivery_cost' => $cost,
                            'tariff_id' => $data_shipping['service_params']['tariffid'],
                            'address_pvz' => $sdek_pvz,
                        );
                        $data_shipments[$shipment['shipment_id']]['address'] = (!empty($order_info['s_address'])) ? $order_info['s_address'] : $order_info['b_address'];
                    }

                    $data_shipments[$shipment['shipment_id']]['send_city_code'] = $data_shipping['service_params']['from_city_id'];
                    $data_shipments[$shipment['shipment_id']]['shipping'] = $shipment['shipping'];

                    $data_call_recipients = db_get_row("SELECT * FROM ?:rus_sdek_call_recipient WHERE order_id = ?i and shipment_id = ?i", $shipment['order_id'], $shipment['shipment_id']);

                    if (!empty($data_call_recipients)) {
                        $data_shipments[$shipment['shipment_id']]['new_schedules'] = $data_call_recipients;
                        $data_shipments[$shipment['shipment_id']]['new_schedules']['date'] = strtotime($data_call_recipients['shipment_date']);
                        $data_shipments[$shipment['shipment_id']]['address_pvz'] = $data_call_recipients['pvz_code'];
                        $data_shipments[$shipment['shipment_id']]['address'] = $data_call_recipients['address'];
                    } else {
                        $data_shipments[$shipment['shipment_id']]['new_schedules'] = array(
                            'recipient_name' => $fio,
                            'phone' => $phone,
                            'date' => TIME,
                            'recipient_cost' => '0.00',
                            'timebag' => '',
                            'timeend' => '',
                        );
                    }

                    $data_call_couriers = db_get_array("SELECT * FROM ?:rus_sdek_call_courier WHERE order_id = ?i and shipment_id = ?i ORDER BY timestamp desc", $shipment['order_id'], $shipment['shipment_id']);

                    $data_shipments[$shipment['shipment_id']]['call_couriers'][] = array(
                        'date' => TIME,
                    );

                    $data_call_courier = array();
                    if (!empty($data_call_couriers)) {
                        $data_call_couriers = reset($data_call_couriers);
                        $data_call_couriers['date'] = $data_call_couriers['timestamp'];
                        $data_shipments[$shipment['shipment_id']]['call_couriers'] = array_merge($data_shipments[$shipment['shipment_id']]['call_couriers'], $data_call_couriers);
                    }

                    if (!empty($sdek_delivery[$data_shipping['service_params']['tariffid']]['terminals']) && ($sdek_delivery[$data_shipping['service_params']['tariffid']]['terminals'] == 'Y')) {

                        $type_terminals = 'PVZ';
                        if (!empty($sdek_delivery[$data_shipping['service_params']['tariffid']]['postomat'])) {
                            $type_terminals = 'POSTOMAT';
                        }

                        if (!empty($rec_city_code)) {
                            $offices = RusSdek::pvzOffices(array('cityid' => $rec_city_code, 'type' => $type_terminals));
                        }

                        $data_shipments[$shipment['shipment_id']]['offices'] = $offices;
                    }
                }
            }

            if (!empty($data_shipments)) {
                Tygh::$app['view']->assign('data_shipments', $data_shipments);
                Tygh::$app['view']->assign('sdek_pvz', $sdek_pvz);
                Tygh::$app['view']->assign('rec_city_code', $rec_city_code);
                Tygh::$app['view']->assign('order_id', $params['order_id']);
                Tygh::$app['view']->assign('data_status', $data_status);
            }
        }
    }

} elseif ($mode == 'sdek_get_ticket') {

    $params = $_REQUEST;

    $file = $params['order_id'] . '.pdf';

    $path = fn_get_files_dir_path() . 'sdek/' . $params['shipment_id'] . '/';

    fn_get_file($path . $file);

    if (defined('AJAX_REQUEST') && !empty($url)) {
        Registry::get('ajax')->assign('force_redirection', $url);
        exit;
    }

    return array(CONTROLLER_STATUS_OK);
}

function fn_sdek_get_ticket_order($data_auth, $order_id, $chek_id)
{
    unset($data_auth['Number']);
    $xml = '            ' . RusSdek::arraySimpleXml('OrdersPrint', $data_auth, 'open');
    $order_sdek = array (
        'Number' => $order_id . '_' . $chek_id,
        'Date' => $data_auth['Date']
    );
    $xml .= '            ' . RusSdek::arraySimpleXml('Order', $order_sdek);
    $xml .= '            ' . '</OrdersPrint>';

    $response = RusSdek::xmlRequest('http://gw.edostavka.ru:11443/orders_print.php', $xml, $data_auth);

    $download_file_dir = fn_get_files_dir_path() . '/sdek' . '/' . $chek_id . '/';

    fn_rm($download_file_dir);
    fn_mkdir($download_file_dir);

    $name = $order_id . '.pdf';

    $download_file_path = $download_file_dir . $name;
    if (!fn_is_empty($response)) {
        fn_put_contents($download_file_path, $response);
    }
}
