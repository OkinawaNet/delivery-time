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

namespace Tygh\Addons\Retailcrm\Ym\Offers;

use Tygh\Ym\Offers\Simple as BaseSimple;

/**
 * Class Simple
 *
 * @package Tygh\Addons\Retailcrm\Ym\Offers
 */
class Simple extends BaseSimple
{
    /**
     * @inheritdoc
     */
    public function gatherAdditional($product)
    {
        parent::gatherAdditional($product);

        $this->offer['attr'] = array_merge($this->offer['attr'], self::getRetailCrmOfferAttributes($product));

        return true;
    }

    /**
     * Gets offer attributes required for retailCRM.
     *
     * @param array         $product
     * @param array|null    $combination
     *
     * @return array
     */
    public static function getRetailCrmOfferAttributes($product, array $combination = null)
    {
        $offer_id_parts = array(
            $product['product_id']
        );

        if (!empty($combination['combination'])) {
            ksort($combination['combination']);

            foreach ($combination['combination'] as $option_id => $variant_id) {
                $offer_id_parts[] = $option_id;
                $offer_id_parts[] = $variant_id;
            }
        }

        $result = array(
            'id' => implode('_', $offer_id_parts),
            'productId' => $product['product_id'],
            'group_id' => $product['product_id'],
            'quantity' => $combination ? $combination['amount'] : $product['amount']
        );

        return $result;
    }
}
