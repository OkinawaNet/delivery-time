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

use Tygh\Enum\YandexCheckpointTaxedItems as Taxed;
use Tygh\Enum\YandexCheckpointVatTypes;
use Tygh\Http;
use Tygh\Registry;
use Tygh\Payments\Processors\YandexMoneyMWS\Client as MWSClient;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

/* HOOKS */

function fn_rus_payments_change_order_status(&$status_to, &$status_from, &$order_info, &$force_notification, &$order_statuses, &$place_order)
{
    $processor_data = fn_get_processor_data($order_info['payment_id']);
    $payment_info = $order_info['payment_info'];

    if (!empty($processor_data['processor_script']) && $processor_data['processor_script'] == 'yandex_money.php' && !empty($payment_info['yandex_postponed_payment'])) {

        try {

            $cert = $processor_data['processor_params']['certificate_filename'];

            $mws_client = new MWSClient();
            $mws_client->authenticate(array(
                'pkcs12_file' => Registry::get('config.dir.certificates') . $cert,
                'pass' => $processor_data['processor_params']['p12_password'],
                'is_test_mode' => $processor_data['processor_params']['mode'] == 'test',
            ));

            if ($status_to == $processor_data['processor_params']['confirmed_order_status']) {

                $mws_client->confirmPayment($payment_info['yandex_invoice_id'], $order_info['total']);

                $payment_info['yandex_confirmed_time'] = date('c');
                $payment_info['yandex_postponed_payment'] = false;

            } elseif ($status_to == $processor_data['processor_params']['canceled_order_status']) {

                $mws_client->cancelPayment($payment_info['yandex_invoice_id']);

                $payment_info['yandex_canceled_time'] = date('c');
                $payment_info['yandex_postponed_payment'] = false;
            }

            $payment_info['order_status'] = $status_to;

            fn_update_order_payment_info($order_info['order_id'], $payment_info);

            $order_info['payment_info'] = $payment_info;

        } catch (\Exception $e) {
            fn_set_notification('E', __('error'), __('addons.rus_payments.yandex_money_mws_operation_error'));
            return $status_to = $status_from;
        }
    }
}

/* \HOOKS */

function fn_rus_payments_install()
{
    $payments = fn_get_schema('rus_payments', 'processors', 'php', true);

    if (!empty($payments)) {
        foreach ($payments as $payment) {

            $processor_id = db_get_field("SELECT processor_id FROM ?:payment_processors WHERE admin_template = ?s", $payment['admin_template']);

            if (empty($processor_id)) {
                db_query('INSERT INTO ?:payment_processors ?e', $payment);
            } else {
                db_query("UPDATE ?:payment_processors SET ?u WHERE processor_id = ?i", $payment, $processor_id);
            }
        }
    }

    $statuses = fn_get_schema('rus_payments', 'statuses', 'php', true);

    if (!empty($statuses)) {
        foreach ($statuses as $status_name => $status_data) {
            $status = fn_update_status('', $status_data, $status_data['type']);
            fn_set_storage_data($status_name, $status);
        }
    }

    db_query(
        'ALTER TABLE ?:taxes'
        . ' ADD COLUMN yandex_checkpoint_vat_type INT(1) UNSIGNED NOT NULL DEFAULT ?i',
        YandexCheckpointVatTypes::VAT_NONE
    );
}

function fn_rus_payments_uninstall()
{
    $payments = fn_get_schema('rus_payments', 'processors');
    fn_rus_payments_disable_payments($payments, true);

    foreach ($payments as $payment) {
        db_query("DELETE FROM ?:payment_processors WHERE admin_template = ?s", $payment['admin_template']);
    }

    $statuses = fn_get_schema('rus_payments', 'statuses', 'php', true);
    if (!empty($statuses)) {
        foreach ($statuses as $status_name => $status_data) {
            fn_delete_status(fn_get_storage_data($status_name), 'O');
        }
    }

    db_query('ALTER TABLE ?:taxes DROP COLUMN yandex_checkpoint_vat_type');

}

function fn_rus_payments_disable_payments($payments, $drop_processor_id = false)
{
    $fields = '';
    if ($drop_processor_id) {
        $fields = 'processor_id = 0,';
    }

    foreach ($payments as $payment) {
        $processor_id = db_get_field("SELECT processor_id FROM ?:payment_processors WHERE admin_template = ?s", $payment['admin_template']);

        if (!empty($processor_id)) {
            db_query("UPDATE ?:payments SET $fields status = 'D' WHERE processor_id = ?i", $processor_id);
        }
    }
}

