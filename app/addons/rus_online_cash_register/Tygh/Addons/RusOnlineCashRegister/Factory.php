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


use Pimple\Container;
use Tygh\Addons\RusOnlineCashRegister\CashRegister\Atol\CashRegister;
use Tygh\Addons\RusOnlineCashRegister\CashRegister\ICashRegister;
use Tygh\Http;

/**
 * Class provides methods for creating cash register instance and cash register service.
 *
 * @package Tygh\Addons\RusOnlineCashRegister
 */
class Factory
{
    protected $container;

    /**
     * Factory constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Create CashRegister instance.
     *
     * @param string        $inn                Company INN
     * @param string        $group_code         ATOL Group code
     * @param string        $payment_address    ATOL Payment address
     * @param string        $login              ATOL login
     * @param string        $password           ATOL password
     * @param RequestLogger $request_logger     Instance of the RequestLogger
     *
     * @return ICashRegister
     */
    public function createCashRegister($inn, $group_code, $payment_address, $login, $password, $request_logger = null)
    {
        return new CashRegister(
            $inn,
            $group_code,
            $payment_address,
            $login,
            $password,
            fn_url('online_cash_register.callback_atol', 'C'),
            new Http(),
            $request_logger ? $request_logger : $this->container['addons.rus_online_cash_register.request_logger']
        );
    }

    /**
     * Create CashRegister instance by params.
     *
     * @param array $params
     *
     * @return ICashRegister
     */
    public function createCashRegisterByArray(array $params)
    {
        return $this->createCashRegister(
            isset($params['atol_inn']) ? $params['atol_inn'] : null,
            isset($params['atol_group_code']) ? $params['atol_group_code'] : null,
            isset($params['atol_payment_address']) ? $params['atol_payment_address'] : null,
            isset($params['atol_login']) ? $params['atol_login'] : null,
            isset($params['atol_password']) ? $params['atol_password'] : null
        );
    }

    /**
     * Create service
     *
     * @param null|ICashRegister        $cash_register          Cash register instance.
     * @param null|ReceiptRepository    $receipt_repository     Receipt repository instance.
     * @param null|ReceiptFactory       $receipt_factory        Receipt factory.
     *
     * @return Service
     */
    public function createService($cash_register = null, $receipt_repository = null, $receipt_factory = null)
    {
        return new Service(
            $cash_register ? $cash_register : $this->container['addons.rus_online_cash_register.cash_register'],
            $receipt_repository ? $receipt_repository : $this->container['addons.rus_online_cash_register.receipt_repository'],
            $receipt_factory ? $receipt_factory : $this->container['addons.rus_online_cash_register.receipt_factory']
        );
    }
}