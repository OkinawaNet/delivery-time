<?php


namespace Tygh\Addons\RusOnlineCashRegister\Tests\Unit;

use PHPUnit_Framework_TestCase;
use Tygh\Addons\RusOnlineCashRegister\Receipt\Receipt;
use Tygh\Addons\RusOnlineCashRegister\ReceiptFactory;

class ReceiptFactoryTest extends PHPUnit_Framework_TestCase
{
    public $runTestInSeparateProcess = true;
    public $backupGlobals = false;
    public $preserveGlobalState = false;

    /**
     * @param array $order
     * @param array $expected
     * @param string $tax_calculation_type
     * @param string $currency
     * @param bool $is_prices_with_taxes
     * @dataProvider dpCreateReceiptFromOrder
     */
    public function testCreateReceiptFromOrder(array $order, $expected, $tax_calculation_type, $currency, $is_prices_with_taxes = false)
    {
        define('TIME', 100000);

        $service = new ReceiptFactory(
            'RUB',
            $currency,
            'Osn',
            array(
                1 => 10,
                2 => 20,
                3 => 30,
            ),
            array(
                1 => 'vat10',
                2 => 'vat18',
                3 => 'vat110',
                6 => 'vat118',
            ),
            $tax_calculation_type,
            $is_prices_with_taxes,
            function ($price, $from, $to) {
                return $price * 10;
            }
        );

        $receipt = $service->createReceiptFromOrder($order);
        $this->assertEquals($expected, $receipt->toArray(), '');

        if ($currency !== 'RUB') {
            $this->assertEquals($order['total'] * 10, $receipt->getTotal(), '');
        } else {
            $this->assertEquals($order['total'], $receipt->getTotal(), '');
        }
    }