function fn_rus_pay_format_price($price, $payment_currency)
{
    $currencies = Registry::get('currencies');

    if (array_key_exists($payment_currency, $currencies)) {
        if ($currencies[$payment_currency]['is_primary'] != 'Y') {
            $price = fn_format_price($price / $currencies[$payment_currency]['coefficient']);
        }
    } else {
        return false;
    }

    return $price;
}

function fn_rus_pay_format_price_down($price, $payment_currency)
{
    $currencies = Registry::get('currencies');

    if (array_key_exists($payment_currency, $currencies)) {
          $price = fn_format_price($price * $currencies[$payment_currency]['coefficient']);
    } else {
        return false;
    }

    return $price;
}

function fn_rus_payments_normalize_phone($phone)
{
    $phone_normalize = '';

    if (!empty($phone)) {
        if (strpos('+', $phone) === false && $phone[0] == '8') {
            $phone[0] = '7';
        }

        $phone_normalize = str_replace(array(' ', '(', ')', '-'), '', $phone);
    }

    return $phone_normalize;
}

function fn_qr_generate($order_info, $delimenter = '|', $dir = "")
{
    $processor_params = $order_info['payment_method']['processor_params'];

    $format_block = 'ST' . '0001' . '2' . $delimenter;

    $required_block = array(
        'Name' => $processor_params['sbrf_recepient_name'],
        'PersonalAcc' => $processor_params['sbrf_settlement_account'],
        'BankName' => $processor_params['sbrf_bank'],
        'BIC' => $processor_params['sbrf_bik'],
        'CorrespAcc' => $processor_params['sbrf_cor_account'],
    );

    $required_block = fn_qr_array2string($required_block, $delimenter);

    $additional_block = array(
        'PayeeINN' => $processor_params['sbrf_inn'],
        'Sum' => $order_info['total'] * 100,
        'Purpose' => __('sbrf_order_payment') . ' №' . $order_info['order_id'],
        'LastName' => $order_info['b_lastname'],
        'FirstName' => $order_info['b_firstname'],
        'PayerAddress' => $order_info['b_city'],
        'Phone' => $order_info['b_phone'],
    );

    $additional_block = fn_qr_array2string($additional_block, $delimenter);

    $string = $format_block . $required_block . $additional_block;

    $string = substr($string, 0, -1);

    $resolution = $processor_params['sbrf_qr_resolution'];

    $data = array(
        'cht' => 'qr',
        'choe' => 'UTF-8',
        'chl' => $string,
        'chs' => $resolution . 'x' . $resolution,
        'chld' => 'M|4'
    );

    $url = 'https://chart.googleapis.com/chart';

    $response = Http::get($url, $data);

    if (!strpos($response, 'Error')) {

        fn_put_contents($dir . 'qr_code_' . $order_info['order_id'] . '.png', $response);
        $path = $dir . 'qr_code_' . $order_info['order_id'] . '.png';

    } else {
        $path = fn_get_contents(DIR_ROOT. '/images/no_image.png');
    }

    return $path;
}

function fn_qr_array2string($array, $del = '|', $eq = '=')
{
    if (is_array($array)) {

        $string = '';

        foreach ($array as $key => $value) {
            if (!empty($value)) {
                $string .= $key . $eq . $value . $del ;
            }
        }
    }

    return $string;
}

function fn_yandex_money_log_write($data, $file)
{
    $path = fn_get_files_dir_path();
    fn_mkdir($path);
    $file = fopen($path . $file, 'a');

    if (!empty($file)) {
        fputs($file, 'TIME: ' . date('Y-m-d H:i:s', time()) . "\n");
        fputs($file, fn_array2code_string($data) . "\n\n");
        fclose($file);
    }
}

