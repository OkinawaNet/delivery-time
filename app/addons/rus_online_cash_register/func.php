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
use Tygh\Settings;
use Tygh\Addons\RusOnlineCashRegister\OrderData;
use Tygh\Addons\RusOnlineCashRegister\Receipt\Receipt;

/**
 * Gets extra settings.
 *
 * @return array
 */
function fn_rus_online_cash_register_get_settings()
{
    if (!Registry::isExist('rus_online_cash_register.extra_settings')) {
        $settings = json_decode(Registry::get('addons.rus_online_cash_register.extra'), true);

        if (!is_array($settings)) {
            $settings = array();
        }

        Registry::set('rus_online_cash_register.extra_settings', $settings, false);
    }

    return (array) Registry::get('rus_online_cash_register.extra_settings');
}

/**
 * Updates extra setting.
 *
 * @param string    $setting_name   Extra setting name
 * @param mixed     $value          Value
 */
function fn_rus_online_cash_register_update_setting($setting_name, $value)
{
    $settings = fn_rus_online_cash_register_get_settings();
    $settings[$setting_name] = $value;

    Registry::set(sprintf('rus_online_cash_register.extra_settings.%s', $setting_name), $value);
    Settings::instance()->updateValue('extra', json_encode($settings), 'rus_online_cash_register', false, false);
}

/**
 * Gets external payments.
 *
 * @return array
 */
function fn_rus_online_cash_register_get_external_payments()
{
    return fn_get_schema('rus_online_cash_register', 'payments');
}

/**
 * Gets payments external identifiers.
 *
 * @return array
 */
function fn_rus_online_cash_register_get_payments_external_ids()
{
    $settings = fn_rus_online_cash_register_get_settings();

    return isset($settings['payments_map']) ? $settings['payments_map'] : array();
}

/**
 * Sets payment external identifier.
 *
 * @param int  $payment_id  Local identifier
 * @param int  $external_id External identifier
 */
function fn_rus_online_cash_register_set_payment_external_id($payment_id, $external_id)
{
    $external_payments = fn_rus_online_cash_register_get_external_payments();
    $map = fn_rus_online_cash_register_get_payments_external_ids();

    if (isset($external_payments[$external_id])) {
        $map[$payment_id] = $external_id;
    } else {
        unset($map[$payment_id]);
    }

    fn_rus_online_cash_register_update_setting('payments_map', $map);
}

/**
 * Gets payment external identifier.
 *
 * @param int $payment_id Payment identifier
 *
 * @return int|null
 */
function fn_rus_online_cash_register_get_payment_external_id($payment_id)
{
    $map = fn_rus_online_cash_register_get_payments_external_ids();

    return isset($map[$payment_id]) ? $map[$payment_id] : null;
}

/**
 * Gets cash register taxes.
 *
 * @return array
 */
function fn_rus_online_cash_register_get_external_taxes()
{
    return fn_get_schema('rus_online_cash_register', 'tax');
}

/**
 * Gets taxes external identifiers map.
 *
 * @return array
 */
function fn_rus_online_cash_register_get_taxes_external_ids()
{
    $settings = fn_rus_online_cash_register_get_settings();

    return isset($settings['taxes_map']) ? $settings['taxes_map'] : array();
}

/**
 * Sets tax external identifier.
 *
 * @param int  $tax_id          Tax identifier
 * @param int  $external_id     External identifier
 */
function fn_rus_online_cash_register_set_tax_external_id($tax_id, $external_id)
{
    $external_taxes = fn_rus_online_cash_register_get_external_taxes();
    $map = fn_rus_online_cash_register_get_taxes_external_ids();

    if (isset($external_taxes[$external_id])) {
        $map[$tax_id] = $external_id;
    } else {
        unset($map[$tax_id]);
    }

    fn_rus_online_cash_register_update_setting('taxes_map', $map);
}

/**
 * Gets tax external identifier.
 *
 * @param int $tax_id   Tax identifier
 *
 * @return int|null
 */
