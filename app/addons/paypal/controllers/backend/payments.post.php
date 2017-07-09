<?php

use Tygh\Http;
use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

/**
 * @var string $mode
 * @var string $action
 * @var array $auth
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($mode == 'update' && ($action == 'paypal_signup_live' || $action == 'paypal_signup_test')) {

        $data = $_REQUEST['payment_data'];

        if (fn_allowed_for('ULTIMATE')) {
            $company_id = Registry::get('runtime.company_id') ?: $data['company_id'];
        } else {
            $company_id = 0;
        }

        if (empty($_REQUEST['payment_id'])) {
            $payment_id = max(array_keys(fn_get_payment_by_processor($data['processor_id'])));
            Tygh::$app['session'][PAYPAL_STORED_PAYMENT_ID_KEY] = $payment_id;
        } else {
            $payment_id = (int) $_REQUEST['payment_id'];
        }

        // disable payment to prevent usage until not configured
        db_query('UPDATE ?:payments SET status = ?s WHERE payment_id = ?i', 'D', $payment_id);

        $config_mode = ($action == 'paypal_signup_live') ? 'live' : 'test';

        $request_data = fn_paypal_build_signup_request($company_id, $auth['user_id'], $payment_id, $config_mode);

        fn_create_payment_form(
            fn_get_paypal_signup_server_url(),
            $request_data,
            '',
            false,
            'post',
            true,
            'form',
            __('addons.paypal.connecting_to_signup_server')
        );
    }
}

if ($mode == 'manage' && !empty($_REQUEST['paypal_signup_for'])) {
    $payment_id = $_REQUEST['paypal_signup_for'];

    $messages = fn_paypal_get_signup_messages($payment_id);

    if ($messages) {
        foreach ($messages as $msg) {
            fn_set_notification($msg['type'], '', $msg['text'], $msg['state']);
        }

        fn_paypal_remove_signup_messages($payment_id);
    }
}