function fn_rus_payments_get_order_info(&$order, $additional_data)
{
    if (!empty($order['payment_info']) && isset($order['payment_info']['yandex_payment_type'])) {

        if ($order['payment_info']['yandex_payment_type'] == 'pc') {
            $payment_type = 'yandex';

        } elseif ($order['payment_info']['yandex_payment_type'] == 'ac') {
            $payment_type = 'card';

        } elseif ($order['payment_info']['yandex_payment_type'] == 'gp') {
            $payment_type = 'terminal';

        } elseif ($order['payment_info']['yandex_payment_type'] == 'mc') {
            $payment_type = 'phone';

        } elseif ($order['payment_info']['yandex_payment_type'] == 'wm') {
            $payment_type = 'webmoney';

        } elseif ($order['payment_info']['yandex_payment_type'] == 'ab') {
            $payment_type = 'alfabank';

        } elseif ($order['payment_info']['yandex_payment_type'] == 'sb') {
            $payment_type = 'sberbank';

        } elseif ($order['payment_info']['yandex_payment_type'] == 'ma') {
            $payment_type = 'masterpass';

        } elseif ($order['payment_info']['yandex_payment_type'] == 'pb') {
            $payment_type = 'psbank';
        }

        if (isset($payment_type)) {
            $order['payment_info']['yandex_payment_type'] = __('yandex_payment_' . $payment_type);
        }
    }
}

function fn_rus_payments_account_fields($fields_account, $user_data)
{
    $account_params = array();
    $profile_fields = db_get_hash_array("SELECT field_id, field_name, field_type FROM ?:profile_fields", "field_id");

    foreach ($fields_account as $name_account => $field_account) {
        if (!empty($profile_fields[$field_account]['field_name'])) {
            $account_params[$name_account] = !empty($user_data[$profile_fields[$field_account]['field_name']]) ? $user_data[$profile_fields[$field_account]['field_name']] : '';

        } elseif (!empty($user_data['fields'][$field_account])) {
            $account_params[$name_account] = !empty($user_data['fields'][$field_account]) ? $user_data['fields'][$field_account] : '';

        } else {
            $account_params[$name_account] = '';
        }
    }

    return $account_params;
}

/**
 * Checks whether receipt should be sent for Yandex.Checkpoint.
 *
 * @param array $processor_data Payment processor information
 *
 * @return bool
 */
function fn_is_yandex_checkpoint_receipt_required($processor_data)
{
    return !empty($processor_data['processor_params']['send_receipt'])
        && $processor_data['processor_params']['send_receipt'] == 'Y';
}

/**
 * Provides VAT type for Yandex.Checkpoint receipt.
 *
 * @param array $item       Object the tax is calculated for:
 *                          array(
 *                              'type' => 'P' // see \Tygh\Enum\YandexCheckpointTaxedItems
 *                              'id'   => '0' // used for YandexCheckpointTaxedItems::PRODUCT: cart_id of the product
 *                          )
 * @param array $taxes_list Taxes of an order
 *
 * @return int `tax` field value for Yandex.Checkpoint receipt
 */
function fn_get_yandex_checkpoint_tax_type($item, $taxes_list)
{
    static $tax_types = array();

    $tax_type = YandexCheckpointVatTypes::VAT_NONE;

    foreach ($taxes_list as $tax_id => $tax_info) {
        switch($item['type']) {
            case Taxed::PRODUCT:
                if (
                    // calculate tax on subtotal
                    empty($tax_info['applies']['items'][Taxed::PRODUCT][$item['id']])
                    // calculate tax on unit price
                    && empty($tax_info['applies'][Taxed::PRODUCT . "_{$item['id']}"])
                ) {
                    continue 2;
                }
                break;
            case Taxed::SHIPPING:
                // calculate tax on subtotal
                if (empty($tax_info['applies'][Taxed::SHIPPING])) {
                    $is_found = false;
                    // calculate tax on unit price
                    foreach(array_keys($tax_info['applies']) as $applied_item) {
                        if (strpos($applied_item, Taxed::SHIPPING . '_') === 0) {
                            $is_found = true;
                            break;
                        }
                    }
                    if (!$is_found) {
                        continue 2;
                    }
                }
                break;
            case Taxed::SURCHARGE:
                // calculate tax on subtotal
                if (empty($tax_info['applies'][Taxed::SURCHARGE])) {
                    $is_found = false;
                    // calculate tax on unit price
                    foreach(array_keys($tax_info['applies']) as $applied_item) {
                        if (strpos($applied_item, Taxed::SURCHARGE . '_') === 0) {
                            $is_found = true;
                            break;
                        }
                    }
                    if (!$is_found) {
                        continue 2;
                    }
                }
                break;

        }

        if (!isset($tax_types[$tax_id])) {
            $tax_info = fn_get_tax($tax_id);

            if ($tax_info) {
                $tax_types[$tax_id] = (int) $tax_info['yandex_checkpoint_vat_type'];
            }
        }

        if (isset($tax_types[$tax_id])
            && $tax_types[$tax_id] !== YandexCheckpointVatTypes::VAT_NONE
        ) {
            $tax_type = $tax_types[$tax_id];
            break;
        }
    }

    return $tax_type;
}

