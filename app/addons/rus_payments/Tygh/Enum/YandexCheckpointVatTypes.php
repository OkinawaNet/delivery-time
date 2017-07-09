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

namespace Tygh\Enum;

/**
 * Class YandexCheckpointVatTypes contains IDs of product VAT types for Yandex.Checkpoint.
 *
 * @package Tygh\Enum
 */
class YandexCheckpointVatTypes
{
    // no VAT
    const VAT_NONE = 1;
    // 0% rate
    const VAT_0 = 2;
    // 10% rate
    const VAT_10 = 3;
    // 18% rate
    const VAT_18 = 4;
    // 10/100 rate
    const VAT_10_110 = 5;
    // 18/118 rate
    const VAT_18_118 = 6;

    /**
     * Provides IDs of possible VAT types.
     *
     * @return array Array of VAT types IDs
     */
    public static function getAll()
    {
        return array(
            self::VAT_NONE   => self::VAT_NONE,
            self::VAT_0      => self::VAT_0,
            self::VAT_10     => self::VAT_10,
            self::VAT_18     => self::VAT_18,
            self::VAT_10_110 => self::VAT_10_110,
            self::VAT_18_118 => self::VAT_18_118,
        );
    }

    /**
     * Provides IDs of VAT types that can be used when tax is not included into price.
     *
     * @return array Array of VAT types IDs
     */
    public static function getForPriceExcluded()
    {
        return array(
            self::VAT_NONE   => self::VAT_NONE,
            self::VAT_10_110 => self::VAT_10_110,
            self::VAT_18_118 => self::VAT_18_118,
        );
    }

    /**
     * Provides IDs of VAT types that can be used when tax is included into price.
     *
     * @return array Array of VAT types IDs
     */
    public static function getForPriceIncluded()
    {
        return array(
            self::VAT_NONE => self::VAT_NONE,
            self::VAT_0    => self::VAT_0,
            self::VAT_10   => self::VAT_10,
            self::VAT_18   => self::VAT_18,
        );
    }

    /**
     * Provides list of VAT types with descriptions.
     *
     * @param string $lang_code Two-letter language code
     *
     * @return array Array of VAT types, where the keys are VAT type IDs and values are respective descriptions
     */
    public static function getWithDescriptions($lang_code = CART_LANGUAGE)
    {
        static $statuses;

        if (!$statuses) {
            $statuses = array();
            foreach (self::getAll() as $type) {
                $statuses[$type] = __("addons.rus_payments.yandex_checkpoint.vat_type.{$type}", array(), $lang_code);
            }
        }

        return $statuses;
    }
}
