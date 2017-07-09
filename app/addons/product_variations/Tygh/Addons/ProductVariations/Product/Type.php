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

/**
 * Provides methods for working with the schema of product types.
 *
 * @package Tygh\Addons\ProductVariations
 */
class Type
{
    /** @var array */
    protected $schema = array();

    /**
     * ProductType constructor.
     *
     * @param array $schema Schema
     */
    public function __construct(array $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Whether to field is available.
     *
     * @param string $field Product field
     *
     * @return bool
     */
    public function isFieldAvailable($field)
    {
        if (isset($this->schema['field_aliases'][$field])) {
            $field = $this->schema['field_aliases'][$field];
        }

        if (isset($this->schema['disable_fields']) && in_array($field, $this->schema['disable_fields'], true)) {
            return false;
        }

        return !isset($this->schema['fields']) || in_array($field, $this->schema['fields'], true);
    }

    /**
     * Whether to product tab is available.
     *
     * @param string $tab_id Product tab identifier
     *
     * @return bool
     */
    public function isTabAvailable($tab_id)
    {
        return !isset($this->schema['tabs']) || in_array($tab_id, $this->schema['tabs'], true);
    }

    /**
     * Whether to product field is mergeable.
     *
     * @param string $field Product field
     *
     * @return bool
     */
    public function isFieldMergeable($field)
    {
        return in_array($field, $this->schema['mergeable_fields'], true);
    }
}