/**
 * Provides price for Yandex.Checkpoint receipt.
 *
 * @param float  $price    Price of an item (product, shipping, surcharge)
 * @param string $currency Currency code
 *
 * @return array `price` field value for Yandex.Checkpoint receipt
 */
function fn_get_yandex_checkpoint_price($price = 0.00, $currency = 'RUB')
{
    return array(
        'amount'   => (float) fn_format_rate_value((float) $price, 'F', 2, '.', '', ''),
        'currency' => $currency
    );
}

/**
 * Provides item description for Yandex.Checkpoint receipt.
 *
 * @param string $text Item (product, shipping, surcharge) description
 *
 * @return string `text` field value for Yandex.Checkpoint receipt
 */
function fn_get_yandex_checkpoint_description($text = '')
{
    return fn_truncate_chars($text, 128, '');
}

/**
 * Manually includes taxes that are not included into price to order items' prices.
 *
 * @param array $order               Order info
 * @param bool  $apply_tax_remainder Whether the whole tax value has to be applied
 *
 * @return array Order info with taxes included into prices
 */
function fn_yandex_checkpoint_apply_taxes($order, $apply_tax_remainder = true)
{
    foreach ($order['taxes'] as $tax_id => $tax) {
        if ($tax['price_includes_tax'] == 'N') {
            if (isset($tax['applies']['items'])) {
                // calculate tax on subtotal
                $tax_remainder = $tax['tax_subtotal'];
                foreach($tax['applies']['items'] as $item_type => $items_list) {
                    switch($item_type) {
                        case Taxed::SHIPPING:
                            if (!empty($order['shipping_cost'])) {
                                $included_tax = $tax['applies'][Taxed::SHIPPING];
                                $order['shipping_cost'] += $included_tax;
                                $tax_remainder -= $included_tax;
                            }
                            break;
                        case Taxed::SURCHARGE:
                            if (!empty($order['payment_surcharge'])) {
                                $included_tax = $tax['applies'][Taxed::SURCHARGE];
                                $order['payment_surcharge'] += $included_tax;
                                $tax_remainder -= $included_tax;
                            }
                            break;
                        case Taxed::PRODUCT:
                            // cost of products that are affected by the tax
                            $taxed_products_cost = 0;
                            foreach (array_keys($items_list) as $cart_id) {
                                // skip missing products
                                if (!isset($order['products'][$cart_id])) {
                                    continue 2;
                                }
                                $taxed_products_cost += $order['products'][$cart_id]['price'] * $order['products'][$cart_id]['amount'];
                            }
                            // skip zero values
                            if (!$taxed_products_cost) {
                                continue;
                            }
                            foreach (array_keys($items_list) as $cart_id) {
                                $included_tax = $tax['applies'][Taxed::PRODUCT] * $order['products'][$cart_id]['price'] / $taxed_products_cost;
                                $order['products'][$cart_id]['price'] += $included_tax;
                                $tax_remainder -= $included_tax * $order['products'][$cart_id]['amount'];
                            }
                            break;
                    }
                }
                if ($tax_remainder && $apply_tax_remainder) {
                    foreach($order['products'] as $cart_id => $product) {
                        // apply remainder to the price of the first non-free product
                        if (empty($product['extra']['exclude_from_calculate'])) {
                            $order['products'][$cart_id]['price'] += $tax_remainder;
                            $tax_remainder = 0;
                            break;
                        }
                    }
                    // apply remainder to anything
                    if ($tax_remainder) {
                        if (!empty($order['shipping_cost'])) {
                            $order['shipping_cost'] += $tax_remainder;
                        }
                        if (!empty($order['payment_surcharge'])) {
                            $order['payment_surcharge'] += $tax_remainder;
                        }
                    }
                }
            } else {
                // calculate tax on unit price
                foreach($tax['applies'] as $item_type_and_id => $included_tax) {
                    list($item_type, $cart_id) = explode('_', $item_type_and_id, 2);
                    switch($item_type) {
                        case Taxed::SHIPPING:
                            // tax already included into shipping cost
                            break;
                        case Taxed::SURCHARGE:
                            $order['payment_surcharge'] += $included_tax;
                            break;
                        case Taxed::PRODUCT:
                            $order['products'][$cart_id]['price'] += $included_tax / $order['products'][$cart_id]['amount'];
                            break;
                    }
                }
            }
        }
    }

    return $order;
}

