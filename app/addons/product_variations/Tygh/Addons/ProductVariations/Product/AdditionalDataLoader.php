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

namespace Tygh\Addons\ProductVariations\Product;

use Tygh\Database\Connection;
use Tygh\Enum\ProductTracking;

/**
 * Contains the logic for combining the data of a parent product and the data of a product variation.
 *
 * @package Tygh\Addons\ProductVariations
 */
class AdditionalDataLoader
{
    /** @var Manager */
    private $product_manager;

    /** @var Connection */
    private $db;

    /** @var array */
    private $products = array();

    /** @var array */
    private $params = array();

    /** @var array */
    private $auth = array();

    /** @var string */
    private $language;

    /** @var array  */
    private $selected_variation_product_ids = array();

    /** @var array */
    private $available_variation_codes = array();

    /** @var array|null */
    private $variations;

    /** @var array */
    private $images = array();

    /** @var array */
    private $additional_images = array();

    /** @var bool */
    private $is_preview = false;

    /**
     * AdditionalDataLoader constructor.
     *
     * @param array         $products           List of products
     * @param array         $params             Parameters for retrieves additional data. See fn_gather_additional_products_data
     * @param array         $auth               Array of user authentication data (e.g. uid, usergroup_ids, etc.)
     * @param string        $cart_language      Cart language
     * @param Manager       $product_manager    Product manager instance
     * @param Connection    $db                 Database connection instance
     */
    public function __construct(array &$products, array $params, array $auth, $cart_language, Manager $product_manager, Connection $db)
    {
        $this->params = $params;
        $this->auth = $auth;
        $this->language = $cart_language;
        $this->product_manager = $product_manager;
        $this->db = $db;

        $parent_product_ids = array();

        foreach ($products as &$product) {
            if (!isset($product['product_type'])) {
                $product['product_type'] = null;
            }

            if ($product['product_type'] === Manager::PRODUCT_TYPE_CONFIGURABLE) {
                if (isset($product['detailed_params']['is_preview']) && $product['detailed_params']['is_preview']) {
                    $this->is_preview = true;
                }
                $parent_product_ids[] = $product['product_id'];

                /*
                 * Checking selected options from cart.
                 */
                if (empty($product['selected_options']) && !empty($product['product_options'])) {
                    foreach ($product['product_options'] as $option) {
                        if (!empty($option['value'])) {
                            $product['selected_options'][$option['option_id']] = $option['value'];
                        }
                    }
                }
            }
        }
        unset($product);

        if (!empty($parent_product_ids)) {
            $statuses = array('A');

            if ($this->is_preview) {
                $statuses[] = 'H';
            }
            $this->available_variation_codes = $this->getVariationCodesByProductIds($parent_product_ids, $statuses);
        }

        $this->products = $products;
    }

