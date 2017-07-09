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

$schema = array(
    'none' => array(
        'name' => __('rus_online_cash_register.tax.none'),
    ),
    'vat0' => array(
        'name' => __('rus_online_cash_register.tax.vat0'),
        'is_tax_included' => true
    ),
    'vat10' => array(
        'name' => __('rus_online_cash_register.tax.vat10'),
        'is_tax_included' => true
    ),
    'vat18' => array(
        'name' => __('rus_online_cash_register.tax.vat18'),
        'is_tax_included' => true
    ),
    'vat110' => array(
        'name' => __('rus_online_cash_register.tax.vat110'),
        'is_tax_included' => false
    ),
    'vat118' => array(
        'name' => __('rus_online_cash_register.tax.vat118'),
        'is_tax_included' => false
    ),
);

return $schema;