/**
 * Provides receipt for Yandex.Checkpoint.
 *
 * @param array  $order    Info of the order to build receipt for
 * @param string $currency Currency code
 * @param array  $extra    Receipt calculation parameters:
 *                         apply_taxes - Whether taxes must be included into items' prices,
 *                         apply_tax_remainder - Whether the whole tax value has to be included into items' prices
 *                         apply_discounts - Whether discounts must be excluded into items' prices,
 *                         apply_discounts_remainder - Whether the whole discount value has to be distributed between items' prices
 *
 * @return array|null Receipt data or null when not needed
 */
function fn_yandex_checkpoint_get_receipt($order, $currency, $extra = array())
{
    $extra = array_merge(array(
        'apply_taxes'               => true,
        'apply_tax_remainder'       => true,
        'apply_discounts'           => true,
        'apply_discounts_remainder' => true,
    ), $extra);

    $items = array();

    // apply taxes that are not included into price
    if (!empty($extra['apply_taxes'])) {
        $order = fn_yandex_checkpoint_apply_taxes($order, !empty($extra['apply_tax_remainder']));
    }

    // distribute discount between products
    if (!empty($extra['apply_discounts'])) {
        $order = fn_yandex_checkpoint_apply_discounts($order, !empty($extra['apply_discounts_remainder']));
    }

    foreach ($order['products'] as $cart_id => $product) {
        if (!empty($product['extra']['exclude_from_calculate'])) {
            continue;
        }
        $items[] = array(
            'quantity' => (int) $product['amount'],
            'price'    => fn_get_yandex_checkpoint_price($product['price'], $currency),
            'tax'      => fn_get_yandex_checkpoint_tax_type(array('type' => Taxed::PRODUCT, 'id' => $cart_id), $order['taxes']),
            'text'     => fn_get_yandex_checkpoint_description($product['product']),
        );
    }
    if (!empty($order['shipping_cost'])) {
        $items[] = array(
            'quantity' => 1,
            'price'    => fn_get_yandex_checkpoint_price($order['shipping_cost'], $currency),
            'tax'      => fn_get_yandex_checkpoint_tax_type(array('type' => Taxed::SHIPPING), $order['taxes']),
            'text'     => fn_get_yandex_checkpoint_description(__('shipping')),
        );
    }
    if (!empty($order['payment_surcharge'])) {
        $items[] = array(
            'quantity' => 1,
            'price'    => fn_get_yandex_checkpoint_price($order['payment_surcharge'], $currency),
            'tax'      => fn_get_yandex_checkpoint_tax_type(array('type' => Taxed::SURCHARGE), $order['taxes']),
            'text'     => fn_get_yandex_checkpoint_description(__('payment_surcharge')),
        );
    }
    if (Registry::get('addons.gift_certificates.status') == 'A'
        && !empty($order['gift_certificates'])
    ) {
        foreach ($order['gift_certificates'] as $cart_id => $certificate) {
            if ($certificate['amount'] > 0) {
                $items[] = array(
                    'quantity' => 1,
                    'price'    => fn_get_yandex_checkpoint_price($certificate['amount'], $currency),
                    'tax'      => YandexCheckpointVatTypes::VAT_NONE,
                    'text'     => __('gift_certificate'),
                );
            }
        }
    }

    /**
     * Executes after Yandex.Checkpoint receipt items are populated, allows to modify items in the receipt.
     *
     * @param array  $order    Info of the order to build receipt for
     * @param string $currency Currency code
     * @param array  $extra    Receipt calculation parameters
     * @param array  $items    Receipt items
     */
    fn_set_hook('yandex_checkpoint_get_receipt_after_items', $order, $currency, $extra, $items);

    $receipt = null;
    if ($items) {
        $receipt = array(
            'customerContact' => $order['email'],
            'items'           => $items,
        );
    }

    /**
     * Executes before returning Yandex.Checkpoint receipt, allows to modify receipt data.
     *
     * @param array      $order    Info of the order to build receipt for
     * @param string     $currency Currency code
     * @param array      $extra    Receipt calculation parameters
     * @param array|null $receipt  Receipt data or null when not needed
     */
    fn_set_hook('yandex_checkpoint_get_receipt_post', $order, $currency, $extra, $receipt);

    return $receipt;
}

