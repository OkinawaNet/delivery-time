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

$sdek_delivery = array(
    '7' => array(
        'code' => '7',
        'tariff' => 'Международный экспресс документы дверь-дверь',
        'terminals' => 'N'
    ),
    '8' => array(
        'code' => '8',
        'tariff' => 'Международный экспресс грузы дверь-дверь',
        'terminals' => 'N'
    ),
    '180' => array(
        'code' => '180',
        'tariff' => 'Международный экспресс грузы дверь-склад',
        'terminals' => 'Y'
    ),
    '183' => array(
        'code' => '183',
        'tariff' => 'Международный экспресс документы дверь-склад',
        'terminals' => 'Y'
    ),
    '184' => array(
        'code' => '184',
        'tariff' => 'Международный экономичный экспресс дверь-дверь',
        'terminals' => 'N'
    ),
    '185' => array(
        'code' => '185',
        'tariff' => 'Международный экономичный экспресс дверь-склад',
        'terminals' => 'Y'
    ),
    '136' => array(
        'code' => '136',
        'tariff' => 'Посылка склад-склад',
        'terminals' => 'Y'
    ),
    '137' => array(
        'code' => '137',
        'tariff' => 'Посылка склад-дверь',
        'terminals' => 'N'
    ),
    '138' => array(
        'code' => '138',
        'tariff' => 'Посылка дверь-склад',
        'terminals' => 'Y'
    ),
    '139' => array(
        'code' => '139',
        'tariff' => 'Посылка дверь-дверь',
        'terminals' => 'N'
    ),
    '233' => array(
        'code' => '233',
        'tariff' => 'Экономичная посылка склад-дверь',
        'terminals' => 'N'
    ),
    '234' => array(
        'code' => '234',
        'tariff' => 'Экономичная посылка склад-склад',
        'terminals' => 'Y'
    ),
    '291' => array(
        'code' => '291',
        'tariff' => 'CDEK Express склад-склад',
        'terminals' => 'Y'
    ),
    '293' => array(
        'code' => '293',
        'tariff' => 'CDEK Express дверь-дверь',
        'terminals' => 'N'
    ),
    '294' => array(
        'code' => '294',
        'tariff' => 'CDEK Express склад-дверь',
        'terminals' => 'N'
    ),
    '295' => array(
        'code' => '295',
        'tariff' => 'CDEK Express дверь-склад',
        'terminals' => 'Y'
    ),
    '301' => array(
        'code' => '301',
        'tariff' => 'Постомат InPost дверь-склад',
        'terminals' => 'Y',
        'postomat' => 'Y'
    ),
    '302' => array(
        'code' => '302',
        'tariff' => 'Постомат InPost склад-склад',
        'terminals' => 'Y',
        'postomat' => 'Y'
    )
);

return $sdek_delivery;
