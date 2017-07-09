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
 * 'copyright.txt' FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

if (!defined('BOOTSTRAP')) { die('Access denied'); }

include_once __DIR__ . '/products.functions.php';

if (!isset($schema['pre_export_process'])) {
    $schema['pre_export_process'] = array();
}

if (!isset($schema['export_pre_moderation'])) {
    $schema['export_pre_moderation'] = array();
}

if (!isset($schema['export_processing'])) {
    $schema['export_processing'] = array();
}

$schema['export_fields']['Product type'] = array(
    'db_field' => 'product_type',
    'export_only' => true,
    'required' => true
);

$schema['export_fields']['Variation code'] = array(
    'db_field' => 'variation_code'
);

$schema['export_pre_moderation']['pre_moderation_by_product_type'] = array(
    'function' => 'fn_product_variations_exim_pre_moderation_by_product_type',
    'args' => array('$pattern'),
);

$schema['export_processing']['processing_by_product_type'] = array(
    'function' => 'fn_product_variations_exim_processing_by_product_type',
    'args' => array('$data', '$result', '$multi_lang', '$pattern'),
);

$schema['pre_export_process']['pre_processing_by_product_type'] = array(
    'function' => 'fn_product_variations_exim_pre_processing_by_product_type',
    'args' => array('$pattern', '$conditions', '$table_fields'),
);

if (isset($schema['export_fields']['SEO name'])) {
    $schema['export_fields']['SEO name']['process_put'] = array(
        'fn_product_variations_create_import_seo_name', '#key', 'p', '#this', '%Product name%', 0, '', '', '#lang_code', '%Store%', '#row'
    );
}

return $schema;