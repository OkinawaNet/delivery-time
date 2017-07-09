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

if (!defined('BOOTSTRAP')) { die('Access denied'); }

use Tygh\Registry;
use Tygh\Shippings\Shippings;

/**
 * Gets description use geolocation
 *
 * @return array description
 */
function fn_rus_geolocation_information()
{
    $geolocation_information = __('addon.rus_geolocation.geolocation_information');

    return $geolocation_information;
}

/**
 * Gets country and state for selected city
 *
 * @param string $city_geolocation name selected city
 * @return array city information
 */
function fn_rus_geolocation_select_cities($city_geolocation)
{
	$data_city = db_get_row(
        "SELECT a.city, b.city_code, b.state_code, b.country_code " .
        "FROM ?:rus_city_descriptions as a LEFT JOIN ?:rus_cities as b ON a.city_id = b.city_id " .
        "WHERE b.status = 'A' AND a.city = ?s", $city_geolocation
    );

    if (!empty($data_city['country_code']) && !empty($data_city['state_code'])) {
        $country = db_get_field(
            "SELECT d.country " .
            "FROM ?:countries as c LEFT JOIN ?:country_descriptions as d ON c.code = d.code " .
            "WHERE c.code = ?s AND c.status = 'A' ", $data_city['country_code']
        );

        if (!empty($country)) {
            $data_city['code'] = $data_city['country_code'];
            $data_city['country'] = $country;
        }

        $state = db_get_field(
            "SELECT b.state " .
            "FROM ?:states as a LEFT JOIN ?:state_descriptions as b ON a.state_id = b.state_id " .
            "WHERE a.code = ?s AND a.status = 'A' AND a.country_code = ?s ", $data_city['state_code'], $data_city['country_code']
        );
        $data_city['state'] = (!empty($state)) ? $state : '';
    }

    return $data_city;
}

/**
 * Gets list cities destinations
 *
 * @return array list cities
 */
function fn_rus_geolocation_list_destinations_cities()
{
    $data_cities = array();
    $select_countries = array();
    $select_states = array();

    $countries = db_get_fields("SELECT code FROM ?:countries WHERE status = 'A'");
    $states = db_get_hash_array(
        "SELECT state_id, code, country_code FROM ?:states " .
        "WHERE status = 'A' AND country_code IN (?a)", 'state_id', $countries
    );

    $data_destinations = db_get_array(
        "SELECT b.element_type, a.destination_id, b.element FROM ?:destinations as a LEFT JOIN ?:destination_elements as b ON a.destination_id = b.destination_id " .
        "WHERE a.status = 'A'"
    );

    $destinations_elements = array();
    foreach ($data_destinations as $d_destination) {
        if (($d_destination['element_type'] == 'S') && !empty($states[$d_destination['element']])) {
            $d_destination['element'] = $states[$d_destination['element']]['code'];
        }
        $destinations_elements[$d_destination['element_type']][$d_destination['destination_id']][$d_destination['element']] = $d_destination['element'];
    }

    $destinations_ids = array();
    foreach ($destinations_elements as $element_type => $_destinations) {
        foreach ($_destinations as $destination_id => $destination) {
            $destinations_ids[$destination_id][$element_type] = $element_type;
        }
    }

    foreach ($destinations_ids as $destination_id => $element) {
        if (empty($element['C']) && empty($element['S'])) {
            foreach ($states as $state) {
                $destinations_elements['C'][$destination_id][$state['country_code']] = $state['country_code'];

                $destinations_elements['S'][$destination_id][$state['code']] = $state['code'];
            }
        }

        if (!empty($element['C']) && empty($element['S'])) {
            foreach ($states as $state) {
                if (!empty($destinations_elements['C'][$destination_id][$state['country_code']])) {
                    $destinations_elements['S'][$destination_id][$state['code']] = $state['code'];
                }
            }
        }

        if (empty($element['C']) && !empty($element['S'])) {
            foreach ($states as $state) {
                if (!empty($destinations_elements['S'][$destination_id][$state['code']])) {
                    $destinations_elements['C'][$destination_id][$state['country_code']] = $state['country_code'];
                }
            }
        }

        if (!empty($destinations_elements['C'][$destination_id])) {
            $select_countries = array_merge($select_countries, $destinations_elements['C'][$destination_id]);
        }

        if (!empty($destinations_elements['S'][$destination_id])) {
            $select_states = array_merge($select_states, $destinations_elements['S'][$destination_id]);
        }
    }

    $list_cities = db_get_array(
        "SELECT a.city, b.country_code, b.state_code FROM ?:rus_city_descriptions as a LEFT JOIN ?:rus_cities as b ON a.city_id = b.city_id " .
        "WHERE a.lang_code = ?s AND country_code IN (?a) AND state_code IN (?a) ", CART_LANGUAGE, $select_countries, $select_states
    );

    foreach ($destinations_ids as $destination_id => $element) {
        $d_countries = $destinations_elements['C'][$destination_id];

        if (!empty($destinations_elements['S'][$destination_id])) {
            $d_states = $destinations_elements['S'][$destination_id];

            foreach ($list_cities as $d_city) {
                if (array_key_exists($d_city['country_code'], $d_countries) && array_key_exists($d_city['state_code'], $d_states)) {
                    if (!empty($destinations_elements['T'][$destination_id])) {
                        $d_cities = $destinations_elements['T'][$destination_id];

                        foreach ($d_cities as $city) {
                            $city = str_replace(array('*', '%'), '', $city);
                            if (strpos($d_city['city'], $city) !== false) {
                                $data_cities[$d_city['city']] = array(
                                    'city' => $d_city['city']
                                );
                            }
                        }
                    } else {
                        $data_cities[$d_city['city']] = array(
                            'city' => $d_city['city']
                        );
                    }
                }
            }
        }
    }

    return $data_cities;
}