    public function dpCreateReceiptFromOrder()
    {
        return array(
            array(
                /**
                 * Order discount
                 * Tax by unit price, tax included = Y
                 * Payment surcharge
                 * Product discount
                 */
                array(
                    'order_id' => 100,
                    'total' => 666.09,
                    'subtotal_discount' => 87.45,
                    'payment_surcharge' => 10,
                    'email' => 'customer@example.com',
                    'phone' => '+79021114567',
                    'payment_id' => 2,
                    'payment_method' => array(
                        'payment_id' => 2,
                        'surcharge_title' => 'Surcharge'
                    ),
                    'products' => array(
                        1237327324 => array(
                            'item_id' => '1237327324',
                            'amount' => 1,
                            'price' => 120,
                            'original_price' => 120,
                            'product' => '16GB A Series Walkman Video MP3',
                            'subtotal' => 120,
                            'tax_value' => 18.31
                        ),
                        1706372553 => array(
                            'item_id' => '1706372553',
                            'amount' => 2,
                            'product' => 'X-Box One',
                            'price' => 302.77,
                            'original_price' => 340,
                            'subtotal' => 605.54,
                            'tax_value' => 92.38
                        )
                    ),
                    'taxes' => array(
                        6 => array(
                            'price_includes_tax' => 'Y',
                            'tax_subtotal' => 114.97,
                            'applies' => array(
                                'P_1237327324' => 18.31,
                                'P_1706372553' => 92.38,
                                'S_0_1' => 2.75,
                                'PS_2' => 1.53,
                            )
                        )
                    ),
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 18
                        )
                    ),
                    'secondary_currency' => 'RUB'
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 100,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79021114567',
                    'items' => array(
                        array(
                            /**
                             * item[0].order_discount = item[0].total / (item[0].total + item[1].total + item[2].total + item[3].total) * order.discount
                             * item[0].order_discount  = 120 / (120 + 340 * 2 - 74.46 + 18 + 10) * 87.45 = 13.93
                             * item[0].discount = item[0].discount + item[0].order_discount
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 120,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  18.31,
                            'discount' => 13.93
                        ),
                        array(
                            /**
                             * item[1].order_discount = item[1].total / (item[1].total + item[2].total + item[3].total) * (order.discount - item[0].order_discount)
                             * item[1].order_discount  = (340 * 2 - 74.46) / ((340 * 2 - 74.46) +  + 18 + 10) * (87.45 - 13.93) = 70.27
                             * item[1].discount = item[1].discount + item[1].order_discount
                             * item[1].discount = 74.46 + 70.27 = 144.73
                             */
                            'name' => 'X-Box One',
                            'price' => 340,
                            'quantity' => 2.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  92.38,
                            'discount' => 144.73
                        ),
                        array(
                            /**
                             * item[2].order_discount = item[2].total / (item[2].total + item[3].total) * (order.discount - item[0].order_discount - item[1].order_discount)
                             * item[2].order_discount  = 18 / (18 + 10) * (87.45 - 13.93 - 70.27) = 2.09
                             * item[2].discount = item[2].discount + item[2].order_discount
                             * item[2].discount = 0 + 2.09 = 2.09
                             */
                            'name' => 'Custom shipping method',
                            'price' => 18.0,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  2.75,
                            'discount' => 2.09
                        ),
                        array(
                            /**
                             * item[3].order_discount = item[3].total / (item[3].total) * (order.discount - item[0].order_discount - item[1].order_discount - item[2].order_discount)
                             * item[3].order_discount  = 10 / 10 * (87.45 - 13.93 - 70.27 - 2.09) = 1.16
                             * item[3].discount = item[3].discount + item[3].order_discount
                             * item[3].discount = 0 + 1.16 = 1.16
                             */
                            'name' => 'Surcharge',
                            'price' => 10,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  1.53,
                            'discount' => 1.16
                        ),
                    ),
                    'payments' => array(
                        array(
                            'type' => 20,
                            'sum' => 666.09
                        )
                    ),
                ),
                'unit_price', 'RUB'
            ),
            array(
                /**
                 * Order discount
                 * Tax by unit price, tax included = N, prices with tax = Y
                 * Payment surcharge
                 * Product discount
                 */
                array(
                    'order_id' => 100,
                    'total' => 801.73,
                    'subtotal_discount' => 87.45,
                    'payment_surcharge' => 11.8,
                    'email' => 'customer@example.com',
                    'phone' => '+79021114567',
                    'payment_id' => 2,
                    'payment_method' => array(
                        'payment_id' => 2,
                        'surcharge_title' => 'Surcharge'
                    ),
                    'products' => array(
                        1237327324 => array(
                            'item_id' => '1237327324',
                            'amount' => 1,
                            'price' => 120,
                            'original_price' => 120,
                            'product' => '16GB A Series Walkman Video MP3',
                            'subtotal' => 141.6,
                            'tax_value' => 21.6
                        ),
                        1706372553 => array(
                            'item_id' => '1706372553',
                            'amount' => 2,
                            'product' => 'X-Box One',
                            'price' => 302.77,
                            'original_price' => 340,
                            'subtotal' => 714.54,
                            'tax_value' => 109
                        )
                    ),
                    'taxes' => array(
                        6 => array(
                            'price_includes_tax' => 'N',
                            'tax_subtotal' => 135.64,
                            'applies' => array(
                                'P_1237327324' => 21.6,
                                'P_1706372553' => 109,
                                'S_0_1' => 3.24,
                                'PS_2' => 1.8,
                            )
                        )
                    ),
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 18
                        )
                    ),
                    'secondary_currency' => 'RUB'
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 100,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79021114567',
                    'items' => array(
                        array(
                            /**
                             * item[0].discount = original_price * amount - price * amount
                             *               = 120 * 1 - 120 * 1 = 0
                             * item[0].price = original_price + item[0].tax_sum / item[0].discount
                             *               = 120 + 21.6 / 1 = 141.6
                             * item[0].total = original_price * item[0].quantity + item[0].tax_sum - item[0].discount
                             *               = 120 * 1 + 21.6 - 0 = 141.6
                             * item[0].order_discount = item[0].total / (item[0].total + item[1].total + item[2].total + item[3].total) * order.discount
                             *               = 141.6 / (141.6 + 714.54 + 21.24 + 11.8) * 87.45 = 13.93
                             * item[0].discount = item[0].discount + item[0].order_discount
                             *               = 0 + 13.93 = 13.93
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 141.6,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  21.6,
                            'discount' => 13.93
                        ),
                        array(
                            /**
                             * item[1].discount = original_price * amount - price * amount
                             *               = 340 * 2 - 302.77 * 2 = 74.46
                             * item[1].price = original_price + item[1].tax_sum / item[1].discount
                             *               = 340 + 109 / 2 = 394.5
                             * item[1].total = original_price * item[1].quantity + item[1].tax_sum - item[1].discount
                             *               = 340 * 2 + 109 - 74.46 = 714.54
                             * item[1].order_discount = item[1].total / (item[1].total + item[2].total + item[3].total) * (order.discount - item[0].order_discount)
                             *               = 714.54 / (714.54 + 21.24 + 11.8) * (87.45 - 13.93) = 70.27
                             * item[1].discount = item[1].discount + item[1].order_discount
                             *               = 74.46 + 70.27 = 144.73
                             */
                            'name' => 'X-Box One',
                            'price' => 394.5,
                            'quantity' => 2.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 109,
                            'discount' => 144.73
                        ),
                        array(
                            /**
                             * item[2].discount = 0
                             * item[2].price = original_price + item[2].tax_sum
                             *               = 18 + 3.24 = 21.24
                             * item[2].total = original_price * item[2].quantity + item[2].tax_sum - item[2].discount
                             *               = 18 * 1 + 3.24 - 0 = 21.24
                             * item[2].order_discount = item[2].total / (item[2].total + item[3].total) * (order.discount - item[0].order_discount - item[1].order_discount)
                             *               = 21.24 / (21.24 + 11.8) * (87.45 - 13.93 - 70.27) = 2.09
                             * item[2].discount = item[2].discount + item[2].order_discount
                             *               = 0 + 2.09 = 2.09
                             */
                            'name' => 'Custom shipping method',
                            'price' => 21.24,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  3.24,
                            'discount' => 2.09
                        ),
                        array(
                            /**
                             * item[3].discount = 0
                             * item[3].price = 11.8
                             * item[3].total = 11.8
                             *
                             * item[3].order_discount = item[3].total / (item[3].total) * (order.discount - item[0].order_discount - item[1].order_discount - item[2].order_discount)
                             *               = 11.8 / 11.8 * (87.45 - 13.93 - 70.27 - 2.09) = 1.16
                             * item[3].discount = item[0].discount + item[0].order_discount
                             *               = 0 + 1.16 = 1.16
                             */
                            'name' => 'Surcharge',
                            'price' => 11.8,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  1.8,
                            'discount' => 1.16
                        ),
                    ),
                    'payments' => array(
                        array(
                            'type' => 20,
                            'sum' => 801.73
                        )
                    ),
                ),
                'unit_price', 'RUB', true
            ),
            array(
                /**
                 * Tax by subtotal, tax included = N
                 * Payment surcharge
                 * Gift certificate
                 */
                array(
                    'order_id' => 109,
                    'total' => 434.12,
                    'subtotal_discount' => 0,
                    'payment_surcharge' => 10,
                    'payment_id' => 2,
                    'email' => 'customer@example.com',
                    'phone' => '+79021114567',
                    'payment_method' => array(
                        'payment_id' => 2,
                        'surcharge_title' => 'Surcharge'
                    ),
                    'products' => array(
                        array(
                            'item_id' => 1706372553,
                            'product' => 'X-Box One',
                            'price' => 372.27,
                            'amount' => 1,
                            'original_price' => 372.27,
                        )
                    ),
                    'taxes' => array(
                        6 => array(
                            'price_includes_tax' => 'N',
                            'tax_subtotal' => 73.85,
                            'applies' => array(
                                'P' => 67.01,
                                'S' => 5.04,
                                'PS' => 1.8,
                                'items' => array(
                                    'S' => array(
                                        0 => array(
                                            1 => true
                                        )
                                    ),
                                    'P' => array(
                                        1706372553 => true,
                                    ),
                                    'PS' => array(
                                        2 => true
                                    )
                                )
                            )
                        )
                    ),
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 28,
                        )
                    ),
                    'use_gift_certificates' => array(
                        'GC-PP7D-JHU2-022U' => array(
                            'amount' => 50
                        )
                    )
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 109,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79021114567',
                    'items' => array(
                        array(
                            /**
                             * item[0].discount = item[0].total / (item[0].total + item[1].total + item[2].total) * order.discount
                             *                  = (372.27 + 67.01) / (372.27 + 67.01 + 28 + 5.04 + 10 + 1.8) * 50 = 45.37
                             */
                            'name' => 'X-Box One',
                            'price' => 372.27 + 67.01,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  67.01,
                            'discount' => 45.37
                        ),
                        array(
                            /**
                             * item[1].discount = item[1].total / (item[1].total + item[2].total) * (order.discount - item[0].discount)
                             *                  = (28 + 5.04) / (28 + 5.04 + 10 + 1.8) * (50 - 45.37) = 3.41
                             */
                            'name' => 'Custom shipping method',
                            'price' => 28 + 5.04,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  5.04,
                            'discount' => 3.41
                        ),
                        array(
                            /**
                             * item[2].discount = item[2].total / (item[2].total) * (order.discount - item[0].discount - item[1].discount)
                             *                  = (10 + 1.8) / (10 + 1.8) * (50 - 45.37 - 3.41) = 1.22
                             */
                            'name' => 'Surcharge',
                            'price' => 10 + 1.8,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' =>  1.8,
                            'discount' => 1.22
                        )
                    ),
                    'payments' => array(
                        array(
                            'type' => 20,
                            'sum' => 434.12
                        )
                    ),
                ),
                'subtotal', 'RUB'
            ),
            array(
                array(
                    'order_id' => 10,
                    'timestamp' => 1484736211,
                    'discount' => 0,
                    'total' => 1000.00,
                    'shipping_cost' => 28.00,
                    'payment_id' => 1,
                    'phone' => '+79073456797',
                    'email' => 'customer@example.com',
                    'secondary_currency' => 'RUB',
                    'products' => array(
                        1061624811 => array(
                            'item_id' => 1061624811,
                            'product_id' => 214,
                            'price' => 972.00,
                            'original_price' => 972.00,
                            'subtotal' => 972.00,
                            'product' => 'ASUS CP6130',
                            'discount' => 0,
                            'amount' => 1,
                            'tax_value' => 0
                        )
                    ),
                    'taxes' => array(
                        1 => array(
                            'rate_type' => 'P',
                            'rate_value' => 10.000,
                            'price_includes_tax' => 'Y',
                            'tax_subtotal' => 88.36,
                            'applies' => array(
                                'P' => 88.36,
                                'S' => 2.55,
                                'items' => array(
                                    'S' => array(
                                        0 => array(
                                            1 => 1
                                        )
                                    ),
                                    'P' => array(
                                        1061624811 => 1
                                    )
                                )
                            )
                        )
                    ),
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 28,
                        )
                    )
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 10,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'items' => array(
                        array(
                            'name' => 'ASUS CP6130',
                            'price' => 972.00,
                            'quantity' => 1.0,
                            'tax_type' => 'vat10',
                            'tax_sum' =>  88.36,
                            'discount' => 0
                        ),
                        array(
                            'name' => 'Custom shipping method',
                            'price' => 28.0,
                            'quantity' => 1.0,
                            'tax_type' => 'vat10',
                            'tax_sum' =>  2.55,
                            'discount' => 0
                        )
                    ),
                    'payments' => array(
                        array(
                            'type' => 10,
                            'sum' => 1000.00
                        )
                    ),
                ),
                'subtotal', 'RUB'
            ),
            array(
                /**
                 * Tax by subtotal, tax included = N
                 * Payment surcharge
                 * Order discount
                 */
                array(
                    'order_id' => 100,
                    'total' => 196.15,
                    'subtotal' => 130,
                    'timestamp' => 1484736211,
                    'subtotal_discount' => 13,
                    'payment_surcharge' => 15.95,
                    'shipping_cost' => 28.00,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'payment_id' => 2,
                    'payment_method' => array(
                        'payment_id' => 2,
                        'surcharge_title' => 'surcharge_title'
                    ),
                    'products' => array(
                        '1237327324' => array(
                            'item_id' => 1237327324,
                            'price' => 130.0,
                            'original_price' => 130.0,
                            'subtotal' => 130.0,
                            'discount' => 0,
                            'amount' => 1,
                            'product' => '16GB A Series Walkman Video MP3'
                        )
                    ),
                    'taxes' => array(
                        6 => array(
                            'rate_type' => 'P',
                            'rate_value' => '10.000',
                            'price_includes_tax' => 'N',
                            'tax_subtotal' => 16.1,
                            'applies' => array(
                                'P' => 11.7,
                                'PS' => 1.6,
                                'S' => 2.8,
                                'items' => array(
                                    'S' => array(
                                        0 => array(
                                            1 => 1
                                        )
                                    ),
                                    'P' => array(
                                        1237327324 => 1,
                                    ),
                                    'PS' => array(
                                        2 => 1,
                                    )
                                )
                            )
                        ),
                        7 => array(
                            'rate_type' => 'P',
                            'rate_value' => '10.000',
                            'price_includes_tax' => 'N',
                            'tax_subtotal' => 19.1,
                            'applies' => array(
                                'P' => 12.7,
                                'PS' => 2.6,
                                'S' => 3.8,
                                'items' => array(
                                    'S' => array(
                                        0 => array(
                                            1 => 1
                                        )
                                    ),
                                    'P' => array(
                                        1237327324 => 1,
                                    ),
                                    'PS' => array(
                                        2 => 1,
                                    )
                                )
                            )
                        ),
                    ),
                    'tax_subtotal' => 35.2,
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 28,
                        )
                    ),
                    'secondary_currency' => 'RUB',
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 100,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'items' => array(
                        array(
                            /**
                             * discount = (130.0 + 24.4) / (130.0 + 24.4 + 28.0 + 6.6 + 15.95 + 4.2) * 13 = 9.6
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 130.0 + 24.4 / 1,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 24.4,
                            'discount' => 9.6
                        ),
                        array(
                            /**
                             * discount = (28.0 + 6.6) / (28.0 + 6.6 + 15.95 + 4.2) * (13 - 9.6) = 2.15
                             */
                            'name' => 'Custom shipping method',
                            'price' => 28.0 + 6.6,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 6.6,
                            'discount' => 2.15
                        ),
                        array(
                            /**
                             * discount = (15.95 + 4.2) / (15.95 + 4.2) * (13 - 9.6 - 2.15) = 1.25
                             */
                            'name' => 'surcharge_title',
                            'price' => 15.95 + 4.2,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 4.2,
                            'discount' => 1.25
                        )
                    ),
                    'payments' => array(
                        array(
                            'type' => 20,
                            'sum' => 196.15
                        )
                    ),
                ),
                'subtotal', 'RUB'
            ),
            array(
                /**
                 * Tax by subtotal, one tax included = N
                 * Order discount
                 */
                array(
                    'order_id' => 100,
                    'total' => 630.8,
                    'subtotal' => 130,
                    'timestamp' => 1484736211,
                    'subtotal_discount' => 100,
                    'payment_surcharge' => 0,
                    'shipping_cost' => 28.00,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'payment_id' => 2,
                    'payment_method' => array(
                        'payment_id' => 2,
                        'surcharge_title' => 'surcharge_title'
                    ),
                    'products' => array(
                        '3237327324' => array(
                            'item_id' => 3237327324,
                            'original_price' => 130.0,
                            'price' => 130.0,
                            'amount' => 1,
                            'product' => '16GB A Series Walkman Video MP3'
                        ),
                        '1237327325' => array(
                            'item_id' => 1237327325,
                            'original_price' => 140.0,
                            'price' => 140.0,
                            'subtotal' => 560.0,
                            'amount' => 4,
                            'product' => '16GB A Series Walkman Video MP3'
                        )
                    ),
                    'taxes' => array(
                        6 => array(
                            'rate_type' => 'P',
                            'rate_value' => '10.000',
                            'price_includes_tax' => 'N',
                            'tax_subtotal' => 12.8,
                            'applies' => array(
                                'P' => 10,
                                'S' => 2.8,
                                'items' => array(
                                    'S' => array(
                                        0 => array(
                                            1 => 1
                                        )
                                    ),
                                    'P' => array(
                                        3237327324 => 1,
                                        1237327325 => 1,
                                    )
                                )
                            )
                        ),
                        7 => array(
                            'rate_type' => 'P',
                            'rate_value' => '10.000',
                            'price_includes_tax' => 'Y',
                            'tax_subtotal' => 16.5,
                            'applies' => array(
                                'P' => 12.7,
                                'S' => 3.8,
                                'items' => array(
                                    'S' => array(
                                        0 => array(
                                            1 => 1
                                        )
                                    ),
                                    'P' => array(
                                        3237327324 => 1,
                                        1237327325 => 1,
                                    )
                                )
                            )
                        ),
                    ),
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 28,
                        )
                    ),
                    'secondary_currency' => 'RUB',
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 100,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'items' => array(
                        array(
                            /*
                             * tax_sum = (10 * 130 / (130 + 140 * 4) + (12.7 * 130 / (130 + 140 * 4)) = 4.28
                             * tax_not_included_sum = 10 * 130 / (130 + 140 * 4) = 1.88
                             * discount = (130.0 + 1.88) / (130.0 + 1.88 + (140.0 + 2.03) * 4 + 28.0 + 2.8) * 100 = 18.05
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 130.0 + 1.88,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 4.28,
                            'discount' => 18.05
                        ),
                        array(
                            /*
                             * tax_sum = (10 * 140 * 4 / (130 + 140 * 4)) + (12.7 * 140 * 4 / (130 + 140 * 4)) = 18.42
                             * tax_not_included_sum = 10 * 140 / (130 + 140 * 4) = 2.03
                             * discount = (140.0 + 2.03) * 4 / ((140.0 + 2.03) * 4 + 28.0 + 2.8) * (100 - 18.05) = 77.74
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 140.0 + 2.03,
                            'quantity' => 4.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 18.42,
                            'discount' => 77.74
                        ),
                        array(
                            /*
                             * discount = (28.0 + 2.8) / (28.0 + 2.8) * (100 - 18.05 - 77.74)
                             */
                            'name' => 'Custom shipping method',
                            'price' => 28.0 + 2.8,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 2.8 + 3.8,
                            'discount' => 4.21
                        ),
                    ),
                    'payments' => array(
                        array(
                            'type' => 20,
                            'sum' => 630.8
                        )
                    ),
                ),
                'subtotal', 'RUB'
            ),
            array(
                /**
                 * Tax by subtotal, one not included tax
                 * Convert currency
                 * Order discount
                 */
                array(
                    'order_id' => 100,
                    'total' => 630.8,
                    'subtotal' => 130,
                    'timestamp' => 1484736211,
                    'subtotal_discount' => 100,
                    'payment_surcharge' => 0,
                    'shipping_cost' => 28.00,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'payment_id' => 2,
                    'payment_method' => array(
                        'payment_id' => 2,
                        'surcharge_title' => 'surcharge_title'
                    ),
                    'products' => array(
                        '1237327324' => array(
                            'item_id' => 1237327324,
                            'price' => 130.0,
                            'original_price' => 130.0,
                            'subtotal' => 130.0,
                            'amount' => 1,
                            'product' => '16GB A Series Walkman Video MP3'
                        ),
                        '1237327325' => array(
                            'item_id' => 1237327325,
                            'price' => 140.0,
                            'original_price' => 140.0,
                            'subtotal' => 560.0,
                            'amount' => 4,
                            'product' => '16GB A Series Walkman Video MP3'
                        )
                    ),
                    'taxes' => array(
                        6 => array(
                            'rate_type' => 'P',
                            'rate_value' => '10.000',
                            'price_includes_tax' => 'N',
                            'tax_subtotal' => 12.8,
                            'applies' => array(
                                'P' => 10,
                                'S' => 2.8,
                                'items' => array(
                                    'S' => array(
                                        0 => array(
                                            1 => 1
                                        )
                                    ),
                                    'P' => array(
                                        1237327324 => 1,
                                        1237327325 => 1,
                                    )
                                )
                            )
                        ),
                        7 => array(
                            'rate_type' => 'P',
                            'rate_value' => '10.000',
                            'price_includes_tax' => 'Y',
                            'tax_subtotal' => 16.5,
                            'applies' => array(
                                'P' => 12.7,
                                'S' => 3.8,
                                'items' => array(
                                    'S' => array(
                                        0 => array(
                                            1 => 1
                                        )
                                    ),
                                    'P' => array(
                                        1237327324 => 1,
                                        1237327325 => 1,
                                    )
                                )
                            )
                        ),
                    ),
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 28,
                        )
                    ),
                    'secondary_currency' => 'USD',
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 100,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'items' => array(
                        array(
                            /*
                             * tax_not_included = (10 * 130 / (130 + 140 * 4)) * 10 = 18.84
                             * tax_included = (12.7 * 130 / (130 + 140 * 4)) * 10 = 23.93
                             * tax_sum = 18.84 + 23.93 = 42.77
                             * discount = (130.0 * 10 + 18.84) / (130.0 * 10 + 18.84 + (140.0 * 10 + 81.16 / 4) * 4 + 28.0 * 10 + 2.8 * 10) * 1000 = 180.47
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 130.0 * 10 + 18.84,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 42.77,
                            'discount' => 180.47
                        ),
                        array(
                            /*
                             * tax_not_included = (10 * 140 * 4 / (130 + 140 * 4)) * 10 = 81.16
                             * tax_included = (12.7 * 140 * 4 / (130 + 140 * 4)) * 10 = 103.08
                             * tax_sum = ((10 * 140 * 4 / (130 + 140 * 4)) + (12.7 * 140 * 4 / (130 + 140 * 4))) * 10 = 184.23
                             * discount = (140.0 * 10 + 81.16 / 4) * 4 / ((140.0 * 10 + 81.16 / 4) * 4 + 28.0 * 10 + 2.8 * 10) * (1000 - 180.47) = 777.38
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 140.0 * 10 + 81.16 / 4,
                            'quantity' => 4.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 184.23,
                            'discount' => 777.38
                        ),
                        array(
                            /*
                             * discount = (28.0 * 10 + 2.8 * 10) / (28.0 * 10 + 2.8 * 10) * (1000 - 180.47 - 777.38) = 42.15
                             */
                            'name' => 'Custom shipping method',
                            'price' => 28.0 * 10 + 2.8 * 10,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 2.8 * 10 + 3.8 * 10,
                            'discount' => 42.15
                        ),
                    ),
                    'payments' => array(
                        array(
                            'type' => 20,
                            'sum' => 6308.0
                        )
                    ),
                ),
                'subtotal', 'USD'
            ),
            array(
                /**
                 * Tax by unit_price, not included tax
                 * Order discount
                 * Payment surcharge
                 */
                array(
                    'order_id' => 100,
                    'total' => 196.15,
                    'subtotal' => 130,
                    'timestamp' => 1484736211,
                    'subtotal_discount' => 13,
                    'payment_surcharge' => 15.95,
                    'shipping_cost' => 28.00,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'payment_id' => 2,
                    'payment_method' => array(
                        'payment_id' => 2,
                        'surcharge_title' => 'surcharge_title'
                    ),
                    'products' => array(
                        '1237327324' => array(
                            'item_id' => 1237327324,
                            'price' => 130.0,
                            'original_price' => 130.0,
                            'subtotal' => 130.0,
                            'amount' => 1,
                            'product' => '16GB A Series Walkman Video MP3'
                        )
                    ),
                    'taxes' => array(
                        6 => array(
                            'rate_type' => 'P',
                            'rate_value' => '10.000',
                            'price_includes_tax' => 'N',
                            'tax_subtotal' => 16.1,
                            'applies' => array(
                                'P_1237327324' => 11.7,
                                'PS_2' => 1.6,
                                'S_0_1' => 2.8,
                            )
                        ),
                        7 => array(
                            'rate_type' => 'P',
                            'rate_value' => '10.000',
                            'price_includes_tax' => 'N',
                            'tax_subtotal' => 19.1,
                            'applies' => array(
                                'P_1237327324' => 12.7,
                                'PS_2' => 2.6,
                                'S_0_1' => 3.8,
                            )
                        ),
                    ),
                    'tax_subtotal' => 35.21,
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 28,
                        )
                    ),
                    'secondary_currency' => 'RUB',
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 100,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'items' => array(
                        array(
                            /**
                             * discount = (130.0 + 24.4) / (130.0 + 24.4 + 28.0 + 6.6 + 15.95 + 4.2) * 13 = 9.6
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 130.0 + 24.4,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 24.4,
                            'discount' => 9.6
                        ),
                        array(
                            /**
                             * discount = (28.0 + 6.6) / (28.0 + 6.6 + 15.95 + 4.2) * (13 - 9.6) = 2.15
                             */
                            'name' => 'Custom shipping method',
                            'price' => 28.0 + 6.6,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 6.6,
                            'discount' => 2.15
                        ),
                        array(
                            /**
                             * discount = (15.95 + 4.2) / (15.95 + 4.2) * (13 - 9.6 - 2.15) = 1.25
                             */
                            'name' => 'surcharge_title',
                            'price' => 15.95 + 4.2,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 4.2,
                            'discount' => 1.25
                        )
                    ),
                    'payments' => array(
                        array(
                            'type' => 20,
                            'sum' => 196.15
                        )
                    ),
                ),
                'unit_price', 'RUB'
            ),
            array(
                /**
                 * Tax by unit_price, not included tax
                 * Product discount
                 * Order discount
                 */
                array(
                    'order_id' => 100,
                    'total' => 741.45,
                    'subtotal' => 725.54,
                    'timestamp' => 1495006519,
                    'subtotal_discount' => 87.45,
                    'payment_surcharge' => 0,
                    'shipping_cost' => 28,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'payment_id' => 2,
                    'payment_method' => array(
                        'payment_id' => 2,
                        'surcharge_title' => 'surcharge_title'
                    ),
                    'products' => array(
                        '1237327324' => array(
                            'item_id' => 1237327324,
                            'price' => 120.0,
                            'original_price' => 120.0,
                            'subtotal' => 120.0,
                            'discount' => 0,
                            'amount' => 1,
                            'product' => '16GB A Series Walkman Video MP3'
                        ),
                        '1706372553' => array(
                            'item_id' => 1706372553,
                            'original_price' => 340.0,
                            'price' => 302.77,
                            'subtotal' => 605.54,
                            'discount' => 37.23,
                            'amount' => 2,
                            'product' => 'X-Box One'
                        )
                    ),
                    'taxes' => array(
                        6 => array(
                            'tax_subtotal' => 75.36,
                            'price_includes_tax' => 'N',
                            'applies' => array(
                                'P_1237327324' => 12,
                                'P_1706372553' => 60.56,
                                'S_0_1' => 2.8
                            )
                        )
                    ),
                    'tax_subtotal' => 75.36,
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 28,
                        )
                    ),
                    'secondary_currency' => 'RUB',
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 100,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'items' => array(
                        array(
                            /*
                             * discount = (120.0 + 12) / (120.0 + 12 + (340.0 * 2 + 60.56 - 37.23 * 2) + 28.0 + 2.8) * 87.45 = 13.93
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 120.0 + 12,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 12,
                            'discount' => 13.93
                        ),
                        array(
                            /*
                             * discount = 37.23 * 2 + (340.0 * 2 + 60.56 - 37.23 * 2) / ((340.0 * 2 + 60.56 - 37.23 * 2) + 28.0 + 2.8) * (87.45 - 13.93) = 133.54
                             */
                            'name' => 'X-Box One',
                            'price' => 340.0 + 60.56 / 2,
                            'quantity' => 2.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 60.56,
                            'discount' => 144.73
                        ),
                        array(
                            /**
                             * discount = (28.0 + 2.8) / (28.0 + 2.8) * (87.45 - 13.93 - 70.27)
                             */
                            'name' => 'Custom shipping method',
                            'price' => 28.0 + 2.8,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 2.8,
                            'discount' => 3.25
                        )
                    ),
                    'payments' => array(
                        array(
                            'type' => 20,
                            'sum' => 741.45
                        )
                    ),
                ),
                'unit_price', 'RUB'
            ),
            array(
                /**
                 * Tax by unit_price, included tax
                 * Product discount
                 * Order discount
                 */
                array(
                    'order_id' => 100,
                    'total' => 666.09,
                    'subtotal' => 798.1,
                    'timestamp' => 1495006519,
                    'subtotal_discount' => 87.45,
                    'payment_surcharge' => 0,
                    'shipping_cost' => 28,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'payment_id' => 2,
                    'payment_method' => array(
                        'payment_id' => 2,
                        'surcharge_title' => 'surcharge_title'
                    ),
                    'products' => array(
                        '1237327324' => array(
                            'item_id' => 1237327324,
                            'original_price' => 120.0,
                            'price' => 120.0,
                            'subtotal' => 120.0,
                            'discount' => 0,
                            'amount' => 1,
                            'product' => '16GB A Series Walkman Video MP3'
                        ),
                        '1706372553' => array(
                            'item_id' => 1706372553,
                            'original_price' => 340.0,
                            'subtotal' => 605.54,
                            'price' => 302.77,
                            'discount' => 37.23,
                            'amount' => 2,
                            'product' => 'X-Box One'
                        )
                    ),
                    'taxes' => array(
                        6 => array(
                            'tax_subtotal' => 75.36,
                            'price_includes_tax' => 'Y',
                            'applies' => array(
                                'P_1237327324' => 12,
                                'P_1706372553' => 60.56,
                                'S_0_1' => 2.8
                            )
                        )
                    ),
                    'tax_subtotal' => 75.36,
                    'shipping' => array(
                        array(
                            'shipping_id' => 1,
                            'shipping' => 'Custom shipping method',
                            'rate' => 28,
                        )
                    ),
                    'secondary_currency' => 'RUB',
                ),
                array(
                    'id' => null,
                    'type' => null,
                    'requisites' => null,
                    'uuid' => null,
                    'status' => Receipt::STATUS_WAIT,
                    'status_message' => null,
                    'sno' => 'Osn',
                    'object_id' => 100,
                    'object_type' => 'order',
                    'timestamp' => 100000,
                    'email' => 'customer@example.com',
                    'phone' => '+79073456797',
                    'items' => array(
                        array(
                            /**
                             * discount = 120.0 / (120.0 + (340.0 - 37.23) * 2 + 28.0) * 87.45 = 13.93
                             */
                            'name' => '16GB A Series Walkman Video MP3',
                            'price' => 120.0,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 12,
                            'discount' => 13.93
                        ),
                        array(
                            /**
                             * discount = 37.23 * 2 + ((340.0 - 37.23) * 2) / ((340.0 - 37.23) * 2 + 28.0) * (87.45 - 13.93) = 37.23 * 2 + 70.27 = 144.73
                             */
                            'name' => 'X-Box One',
                            'price' => 340.0,
                            'quantity' => 2.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 60.56,
                            'discount' => 144.73
                        ),
                        array(
                            /**
                             * discount = (28.0 + 2.8) / (28.0 + 2.8) * (87.45 - 13.93 - 70.27)
                             */
                            'name' => 'Custom shipping method',
                            'price' => 28.0,
                            'quantity' => 1.0,
                            'tax_type' => 'vat118',
                            'tax_sum' => 2.8,
                            'discount' => 3.25
                        )
                    ),
                    'payments' => array(
                        array(
                            'type' => 20,
                            'sum' => 666.09
                        )
                    ),
                ),
                'unit_price', 'RUB'
            ),
        );
    }
}