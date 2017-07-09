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

namespace Tygh\Ym;

use Tygh\Registry;

class OrderStatus
{

    protected $order;

    protected $statuses;
    protected $substatuses;
    protected $default_status = 'O';

    protected static $from_api = false;

    public function __construct($order_data)
    {
        $this->order = $order_data;

        $this->statuses = array(
            'UNPAID'     => Registry::get('addons.yandex_market.order_status_unpaid'),
            'PROCESSING' => Registry::get('addons.yandex_market.order_status_processing'),
            'DELIVERY'   => Registry::get('addons.yandex_market.order_status_delivery'),
            'PICKUP'     => Registry::get('addons.yandex_market.order_status_pickup'),
            'DELIVERED'  => Registry::get('addons.yandex_market.order_status_delivered'),
        );

        $this->substatuses = array(
            'SHOP_FAILED'            => Registry::get('addons.yandex_market.order_status_shop_failed'),
            'USER_CHANGED_MIND'      => Registry::get('addons.yandex_market.order_status_user_changed_mind'),
            'USER_NOT_PAID'          => Registry::get('addons.yandex_market.order_status_user_not_paid'),
            'PROCESSING_EXPIRED'     => Registry::get('addons.yandex_market.order_status_processing_expired'),
            'REPLACING_ORDER'        => Registry::get('addons.yandex_market.order_status_replacing_order'),
            'USER_REFUSED_DELIVERY'  => Registry::get('addons.yandex_market.order_status_user_refused_delivery'),
            'USER_REFUSED_PRODUCT'   => Registry::get('addons.yandex_market.order_status_user_refused_product'),
            'USER_REFUSED_QUALITY'   => Registry::get('addons.yandex_market.order_status_user_refused_quality'),
            'USER_UNREACHABLE'       => Registry::get('addons.yandex_market.order_status_unreachable'),
        );
    }

    public function change($status_to, $status_from)
    {
        if (self::$from_api) {
            return true;
        }

        if (!empty($this->order['yandex_market']['status'])) {
            if (in_array($this->order['yandex_market']['status'], array('PROCESSING', 'DELIVERY', 'PICKUP'))) {
                $data = array();

                if (in_array($status_to, $this->statuses)) {
                    $data['status'] = array_search($status_to, $this->statuses);
                }

                if (empty($data['status'])) {
                    if (in_array($status_to, $this->substatuses)) {
                        $data['status'] = 'CANCELLED';
                        $data['substatus'] = array_search($status_to, $this->substatuses);
                    }
                }

                if (!empty($data['status'])) {
                    $client = new ApiClient;
                    return $client->orderStatusUpdate($this->order['yandex_market']['order_id'], $data);
                }
            }
        }
    }

    public function update($yandex_status, $yandex_substatus)
    {
        $new_status = $this->default_status;
        if (isset($this->statuses[$yandex_status])) {
            $new_status = $this->statuses[$yandex_status];
        }

        if ($yandex_status == 'CANCELLED') {
            if (isset($this->substatuses[$yandex_substatus])) {
                $new_status = $this->substatuses[$yandex_substatus];
            }
        }

        self::$from_api = true;
        $result = fn_change_order_status($this->order['order_id'], $new_status);
        self::$from_api = false;

        return $result;
    }
}