/**
 * Determines whether a Yandex.Checkpoint refund is partial.
 *
 * @param array $refund_data Refund info (returned products, refunded shipping etc.)
 * @param array $order_info  Order info
 *
 * @return bool
 */
function fn_yandex_checkpoint_is_partial_refund($refund_data, $order_info)
{
    $is_partial_refund = isset($refund_data['refund_shipping']) && $refund_data['refund_shipping'] == 'N'
        || isset($refund_data['refund_surcharge']) && $refund_data['refund_surcharge'] == 'N';

    if (!$is_partial_refund) {
        foreach ($refund_data['products'] as $cart_id => $product) {
            if ($product['is_returned'] == 'N' || $product['amount'] != $order_info['products'][$cart_id]['amount']) {
                $is_partial_refund = true;
                break;
            }
        }
    }

    if (Registry::get('addons.gift_certificates.status') == 'A'
        && !$is_partial_refund && !empty($refund_data['certificates'])
    ) {
        foreach ($refund_data['certificates'] as $cart_id => $certificate) {
            if ($certificate['is_returned'] == 'N') {
                $is_partial_refund = true;
                break;
            }
        }
    }

    /**
     * Executes after determining whether a Yandex.Checkpoint refund is partial, allows to modify check results.
     *
     * @param array $refund_data       Refund info (returned products, refunded shipping etc.)
     * @param array $order_info        Order info
     * @param bool  $is_partial_refund Whether refund is partial
     */
    fn_set_hook('yandex_checkpoint_is_partial_refund_post', $refund_data, $order_info, $is_partial_refund);

    return $is_partial_refund;
}

/**
 * Builds content of an order that is partially refunded via Yandex.Checkpoint.
 *
 * @param array $refund_data Refund info (returned products, refunded shipping etc.)
 * @param array $order_info  Order info
 *
 * @return array Refunded order data
 */
function fn_yandex_checkpoint_build_refunded_order($refund_data, $order_info)
{
    // manually apply taxes and discounts on full order for correct prices
    $refunded_order_info = fn_yandex_checkpoint_apply_taxes($order_info);
    $refunded_order_info = fn_yandex_checkpoint_apply_discounts($refunded_order_info);

    foreach ($refund_data['products'] as $cart_id => $product) {
        if ($product['is_returned'] == 'N') {
            unset($refunded_order_info['products'][$cart_id]);
        } else {
            $refunded_order_info['products'][$cart_id]['amount'] = $product['amount'];
        }
    }

    if (isset($refund_data['refund_shipping']) && $refund_data['refund_shipping'] == 'N') {
        unset($refunded_order_info['shipping_cost']);
    }

    if (isset($refund_data['refund_surcharge']) && $refund_data['refund_surcharge'] == 'N') {
        unset($refunded_order_info['payment_surcharge']);
    }

    if (Registry::get('addons.gift_certificates.status') == 'A'
        && !empty($order_info['gift_certificates'])
        && isset($refund_data['certificates'])
    ) {
        foreach ($refund_data['certificates'] as $cart_id => $certificate) {
            if ($certificate['is_returned'] == 'N') {
                unset($refunded_order_info['gift_certificates'][$cart_id]);
            }
        }
    }

    /**
     * Executes after building order data to refund via Yandex.Checkpoint, allows to modify order content.
     *
     * @param array $refund_data         Refund info (returned products, refunded shipping etc.)
     * @param array $order_info          Order info
     * @param bool  $refunded_order_info Refunded order info
     */
    fn_set_hook('yandex_checkpoint_build_refunded_order_post', $refund_data, $order_info, $refunded_order_info);

    return $refunded_order_info;
}

