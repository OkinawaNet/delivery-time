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

// rus_build_pack dbazhenov

namespace Tygh\Shippings\Services;

use Tygh\Shippings\IService;
use Tygh\Registry;
use Tygh\Http;

/**
 * UPS shipping service
 */
class RussianPostPochta implements IService
{
    /**
     * Availability multithreading in this module
     *
     * @var bool $_allow_multithreading
     */
    private $_allow_multithreading = false;

    /**
     * The currency in which the carrier calculates shipping costs.
     *
     * @var string $calculation_currency
     */
    public $calculation_currency = 'RUB';

    /**
     * Maximum allowed requests to Russian Post server
     *
     * @var integer $_max_num_requests
     */
    private $_max_num_requests = 2;


    /**
     * Timeout requests to Russian Post server
     *
     * @var integer $_timeout
     */
    private $_timeout = 3;
    /**
     * Stack for errors occured during the preparing rates process
     *
     * @var array $_error_stack
     */
    private $_error_stack = array();

    private $code_errors = array(
        '1304' => ''
    );

    private function _internalError($error)
    {
        $this->_error_stack[] = $error;
    }

    /**
     * Sets data to internal class variable
     *
     * @param array $shipping_info
     */
    public function prepareData($shipping_info)
    {
        $this->_shipping_info = $shipping_info;
    }

    /**
     * Gets shipping cost and information about possible errors
     *
     * @param  string $resonse Reponse from Shipping service server
     * @return array  Shipping cost and errors
     */
    public function processResponse($response)
    {
        $return = array(
            'cost' => false,
            'error' => false,
        );
        $shipping_settings = $this->_shipping_info['service_params'];

        $result = (array) json_decode($response, true);
        if (!empty($result['pay'])) {
            $return['cost'] = $result['pay'] / 100;
        } else {
            $error = implode(', ', $result['error']);
            $return['error'] = $error;

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $_error) {
                    if (!empty($_error['code']) && isset($this->code_errors[$_error['code']])) {
                        $return['error'] = $return['error'] . '. ' . __('addons.rus_russianpost.not_service_shipping');
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Gets error message from shipping service server
     *
     * @param  string $resonse Reponse from Shipping service server
     * @return string Text of error or false if no errors
     */
    public function processErrors($response)
    {
        preg_match('/<span id=\"lblErrStr\">(.*)<\/span>/i', $response, $matches);

        $error = !empty($matches[1]) ? $matches[1] : __('error_occurred');

        if (!empty($this->_error_stack)) {
            foreach ($this->_error_stack as $_error) {
                $error .= '; ' . $_error;
            }
        }

        return $error;
    }

    /**
     * Checks if shipping service allows to use multithreading
     *
     * @return bool true if allow
     */
    public function allowMultithreading()
    {
        return $this->_allow_multithreading;
    }

    /**
     * Prepare request information
     *
     * @return array Prepared data
     */
    public function getRequestData()
    {
        $data_url = array (
            'headers' => array('Content-Type: application/json'),
            'timeout' => $this->_timeout
        );
        $data_post['errorcode'] = 1;

        $weight_data = fn_expand_weight($this->_shipping_info['package_info']['W']);
        $shipping_settings = $this->_shipping_info['service_params'];
        $origination = $this->_shipping_info['package_info']['origination'];
        $location = $this->_shipping_info['package_info']['location'];

        $data_post['typ'] = $shipping_settings['sending_type'];
        $data_post['cat'] = $shipping_settings['sending_category'];

        $data_post['dir'] = 0;
        $international = false;
        if ($origination['country'] != 'RU' || $location['country'] != 'RU') {
            $data_post['dir'] = 1;
            $international = true;
        }

        $data_post['date'] = date("Ymd", TIME);
        $data_post['closed'] = 1;

        if (empty($location['zipcode'])) {
            $this->_internalError(__('russian_post_empty_zipcode'));
            $location['zipcode'] = false;
        }

        $data_post['from'] = $origination['zipcode'];
        $data_post['to'] = $location['zipcode'];

        $country_code = db_get_field("SELECT code_N3 FROM ?:countries WHERE code = ?s", $location['country']);
        $data_post['country'] = $country_code;

        $weight = $weight_data['plain'] * Registry::get('settings.General.weight_symbol_grams');
        $data_post['weight'] = $weight;
        if (($data_post['weight'] < RUSSIANPOST_MIN_WEIGHT) && !empty($this->_shipping_info['keys']['shipping_id'])) {
            $data_post['weight'] = RUSSIANPOST_MIN_WEIGHT;
        }

        $total_cost = $this->_shipping_info['package_info']['C'];

        $cash_sum = 0;
        if (!empty($shipping_settings['cash_on_delivery'])) {
            $cash_sum = $shipping_settings['cash_on_delivery'];
        }

        if (!empty($cash_sum)) {
            if ($total_cost < $cash_sum) {
                $cash_sum = $total_cost;
            }
        }

        $data_post['sumoc'] = $total_cost * 100;
        $data_post['sumnp'] = $cash_sum * 100;

        if (!empty($shipping_settings['sending_package'])) {
            $data_post['pack'] = $shipping_settings['sending_package'];
        }

        $data_post['isavia'] = $shipping_settings['isavia'];

        $data_post['service'] = '';
        if (!empty($shipping_settings['services'])) {
            $services = '';
            foreach ($shipping_settings['services'] as $service) {
                if ($service != 'N') {
                    $services[] = $service;
                }
            }

            $data_post['service'] = (!empty($services)) ? implode(',', $services) : '';
        }

        $url = "http://tariff.russianpost.ru/tariff/v1/calculate?json";
        $request_data = array(
            'method' => 'get',
            'url' => $url,
            'data' => $data_post,
            'data_url' => $data_url
        );

        return $request_data;
    }

    /**
     * Process simple request to shipping service server
     *
     * @return string Server response
     */
    public function getSimpleRates()
    {
        $response = false;

        if (empty($this->_error_stack)) {
            $data = $this->getRequestData();
            $response = Http::get($data['url'], $data['data'], $data['data_url']);
        }

        return $response;
    }

    public function prepareAddress($address)
    {
        
    }

    /**
     * Returns shipping service information
     * @return array information
     */
    public static function getInfo()
    {
        return array(
            'name' => __('carrier_russian_pochta'),
            'tracking_url' => 'https://www.pochta.ru/tracking#%s'
        );
    }
}
