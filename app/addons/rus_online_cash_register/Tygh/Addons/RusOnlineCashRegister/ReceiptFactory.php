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


namespace Tygh\Addons\RusOnlineCashRegister;

use Exception;
use Tygh\Addons\RusOnlineCashRegister\Receipt\Payment;
use Tygh\Addons\RusOnlineCashRegister\Receipt\Receipt;

/**
 * Provides methods to creating receipt from order.
 *
 * @package Tygh\Addons\RusOnlineCashRegister
 */
class ReceiptFactory
{
    const TAX_TYPE_PRODUCT = 'P';

    const TAX_TYPE_SHIPPING = 'S';

    const TAX_TYPE_PAYMENT_SURCHARGE = 'PS';

    const TAX_NONE = 'none';

    /** @var string Currency code (RUB, USD) */
    protected $currency;

    /** @var string Primary currency code (RUB, USD) */
    protected $primary_currency;

    /** @var string Taxation system */
    protected $sno;

    /** @var array List of local taxes to external taxes */
    protected $taxes_map;

    /** @var array List of local payments to external payments */
    protected $payments_map;

    /** @var string Calculation tax type (subtotal|unit_price)  */
    protected $tax_calculation_type;

    /** @var bool Prices with taxes (settings.Appearance.cart_prices_w_taxes)  */
    protected $is_prices_with_taxes;

    /** @var string Callback function for convert price */
    protected $currency_converter_callback;

    /**
     * ReceiptFactory constructor.
     *
     * @param string      $primary_currency             Primary currency code (RUB, USD)
     * @param string      $currency                     Currency code (RUB, USD)
     * @param string      $sno                          Taxation system
     * @param array       $payments_map                 List of the payments map
     * @param array       $taxes_map                    List of the taxes map
     * @param string      $tax_calculation_type         Calculation tax type (subtotal|unit_price)
     * @param bool        $is_prices_with_taxes         Prices with taxes (settings.Appearance.cart_prices_w_taxes)
     * @param string      $currency_converter_callback  Callback function for convert price
     *
     * @throws Exception
     */
    public function __construct(
        $primary_currency,
        $currency,
        $sno,
        array $payments_map,
        array $taxes_map,
        $tax_calculation_type,
        $is_prices_with_taxes,
        $currency_converter_callback = 'fn_format_price_by_currency'
    )
    {
        $this->primary_currency = $primary_currency;
        $this->currency = $currency;
        $this->sno = $sno;
        $this->payments_map = $payments_map;
        $this->taxes_map = $taxes_map;
        $this->currency_converter_callback = $currency_converter_callback;
        $this->tax_calculation_type = $tax_calculation_type;
        $this->is_prices_with_taxes = $is_prices_with_taxes;

        if (!is_callable($currency_converter_callback)) {
            throw new Exception('Argument currency_converter_callback must be a callable');
        }
    }

    /**
     * Creates receipt instance from order.
     *
     * @param array $order  Order data.
     *
     * @return Receipt
     */
    public function createReceiptFromOrder(array $order)
    {
        $payment_id = isset($order['payment_id']) ? $order['payment_id'] : 0;
        $subtotal_discount = isset($order['subtotal_discount']) ? $order['subtotal_discount'] : 0;
        $order_total = isset($order['total']) ? $order['total'] : 0;
        $total = 0;

        $receipt = array(
            'sno' => $this->sno,
            'status' => Receipt::STATUS_WAIT,
            'object_type' => 'order',
            'object_id' => $order['order_id'],
            'timestamp' => TIME,
            'email' => isset($order['email']) ? $order['email'] : '',
            'phone' => isset($order['phone']) ? $order['phone'] : '',
            'items' => array(),
        );

        foreach ($order['products'] as $product) {
            $item = $this->getProductItem($order, $product);

            if ($item) {
                $receipt['items'][] = $item;
                $total += $item['total'];
            }
        }

        if (!empty($order['shipping'])) {
            foreach ($order['shipping'] as $shipping) {
                $item = $this->getShippingItem($order, $shipping);

                if ($item) {
                    $receipt['items'][] = $item;
                    $total += $item['total'];
                }
            }
        }

        if (!empty($order['payment_surcharge'])) {
            $item = $this->getPaymentSurchargeItem($order);
            $receipt['items'][] = $item;
            $total += $item['total'];
        }

        $discount_value = $this->convertPrice($subtotal_discount);
        $order_total = $this->convertPrice($order_total);

        if ($order_total < $total - $discount_value) {
            $discount_value += ($total - $discount_value) - $order_total;
        }

        if ($discount_value) {
            foreach ($receipt['items'] as &$item) {
                $discount = $this->roundPrice($item['total'] / $total * $discount_value);

                $item['discount'] += $discount;
                $discount_value -= $discount;
                $total -= $item['total'];
            }

            unset($item);
        }

        $receipt = Receipt::fromArray($receipt);
        $receipt->setPayment(new Payment($this->getExternalPaymentId($payment_id), $receipt->getTotal()));

        return $receipt;
    }

