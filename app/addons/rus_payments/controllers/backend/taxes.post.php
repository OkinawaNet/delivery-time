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

use Tygh\Enum\YandexCheckpointVatTypes as VatTypes;

/** @var string $mode */

if ($mode == 'add' || $mode == 'update') {
    Tygh::$app['view']->assign(array(
        'yandex_checkpoint_vat_types'                => VatTypes::getWithDescriptions(),
        'yandex_checkpoint_vat_types_price_included' => VatTypes::getForPriceIncluded(),
        'yandex_checkpoint_vat_types_price_excluded' => VatTypes::getForPriceExcluded(),
    ));
}
