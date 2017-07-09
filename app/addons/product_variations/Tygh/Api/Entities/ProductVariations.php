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

namespace Tygh\Api\Entities;


use Tygh\Api\Response;
use Tygh\Addons\ProductVariations\Product\Manager as ProductManager;

/**
 * Provides the REST API method for products variations.
 *
 * @package Tygh\Api\Entities
 */
class ProductVariations extends Products
{
    /** @inheritdoc */
    public function index($id = 0, $params = array())
    {
        if (empty($id)) {
            $params['product_type'] = array('V');
        }

        return parent::index($id, $params);
    }

    /** @inheritdoc */
    public function create($params)
    {
        $data = array();
        $valid_params = true;
        $status = Response::STATUS_BAD_REQUEST;
        $params['product_type'] = ProductManager::PRODUCT_TYPE_VARIATION;

        unset($params['product_id']);

        if (!isset($params['price'])) {
            $data['message'] = __('api_required_field', array(
                '[field]' => 'price'
            ));
            $valid_params = false;
        }

        if ($valid_params) {
            $this->prepareFeature($params);
            $this->prepareImages($params);
            $product_id = fn_update_product($params);

            if ($product_id) {
                $status = Response::STATUS_CREATED;
                $data = array(
                    'product_id' => $product_id,
                );
            }
        }

        return array(
            'status' => $status,
            'data' => $data
        );
    }
}