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

/**
 * Provides methods for working with product variations.
 *
 * @package Tygh\Addons\ProductVariations
 */
class Manager
{
    /** Configurable product type */
    const PRODUCT_TYPE_CONFIGURABLE = 'C';

    /** Product type variation */
    const PRODUCT_TYPE_VARIATION = 'V';

    /** Simple product type */
    const PRODUCT_TYPE_SIMPLE = 'P';

    /** @var Connection */
    protected $db;

    /** @var array */
    protected $products_data_cache = array();

    /** @var array|null */
    protected $type_schemas;

    /** @var array */
    protected $types_cache = array();

    /** @var array */
    protected $variations_ids_cache = array();

    /**
     * Manager constructor.
     *
     * @param Connection $db Instance of database connection.
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Gets product field value.
     *
     * @param int       $product_id Product identifier
     * @param string    $field      Product db field
     * @param mixed     $default    Default values if product is undefined
     *
     * @return mixed
     */
    public function getProductFieldValue($product_id, $field, $default = null)
    {
        if (!array_key_exists($product_id, $this->products_data_cache)) {
            $this->products_data_cache[$product_id] = $this->db->getRow(
                'SELECT * FROM ?:products WHERE product_id = ?i',
                $product_id
            );
        }

        return isset($this->products_data_cache[$product_id][$field]) ? $this->products_data_cache[$product_id][$field] : $default;
    }

    /**
     * Gets product variation options.
     *
     * @param int $product_id Product identifier.
     *
     * @return array
     */
    public function getProductVariationOptionsValue($product_id)
    {
        $value = $this->getProductFieldValue($product_id, 'variation_options');
        $value = json_decode($value, true);

        if (!is_array($value)) {
            $value = array();
        }

        return $value;
    }

    /**
     * Gets product types schema.
     *
     * @return array
     */
    public function getProductTypeSchemas()
    {
        if ($this->type_schemas === null) {
            $this->type_schemas = fn_get_schema('product_variations', 'product_types');
        }

        return $this->type_schemas;
    }

    /**
     * Gets product type schema by product type.
     *
     * @param string $type Product type
     * @return array
     */
    public function getProductTypeSchema($type)
    {
        $schemas = $this->getProductTypeSchemas();

        return isset($schemas[$type]) ? $schemas[$type] : array();
    }

    /**
     * Gets product types name.
     *
     * @param array|null $types Filter by product types.
     *
     * @return array
     */
    public function getProductTypeNames(array $types = null)
    {
        $names = array();
        $schemas = $this->getProductTypeSchemas();

        foreach ($schemas as $type => $schema) {
            if ($types === null || in_array($type, $types, true)) {
                $names[$type] = $schema['name'];
            }
        }

        return $names;
    }

    /**
     * Gets list of creatable product types.
     *
     * @return array
     */
    public function getCreatableProductTypes()
    {
        $types = array();
        $schemas = $this->getProductTypeSchemas();

        foreach ($schemas as $type => $schema) {
            if (!empty($schema['creatable'])) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Checks if product type is defined.
     *
     * @param string $type Product type (P,S,C)
     *
     * @return bool
     */
    public function isProductTypeExists($type)
    {
        return $this->getProductTypeSchema($type) !== array();
    }

    /**
     * Gets product type instance.
     *
     * @param string $type Product type (P,S,C)
     *
     * @return Type
     */
    public function getProductTypeInstance($type)
    {
        if (!$this->isProductTypeExists($type)) {
            $type = self::PRODUCT_TYPE_SIMPLE;
        }

        if (!isset($this->types_cache[$type])) {
            $this->types_cache[$type] = new Type($this->getProductTypeSchema($type));
        }

        return $this->types_cache[$type];
    }

    /**
     * Gets product type instance by product identifier.
     *
     * @param int $product_id Product identifier
     *
     * @return Type
     */
    public function getProductTypeInstanceByProductId($product_id)
    {
        return $this->getProductTypeInstance($this->getProductFieldValue($product_id, 'product_type'));
    }

    /**
     * Generates variation code by product identifier and selected options.
     *
     * @param int   $product_id         Product identifier
     * @param array $selected_options   List of selected options as option_id => variant_id
     *
     * @return string
     */
    public function getVariationCode($product_id, $selected_options)
    {
        sort($selected_options);
        return $product_id . '_' . implode('_', $selected_options);
    }

    /**
     * Finds product variation by selected options.
     *
     * @param int   $product_id         Product identifier
     * @param array $selected_options   List of selected options as option_id => variant_id
     *
     * @return bool|int
     */
    public function getVariationId($product_id, array $selected_options)
    {
        if (empty($selected_options)) {
            return false;
        }

        asort($selected_options);
        $key = md5($product_id . @serialize($selected_options));

        if (array_key_exists($key, $this->variations_ids_cache)) {
            $this->variations_ids_cache[$key];
        }

        $variation_option_ids = $this->getProductVariationOptionsValue($product_id);

        foreach ($selected_options as $option_id => $variant_id) {
            if (!in_array($option_id, $variation_option_ids)) {
                unset($selected_options[$option_id]);
            }
        }

        $variation_code = $this->getVariationCode($product_id, $selected_options);
        $variation_id = (int) $this->db->getField('SELECT product_id FROM ?:products WHERE variation_code = ?s', $variation_code);

        $this->variations_ids_cache[$variation_code] = $variation_id;

        return $variation_id;
    }

    /**
     * Retrieves list of variant identifiers from variation code.
     *
     * @param string $code Variation code
     * @return array
     */
    public function getVariantIdsByVariationCode($code)
    {
        $variation_ids = explode('_', $code);
        array_shift($variation_ids);

        return $variation_ids;
    }

    /**
     * Gets selected options by variation code.
     *
     * @param string        $code               Variation code
     * @param array|null    $product_options    List of product options
     *
     * @return array
     */
    public function getSelectedOptionsByVariationCode($code, array $product_options = null)
    {
        $variation_ids = $this->getVariantIdsByVariationCode($code);
        $selected_options = array();

        if ($product_options === null) {
            $selected_options = $this->db->getSingleHash(
                'SELECT option_id, variant_id FROM ?:product_option_variants WHERE variant_id IN (?n)',
                array('option_id', 'variant_id'),
                $variation_ids
            );
        } else {
            foreach ($product_options as $option) {
                foreach ($variation_ids as $key => $variant_id) {
                    if (isset($option['variants'][$variant_id])) {
                        $selected_options[$option['option_id']] = $variant_id;
                        unset($variation_ids[$key]);
                        break;
                    }
                }
            }
        }

        return $selected_options;
    }
}