    /**
     * Sets the product options.
     * It determines the selected options and available combinations.
     *
     * @param array $product          Product data
     * @param array $product_options  Product options
     *
     * @return array
     */
    public function setOptions($product, $product_options)
    {
        if (empty($product_options) || $product['product_type'] !== Manager::PRODUCT_TYPE_CONFIGURABLE) {
            return $product;
        }

        $option_ids = $variation_ids = $variation_codes = array();
        $variant_combinations = array();

        $product['tracking'] = ProductTracking::TRACK_WITHOUT_OPTIONS;
        $product['selected_options'] = empty($product['selected_options']) ? array() : $product['selected_options'];
        $product['product_options'] = $product_options;
        $product['has_options'] = !empty($product_options);

        if (!empty($this->available_variation_codes[$product['product_id']])) {
            $variation_codes = $this->available_variation_codes[$product['product_id']];

            foreach ($variation_codes as $variation_id => $code) {
                $variation_ids[$code] = $variation_id;
                $combinations = &$variant_combinations;

                /*
                 * Important! selected_options have been sorted as product_options
                 */
                $selected_options = $this->product_manager->getSelectedOptionsByVariationCode($code, $product['product_options']);

                foreach ($selected_options as $option_id => $variant_id) {
                    if (!isset($combinations[$variant_id])) {
                        $combinations[$variant_id] = array();
                    }
                    $combinations = &$combinations[$variant_id];
                }
                unset($combinations);

                $option_ids = array_keys($selected_options);
            }
        }

        if (empty($product['selected_options']) && !empty($product['combination'])) {
            $product['selected_options'] = fn_get_product_options_by_combination($product['combination']);
        }

        $variant_selected_options = array();

        foreach ($product['product_options'] as &$option) {
            $option_id = $option['option_id'];
            $is_variation_option = in_array($option_id, $option_ids);
            $variant_id = isset($product['selected_options'][$option_id]) ? $product['selected_options'][$option_id] : 0;

            if ($is_variation_option) {
                foreach ($option['variants'] as $key => $variant) {
                    if (!isset($variant_combinations[$variant['variant_id']])) {
                        unset($option['variants'][$key]);
                    }
                }
            }

            if (empty($option['variants'])) {
                $option['disable'] = true;
                $variant_id = 0;
            } elseif (!isset($option['variants'][$variant_id])) {
                $variant = reset($option['variants']);
                $variant_id = $variant['variant_id'];
            }

            if ($is_variation_option) {
                $variant_combinations = isset($variant_combinations[$variant_id]) ? $variant_combinations[$variant_id] : array();
                $variant_selected_options[$option_id] = $variant_id;
            }

            if ($variant_id) {
                $product['selected_options'][$option_id] = $variant_id;
                $option['value'] = $variant_id;
            }
        }
        unset($option);

        if ($variant_selected_options) {
            $product['selected_variation_code'] = $this->product_manager->getVariationCode($product['product_id'], $variant_selected_options);
            $product['variation_product_id'] = $variation_ids[$product['selected_variation_code']];
        } else {
            $product['selected_variation_code'] = $product['variation_product_id'] = null;
        }

        $product['options_update'] = count($variation_codes);

        fn_apply_options_rules($product);

        $this->selected_variation_product_ids[] = $product['variation_product_id'];

        return $product;
    }

    /**
     * Loads the main data of a product.
     *
     * @param array $product Product data
     *
     * @return array
     */
    public function loadBaseData($product)
    {
        if (empty($product['variation_product_id']) || $product['product_type'] !== Manager::PRODUCT_TYPE_CONFIGURABLE) {
            return $product;
        }

        $product_variation = $this->getProductVariation($product['variation_product_id']);
        $product_type = $this->product_manager->getProductTypeInstance($product['product_type']);

        if ($product_variation) {
            foreach ($product as $field => $value) {
                if ($product_type->isFieldMergeable($field) && array_key_exists($field, $product_variation) && $field !== 'amount' /* FIXME */) {
                    $product[$field] = $product_variation[$field];
                }
            }

            if ($product_type->isFieldMergeable('prices')) {
                $product['base_price'] = $product['price'] = $product_variation['price'];

                if (isset($product['prices']) || (isset($product['detailed_params']['info_type']) && $product['detailed_params']['info_type'] === 'D')) {
                    fn_get_product_prices($product_variation['product_id'], $product_variation, $this->auth);
                    $product['prices'] = isset($product_variation['prices']) ? $product_variation['prices'] : array();
                }
            }

            if ($product_type->isFieldMergeable('tax_ids')) {
                if (is_array($product['tax_ids'])) {
                    $product['tax_ids'] = explode(',', $product_variation['tax_ids']);
                } else {
                    $product['tax_ids'] = $product_variation['tax_ids'];
                }
            }

            if ($product_type->isFieldMergeable('amount')) {
                $product['inventory_amount'] = $product_variation['amount'];
            }

            if ($product_type->isFieldMergeable('detailed_image')
                && !empty($this->images[$product['variation_product_id']])
            ) {
                $product['main_pair'] = reset($this->images[$product['variation_product_id']]);
            }

            if ($product_type->isFieldMergeable('detailed_image')
                && !empty($this->additional_images[$product['variation_product_id']])
            ) {
                $product['image_pairs'] = $this->additional_images[$product['variation_product_id']];
            }

            $selected_options = $product['selected_options'];

            if (isset($product['price']) && empty($product['modifiers_price'])) {
                $product['base_modifier'] = fn_apply_options_modifiers($selected_options, $product['base_price'], 'P', array(), array('product_data' => $product));
                $old_price = $product['price'];
                $product['price'] = fn_apply_options_modifiers($selected_options, $product['price'], 'P', array(), array('product_data' => $product));

                if (empty($product['original_price'])) {
                    $product['original_price'] = $old_price;
                }

                $product['original_price'] = fn_apply_options_modifiers($selected_options, $product['original_price'], 'P', array(), array('product_data' => $product));
                $product['modifiers_price'] = $product['price'] - $old_price;
            }

            if (!empty($product['list_price'])) {
                $product['list_price'] = fn_apply_options_modifiers($selected_options, $product['list_price'], 'P', array(), array('product_data' => $product));
            }

            if (!empty($product['prices']) && is_array($product['prices'])) {
                foreach ($product['prices'] as $key => $item) {
                    $product['prices'][$key]['price'] = fn_apply_options_modifiers($selected_options, $item['price'], 'P', array(), array('product_data' => $product));
                }
            }
        }

        return $product;
    }