/**
 * Gets rates shippings
 *
 * @param array $cart array cart
 * @param array $data_product array selected product
 * @return array group data rates shipping
 */
function fn_rus_geolocation_calculate_rate_shipping_product($cart, $data_product)
{
    $cart['free_shipping'] = array();
    $cart['products'] = array();
    $cart['calculate_shipping'] = true;

    if ($cart['subtotal'] >= 0) {
        $cart['applied_promotions'] = fn_promotion_apply('cart', $cart, $auth, $cart['products']);
    }

    $location = fn_get_customer_location($auth, $cart);
    $product_groups = Shippings::groupProductsList($data_product, $location);

    $group = reset($product_groups);
    $key_group = key($product_groups);

    if ($group['shipping_no_required'] === false) {
        $cart['shipping_required'] = true;
    }

    if ($cart['shipping_required'] === false) {
        $group['free_shipping'] = true;
        $group['shipping_no_required'] = true;
    }

    $shippings_group = Shippings::getShippingsList($group);
    foreach ($shippings_group as $shipping_id => &$shipping) {
        if (!empty($shipping['service_params']['max_weight_of_box'])) {
            $_group = Shippings::repackProductsByWeight($group, $shipping['service_params']['max_weight_of_box']);
        } else {
            $_group = $group;
        }

        $shipping['package_info'] = $_group['package_info'];
        $shipping['package_info_full'] = $_group['package_info_full'];
        $shipping['keys'] = array(
            'group_key' => $key_group,
            'shipping_id' => $shipping_id,
        );

        $group['shippings'][$shipping_id] = $shipping;
        $group['shippings'][$shipping_id]['rate'] = 0;
        $group['shippings'][$shipping_id]['free_shipping'] = (in_array($shipping_id, $cart['free_shipping']) || ($group['free_shipping'] && Shippings::isFreeShipping($shipping)));
    }

    if (!empty($cart['calculate_shipping'])) {
        $rates = Shippings::calculateRates($shippings_group);

        foreach ($rates as $rate) {
            $sh_id = $rate['keys']['shipping_id'];

            if ($rate['price'] !== false) {
                $rate['price'] += !empty($group['package_info']['shipping_freight']) ? $group['package_info']['shipping_freight'] : 0;
                $group['shippings'][$sh_id]['rate'] = empty($group['shippings'][$sh_id]['free_shipping']) ? $rate['price'] : 0;
            } else {
                unset($group['shippings'][$sh_id]);
            }

            if (!empty($rate['service_delivery_time'])) {
                $group['shippings'][$sh_id]['service_delivery_time'] = $rate['service_delivery_time'];
            }
        }
    }

    return $group;
}
