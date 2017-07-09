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

/** @var string $mode */

if ($mode == 'update') {
    $tax_id = isset($_REQUEST['tax_id']) ? $_REQUEST['tax_id'] : 0;

    Tygh::$app['view']->assign('cash_register_taxes', fn_rus_online_cash_register_get_external_taxes());
    Tygh::$app['view']->assign('cash_register_tax_id', fn_rus_online_cash_register_get_tax_external_id($tax_id));

    //Compatibility with version <= 4.5.3
    if ($_SERVER['REQUEST_METHOD'] == 'POST'
        && $tax_id
        && version_compare(PRODUCT_VERSION, '4.5.3', '<=')
        && isset($_REQUEST['tax_data']['cash_register_tax_id'])
    ) {
        fn_rus_online_cash_register_set_tax_external_id($tax_id, $_REQUEST['tax_data']['cash_register_tax_id']);
    }
} elseif ($mode == 'add') {
    Tygh::$app['view']->assign('cash_register_taxes', fn_rus_online_cash_register_get_external_taxes());
}
