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

use Tygh\Addons\ProductVariations\Product\Manager as ProductManager;

return array(
    ProductManager::PRODUCT_TYPE_SIMPLE => array(
        'name' => __('product_variations.product_type.simple'),
        'creatable' => true, // Available to manually create product with this type
    ),
    ProductManager::PRODUCT_TYPE_CONFIGURABLE => array(
        'name' => __('product_variations.product_type.configurable'),
        'creatable' => true, // Available to manually create product with this type
        'mergeable_fields' => array(
            'product_code', 'list_price', 'prices', 'amount', 'tax_ids', 'detailed_image', 'subscribers', 'files',
            'weight', 'free_shipping', 'shipping_freight', 'shipping_params', 'features', 'min_qty', 'max_qty',
            'qty_step', 'list_qty_count', 'avail_since'
        ),
        'disable_fields' => array(
            'options_type', 'exceptions_type', 'amount'
        )
    ),
    ProductManager::PRODUCT_TYPE_VARIATION => array(
        'name' => __('product_variations.product_type.variation'),
        'tabs' => array('detailed', 'images', 'shippings', 'qty_discounts', 'files', 'subscribers', 'features'),
        'fields' => array(
            'product_id', 'product_type', 'product', 'product_code', 'list_price', 'prices', 'amount', 'tax_ids',
            'detailed_image', 'additional_images', 'subscribers', 'files', 'variation_code', 'status', 'timestamp',
            'lang_code', 'shippings', 'features', 'weight', 'shipping_freight', 'box_height', 'box_length', 'box_width',
            'min_items_in_box', 'max_items_in_box', 'min_qty', 'max_qty', 'qty_step', 'list_qty_count',
            'availability', 'avail_since'
        ),
        'field_aliases' => array(
            'detailed_id' => 'detailed_image',
            'image_id' => 'detailed_image',
            'price' => 'prices',
            'taxes' => 'tax_ids',
            'main_pair' => 'detailed_image',
        )
    )
);