    /**
     * Convert price.
     *
     * @param float $price Base price
     *
     * @return float
     */
    protected function convertPrice($price)
    {
        if ($this->primary_currency !== $this->currency) {
            $price = call_user_func($this->currency_converter_callback, $price, $this->primary_currency, $this->currency);
        }

        $price = $this->roundPrice($price);

        return $price;
    }

    /**
     * Gets tax from order array bay type.
     *
     * @param array  $order     Order data.
     * @param string $type      Object type (PS - payment surcharge, P - product, S - shipping)
     * @param string $id        Object identifier (payment identifier, shipping identifier, product identifier)
     *
     * @return array
     */
    protected function getTax(array $order, $type, $id)
    {
        $taxes = array();
        $result = array(
            'included_sum' => 0,
            'unincluded_sum' => 0,
            'sum' => 0,
            'id' => self::TAX_NONE
        );

        if (empty($order['taxes'])) {
            return $result;
        }

        if ($this->tax_calculation_type !== 'unit_price') {
            foreach ($order['taxes'] as $tax_id => $item) {
                $item['tax_id'] = $tax_id;

                if ($type === self::TAX_TYPE_SHIPPING && !empty($item['applies']['items'][$type])) {
                    foreach ($item['applies']['items'][$type] as $sub_item) {
                        if (!empty($sub_item[$id])) {
                            $taxes[] = $item;
                        }
                    }
                } elseif (!empty($item['applies']['items'][$type][$id])) {
                    $taxes[] = $item;
                }
            }

            foreach ($taxes as $item) {
                if ($result['id'] === self::TAX_NONE) {
                    $result['id'] = $this->getExternalTaxCode($item['tax_id']);
                }

                $sum = $item['applies'][$type];

                if ($type === self::TAX_TYPE_PRODUCT) {
                    $total = 0;
                    $item_total = 0;

                    foreach ($item['applies']['items'][$type] as $item_id => $flag) {
                        $quantity = isset($order['products'][$item_id]['amount']) ? $order['products'][$item_id]['amount'] : 1;
                        $price = isset($order['products'][$item_id]['price']) ? $order['products'][$item_id]['price'] : 1;
                        $total += $price * $quantity;

                        if ($item_id == $id) {
                            $item_total += $price * $quantity;;
                        }
                    }

                    $sum = $sum * $item_total / $total;
                }

                $result['sum'] += $sum;

                if ($item['price_includes_tax'] === 'N') {
                    $result['unincluded_sum'] += $sum;
                } else {
                    $result['included_sum'] += $sum;
                }
            }
        } else {
            foreach ($order['taxes'] as $tax_id => $item) {
                if ($type === self::TAX_TYPE_SHIPPING) {
                    foreach ($item['applies'] as $key => $value) {
                        if (strpos($key, $type . '_') === 0) {
                            $part = explode('_', $key);
                            $item_id = array_pop($part);

                            if ($item_id && $item_id == $id) {
                                if ($result['id'] === self::TAX_NONE) {
                                    $result['id'] = $this->getExternalTaxCode($tax_id);
                                }

                                $result['sum'] += $value;

                                if ($item['price_includes_tax'] === 'N') {
                                    $result['unincluded_sum'] += $value;
                                } else {
                                    $result['included_sum'] += $value;
                                }
                            }
                        }
                    }
                } elseif (isset($item['applies'][$type . '_' . $id])) {
                    if ($result['id'] === self::TAX_NONE) {
                        $result['id'] = $this->getExternalTaxCode($tax_id);
                    }

                    $result['sum'] += $item['applies'][$type . '_' . $id];

                    if ($item['price_includes_tax'] === 'N') {
                        $result['unincluded_sum'] += $item['applies'][$type . '_' . $id];
                    } else {
                        $result['included_sum'] += $item['applies'][$type . '_' . $id];
                    }
                }
            }
        }

        $result['sum'] = $this->convertPrice($result['sum']);
        $result['unincluded_sum'] = $this->convertPrice($result['unincluded_sum']);
        $result['included_sum'] = $this->convertPrice($result['included_sum']);

        return $result;
    }