function fn_rus_online_cash_register_get_tax_external_id($tax_id)
{
    $map = fn_rus_online_cash_register_get_taxes_external_ids();

    return isset($map[$tax_id]) ? $map[$tax_id] : null;
}

/**
 * Hook handler: after order status changed.
 *
 * @param string $status_to     Order status to
 * @param string $status_from   Order status from
 * @param array  $order_info    Order data
 */
function fn_rus_online_cash_register_change_order_status($status_to, $status_from, $order_info)
{
    if ($order_info['is_parent_order'] === 'Y') {
        return;
    }

    $statuses_paid = Registry::get('addons.rus_online_cash_register.statuses_paid');
    $statuses_refund = Registry::get('addons.rus_online_cash_register.statuses_refund');
    $payment_id = isset($order_info['payment_id']) ? $order_info['payment_id'] : 0;
    $cash_register_payment_id = fn_rus_online_cash_register_get_payment_external_id($payment_id);

    if ($cash_register_payment_id === null) {
        return;
    }

    /** @var \Tygh\Addons\RusOnlineCashRegister\Service $service */
    $service = Tygh::$app['addons.rus_online_cash_register.service'];

    /** @var \Tygh\Addons\RusOnlineCashRegister\OrderDataRepository $order_data_repository */
    $order_data_repository = Tygh::$app['addons.rus_online_cash_register.order_data_repository'];

    $order_data = $order_data_repository->findByOrderId($order_info['order_id']);

    if (!$order_data) {
        $order_data = OrderData::fromArray(array(
            'order_id' => $order_info['order_id'],
            'status' => OrderData::STATUS_NONE,
            'timestamp' => time()
        ));
    }

    if (
        isset($statuses_paid[$status_to])
        && !isset($statuses_paid[$status_from])
        && !$order_data->isStatusPaid()
    ) {
        $service->sendReceipt($service->getReceiptFromOrder($order_info, Receipt::TYPE_SELL));

        $order_data->setStatus(OrderData::STATUS_PAID);
        $order_data_repository->save($order_data);
    } elseif (
        isset($statuses_refund[$status_to])
        && !isset($statuses_refund[$status_from])
        && $order_data->isStatusPaid()
    ) {
        $service->sendReceipt($service->getReceiptFromOrder($order_info, Receipt::TYPE_SELL_REFUND));

        $order_data->setStatus(OrderData::STATUS_REFUND);
        $order_data_repository->save($order_data);
    }
}

/**
 * Hook handler: after payment updated.
 *
 * @param array $payment_data   Payment data
 * @param int   $payment_id     Payment identifier
 */
function fn_rus_online_cash_register_update_payment_post($payment_data, $payment_id)
{
    if (isset($payment_data['cash_register_payment_id'])) {
        fn_rus_online_cash_register_set_payment_external_id($payment_id, $payment_data['cash_register_payment_id']);
    }
}

/**
 * Hook handler: after tax updated.
 *
 * @param array     $tax_data   Tax data
 * @param int       $tax_id     Tax identifier
 * @param string    $lang_code  Language code
 */
function fn_rus_online_cash_register_update_tax_post($tax_data, $tax_id, $lang_code)
{
    if (isset($tax_data['cash_register_tax_id'])) {
        fn_rus_online_cash_register_set_tax_external_id($tax_id, $tax_data['cash_register_tax_id']);
    }
}

/**
 * Hook handler: after order deleted.
 *
 * @param int $order_id Order identifier
 */
function fn_rus_online_cash_register_delete_order($order_id)
{
    /** @var \Tygh\Addons\RusOnlineCashRegister\OrderDataRepository $order_data_repository */
    $order_data_repository = Tygh::$app['addons.rus_online_cash_register.order_data_repository'];
    $order_data_repository->removeById($order_id);

    /** @var \Tygh\Addons\RusOnlineCashRegister\ReceiptRepository $receipt_repository */
    $receipt_repository = Tygh::$app['addons.rus_online_cash_register.receipt_repository'];
    $receipt_repository->removeByObject('order', $order_id);
}