/**
 * Distributes subtotal discount between order items (products, shipping, payment surcharge).
 *
 * @param array $order                    Order data
 * @param bool  $apply_discount_remainder Whether the whole discount value has to be distributed between items' prices
 *
 * @return array Order data with discounted prices
 */
function fn_yandex_checkpoint_apply_discounts($order, $apply_discount_remainder = true)
{
    $order_cost = 0;
    foreach ($order['products'] as $product) {
        $order_cost += $product['price'] * $product['amount'];
    }
    if (!empty($order['shipping_cost'])) {
        $order_cost += $order['shipping_cost'];
    }
    if (!empty($order['payment_surcharge'])) {
        $order_cost += $order['payment_surcharge'];
    }

    $discount = empty($order['subtotal_discount']) ? 0 : $order['subtotal_discount'];

    if (Registry::get('addons.gift_certificates.status') == 'A') {
        // GC are bought
        if (!empty($order['gift_certificates'])) {
            foreach($order['gift_certificates'] as $cart_id => $certificate) {
                $order_cost += $certificate['amount'];
            }
        }

        // GC are applied
        if (!empty($order['use_gift_certificates'])) {
            foreach($order['use_gift_certificates'] as $code => $certificate) {
                $discount += $certificate['amount'];
            }
        }
    }

    /**
     * Executes when distributing subtotal discont upon receipt calculation after the order price is calculated.
     * Allows to modify order content, cost and discount value.
     *
     * @param array $order                    Order info
     * @param bool  $apply_discount_remainder Whether the whole discount value has to be distributed into items' prices
     * @param float $order_cost               Cost of products, shipping and payment surcharge with taxes distributed
     * @param float $discount                 Subtotal discount value
     */
    fn_set_hook('yandex_checkpoint_apply_discounts', $order, $apply_discount_remainder, $order_cost, $discount);

    if (!$order_cost || !$discount) {
        return $order;
    }

    $discount_remainder = $discount;

    foreach ($order['products'] as $cart_id => $product) {
        $item_discount = $discount * $order['products'][$cart_id]['price'] / $order_cost;
        $order['products'][$cart_id]['price'] -= $item_discount;
        $discount_remainder -= $item_discount * $order['products'][$cart_id]['amount'];
    }

    if (!empty($order['shipping_cost'])) {
        $item_discount = $discount * $order['shipping_cost'] / $order_cost;
        $order['shipping_cost'] -= $item_discount;
        $discount_remainder -= $item_discount;
    }

    if (!empty($order['payment_surcharge'])) {
        $item_discount = $discount * $order['payment_surcharge'] / $order_cost;
        $order['payment_surcharge'] -= $item_discount;
        $discount_remainder -= $item_discount;
    }

    if (Registry::get('addons.gift_certificates.status') == 'A'
        && !empty($order['gift_certificates'])
    ) {
        foreach ($order['gift_certificates'] as $cart_id => $certificate) {
            $item_discount = $discount * $certificate['amount'] / $order_cost;
            $order['gift_certificates'][$cart_id]['amount'] -= $item_discount;
            $discount_remainder -= $item_discount;
        }
    }

    /**
     * Executes when distributing subtotal discont upon receipt calculation after the discount for products, shipping
     * and surcharge is distributed. Allows to distribute discount to other entities.
     *
     * @param array $order                    Order info
     * @param bool  $apply_discount_remainder Whether the whole discount value has to be distributed into items' prices
     * @param float $order_cost               Cost of products, shipping and payment surcharge with taxes distributed
     * @param float $discount                 Subtotal discount value
     * @param float $discount_remainder       Not distributed discount
     */
    fn_set_hook('yandex_checkpoint_apply_discounts_after_order', $order, $apply_discount_remainder, $order_cost, $discount, $discount_remainder);

    if ($discount_remainder && $apply_discount_remainder) {
        foreach($order['products'] as $cart_id => $product) {
            // apply remainder to the price of the first possible product
            if ($product['price'] > $discount_remainder) {
                $order['products'][$cart_id]['price'] -= $discount_remainder;
                $discount_remainder = 0;
                break;
            }
        }
        // apply remainder to anything
        if ($discount_remainder) {
            if (!empty($order['shipping_cost'])) {
                $order['shipping_cost'] -= $discount_remainder;
            }
            if (!empty($order['payment_surcharge'])) {
                $order['payment_surcharge'] -= $discount_remainder;
            }
        }
    }

    return $order;
}