    /**
     * Gets external payment id.
     *
     * @param int $payment_id Payment identifier.
     *
     * @return int
     */
    protected function getExternalPaymentId($payment_id)
    {
        return isset($this->payments_map[$payment_id]) ? $this->payments_map[$payment_id] : 0;
    }

    /**
     * Gets external tax id.
     *
     * @param int $tax_id   Tax identifier.
     *
     * @return string
     */
    protected function getExternalTaxCode($tax_id)
    {
        return isset($this->taxes_map[$tax_id]) ? $this->taxes_map[$tax_id] : self::TAX_NONE;
    }

    /**
     * Rounds price value.
     *
     * @param float $price
     *
     * @return float
     */
    protected function roundPrice($price)
    {
        return round($price, 2);
    }

    /**
     * Gets product receipt item.
     *
     * @param array $order      Order data.
     * @param array $product    Product data.
     *
     * @return array|bool
     */
    protected function getProductItem($order, $product)
    {
        $quantity = isset($product['amount']) ? $product['amount'] : 1;
        $price = isset($product['price']) ? $this->convertPrice($product['price']) : 1;
        $original_price = isset($product['original_price']) ? $this->convertPrice($product['original_price']) : 1;

        /*
         * Calculate product subtotal without tax
         */
        $subtotal = $this->roundPrice($price * $quantity);

        if ($subtotal <= 0) {
            return false;
        }

        $tax = $this->getTax($order, self::TAX_TYPE_PRODUCT, $product['item_id']);

        $item_tax = $this->roundPrice($tax['unincluded_sum'] / $quantity);
        $price += $item_tax;
        $original_price += $item_tax;
        $discount = $this->roundPrice(($original_price - $price) * $quantity);

        $result = array(
            'tax_type' => $tax['id'],
            'tax_sum' => $tax['sum'],
            'name' => isset($product['product']) ? strip_tags($product['product']) : '',
            'price' => $original_price,
            'quantity' => $quantity,
            'discount' => $discount,
            'total' => $original_price * $quantity - $discount
        );

        return $result;
    }

    /**
     * Gets shipping receipt item.
     *
     * @param array $order      Order data.
     * @param array $shipping   Shipping data.
     *
     * @return array|bool
     */
    protected function getShippingItem($order, $shipping)
    {
        $quantity = 1;
        $price = isset($shipping['rate']) ? $this->convertPrice($shipping['rate']) : 1;

        if ($price <= 0) {
            return false;
        }

        $tax = $this->getTax($order, self::TAX_TYPE_SHIPPING, $shipping['shipping_id']);
        $price = $this->roundPrice($price + $tax['unincluded_sum']);

        $result = array(
            'tax_type' => $tax['id'],
            'tax_sum' => $tax['sum'],
            'name' => isset($shipping['shipping']) ? strip_tags($shipping['shipping']) : '',
            'price' => $price,
            'quantity' => $quantity,
            'discount' => 0,
            'total' => $price
        );

        return $result;
    }

    /**
     * Gets payment surcharge receipt item.
     *
     * @param array $order Order data.
     *
     * @return array|bool
     */
    protected function getPaymentSurchargeItem($order)
    {
        $quantity = 1;
        $price = $this->convertPrice($order['payment_surcharge']);
        $tax = $this->getTax($order, self::TAX_TYPE_PAYMENT_SURCHARGE, $order['payment_id']);

        if ($this->tax_calculation_type !== 'unit_price' || !$this->is_prices_with_taxes) {
            $price = $this->roundPrice($price + $tax['unincluded_sum']);
        }

        $title = empty($order['payment_method']['surcharge_title']) ? __('payment_surcharge') : $order['payment_method']['surcharge_title'];

        $result = array(
            'tax_type' => $tax['id'],
            'tax_sum' => $tax['sum'],
            'name' => strip_tags($title),
            'price' => $price,
            'quantity' => $quantity,
            'discount' => 0,
            'total' => $price
        );

        return $result;
    }
}