    /**
     * Loads the features of a product.
     *
     * @param array $product Product data
     *
     * @return array
     */
    public function loadFeatures($product)
    {
        if (empty($product['variation_product_id']) || $product['product_type'] !== Manager::PRODUCT_TYPE_CONFIGURABLE) {
            return $product;
        }

        $product_type = $this->product_manager->getProductTypeInstance($product['product_type']);

        if ($product_type->isFieldMergeable('features')) {
            $product = fn_product_variations_merge_features($product, $this->params['features_display_on']);
        }

        return $product;
    }

    /**
     * Gets loader params.
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }


    /**
     * Gets product variation.
     *
     * @param int $product_variation_id
     *
     * @return array|null
     */
    protected function getProductVariation($product_variation_id)
    {
        if ($this->variations === null) {
            $this->preloadVariations();
        }

        return isset($this->variations[$product_variation_id]) ? $this->variations[$product_variation_id] : null;
    }

    /**
     * Retrieves available products variations.
     */
    protected function preloadVariations()
    {
        $product_type = $this->product_manager->getProductTypeInstance(Manager::PRODUCT_TYPE_CONFIGURABLE);

        list($product_variations) = fn_get_products(array(
            'area' => $this->is_preview ? 'A' : 'C',
            'status' => $this->is_preview ? array('A', 'H') : array('A'),
            'parent_product_id' => null,
            'product_type' => Manager::PRODUCT_TYPE_VARIATION,
            'pid' => $this->selected_variation_product_ids,
            'action' => $this->is_preview ? 'preview' : null
        ));

        if ($product_type->isFieldMergeable('detailed_image') && ($this->params['get_icon'] || $this->params['get_detailed'])) {
            $this->images = fn_get_image_pairs(
                $this->selected_variation_product_ids,
                'product',
                'M',
                $this->params['get_icon'],
                $this->params['get_detailed'],
                $this->language
            );
        }

        if ($product_type->isFieldMergeable('detailed_image')
            && ($this->params['get_additional'] || $this->params['get_icon'] || $this->params['get_detailed'])
        ) {
            $this->additional_images = fn_get_image_pairs(
                $this->selected_variation_product_ids,
                'product',
                'A',
                true,
                true,
                $this->language
            );
        }

        $this->variations = $product_variations;
    }

    /**
     * Gets variation codes by product identifiers.
     *
     * @param array $product_ids    List of product identifiers
     * @param array $statuses       List of product statuses
     *
     * @return array
     */
    protected function getVariationCodesByProductIds(array $product_ids, array $statuses = array('A'))
    {
        return $this->db->getMultiHash(
            'SELECT parent_product_id, variation_code, product_id FROM ?:products'
            . ' WHERE parent_product_id IN (?n) AND product_type = ?s AND status IN (?a)',
            array('parent_product_id', 'product_id', 'variation_code'),
            $product_ids, Manager::PRODUCT_TYPE_VARIATION, $statuses
        );
    }
}