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

namespace Tygh\Api\Entities;

use Tygh\Api\AEntity;
use Tygh\Api\Response;
use Tygh\Registry;

class CartContent extends AEntity
{
    /**
     * @var array $cart Shopping cart content
     */
    private $cart;

    /**
     * Gets cart content.
     *
     * @return array
     */
    protected function get()
    {
        if (!$this->cart) {
            $this->cart = array();
            fn_extract_cart_content($this->cart, $this->auth['user_id']);
        }

        return $this->cart;
    }

    /**
     * Calculates cart content with promotions, taxes and shipping.
     *
     * @param array $params
     *
     * @return array
     */
    protected function calculate($params = array())
    {
        $cart = $this->get();

        $params = array_merge(array(
            'calculate_shipping' => 'S', // skip shipping calculation
            'coupon_codes' => array(),
            'shipping_ids' => array()
        ), $params);

        if ($params['shipping_ids']) {
            $cart['chosen_shipping'] = $params['shipping_ids'];
            if ($params['calculate_shipping'] == 'S') {
                $params['calculate_shipping'] = 'E';
            }
        }

        if ($params['coupon_codes']) {
            $do_recalc = false;
            foreach($params['coupon_codes'] as $code) {
                if ($do_recalc) {
                    fn_calculate_cart_content($cart, $this->auth, 'S', false, 'S', true);
                }
                $cart['pending_coupon'] = $code;
                $do_recalc = true;
            }
        }

        if ($params['calculate_shipping'] != 'S') {
            $cart['calculate_shipping'] = true;
        }

        fn_calculate_cart_content($cart, $this->auth, $params['calculate_shipping']);

        // remove sensitive and redundant information
        $cart = $this->stripServiceData($cart);

        // add payment methods
        $cart['payments'] = $this->getPayments();

        return $cart;
    }

    /**
     * Gets payments list that doesn't contain any sensitive data (like config).
     *
     * @return array
     */
    protected function getPayments()
    {
        return array_map(function($payment) {
            return array(
                'payment' => $payment['payment'],
                'description' => $payment['description'],
                'instructions' => $payment['instructions'],
                'p_surcharge' => $payment['p_surcharge'],
                'a_surcharge' => $payment['a_surcharge'],
                'surcharge_title' => $payment['surcharge_title'],
            );
        }, fn_get_payments(array(
            'area' => 'C',
            'usergroup_ids' => $this->auth['usergroup_ids']
        )));
    }


    /**
     * Saves cart content.
     *
     * @return bool
     */
    protected function save()
    {
        return fn_save_cart_content($this->cart, $this->auth['user_id']);
    }

    /**
     * Adds product to a cart.
     *
     * @param array $cart_products Products data to add/update.
     *                             Add:
     *                             product_id: [
     *                                 'amount': product_amount,
     *                                 'product_options': [
     *                                     option_id => option_value
     *                                 ]
     *                             ]
     *                             Update:
     *                             cart_id: [
     *                                 'amount': product_amount,
     *                                 'product_options': [
     *                                     option_id => option_value
     *                                 ]
     *                             ]
     * @param bool  $update        Whether to update existing cart products or add the new one
     *
     * @return array Status and added products cart IDs as pairs of [cart_id => product_id].
     *               Status is Response::STATUS_CREATED when products are added
     *               and Response::STATUS_CONFLICT when unable to add.
     */
    protected function addProducts($cart_products = array(), $update = false)
    {
        $this->get();

        $result = fn_add_product_to_cart($cart_products, $this->cart, $this->auth, $update);

        if ($result) {

            $this->save();

            return array(Response::STATUS_CREATED, array('cart_ids' => $result));
        }

        return array(Response::STATUS_CONFLICT, array());
    }

    /**
     * Removes product from cart.
     *
     * @param int $cart_id Product cart ID or 0 to clear cart
     */
    protected function removeProduct($cart_id = 0)
    {
        $this->get();

        if ($cart_id) {
            fn_delete_cart_product($this->cart, $cart_id);
        }

        if (!$cart_id || fn_cart_is_empty($this->cart) == true) {
            fn_clear_cart($this->cart);
        }

        $this->save();
    }

    /**
     * Lists cart content.
     *
     * @param string $id
     * @param array  $params
     *
     * @return array
     */
    public function index($id = '', $params = array())
    {
        if ($user_relation_error = $this->getUserRelationError()) {
            return $user_relation_error;
        }

        if ($id) {
            return array(
                'status' => Response::STATUS_METHOD_NOT_ALLOWED,
                'data' => array(
                    'message' => __('api_not_need_id'),
                ),
            );
        }

        // normalize coupon codes
        if ($coupon_codes = $this->safeGet($params, 'coupon_codes', array())) {
            $params['coupon_codes'] = array_map(function($code) {
                return strtolower(trim($code));
            }, array_unique((array)$coupon_codes));
        }

        // normalize shipping ids
        $params['shipping_ids'] = array_filter((array)$this->safeGet($params, 'shipping_ids', array()), 'is_numeric');

        // normalize shipping calculation policy
        $calculate_shipping = $this->safeGet($params, 'calculate_shipping', 'S');
        $params['calculate_shipping'] = in_array($calculate_shipping, array('A', 'E', 'S')) ? $calculate_shipping: 'S';

        return array(
            'status' => Response::STATUS_OK,
            'data'   => $this->calculate($params),
        );
    }

    /**
     * Entry point for cart management.
     *
     * @param array $params
     *
     * @return array
     */
    public function create($params)
    {
        if ($user_relation_error = $this->getUserRelationError()) {
            return $user_relation_error;
        }

        $status = Response::STATUS_BAD_REQUEST;
        $data = array();

        $cart_products = $this->safeGet($params, 'products', array());

        // add to cart
        if ($cart_products) {
            list($status, $data) = $this->addProducts($cart_products);
        } else {
            $data['message'] = __('api_required_field', array(
                '[field]' => 'products'
            ));
        }

        return array(
            'status' => $status,
            'data'   => $data,
        );
    }

    // update amount/options
    public function update($id = '', $params = array())
    {
        if ($user_relation_error = $this->getUserRelationError()) {
            return $user_relation_error;
        }

        $status = Response::STATUS_BAD_REQUEST;
        $data = array();

        $can_edit = true;
        if ($id) {
            $cart_products = array($id => $params);
            if (!$params) {
                $can_edit = false;
                $data['message'] = __('api_need_params');
            }

        } else {
            $cart_products = (array)$this->safeGet($params, 'products', array());
            if (!$cart_products) {
                $can_edit = false;
                $data['message'] = __('api_required_field', array(
                    '[field]' => 'products'
                ));
            }
        }

        if ($can_edit) {
            $this->get();

            foreach ($cart_products as $cart_id => $product) {
                // check if editing products that are not in cart
                if (!isset($this->cart['products'][$cart_id])) {
                    $can_edit = false;
                    $status = Response::STATUS_NOT_FOUND;
                    break;
                }

                // remove products with zero amount
                if (isset($products_data['amount']) && empty($product['amount']) && !isset($this->cart['products'][$cart_id]['extra']['parent'])) {
                    $this->removeProduct($cart_id);
                    continue;
                }

                // update existing product data
                $cart_products[$cart_id] = array_merge($this->cart['products'][$cart_id], $product);
            }
        }

        // update cart
        if ($can_edit) {
            list($status, $data) = $this->addProducts($cart_products, true);
        }

        return array(
            'status' => $status,
            'data' => $data,
        );
    }

    /**
     * Deletes a product from a cart or cleans up the whole cart.
     *
     * @param int $id Product cart ID
     *
     * @return array
     */
    public function delete($id = 0)
    {
        if ($user_relation_error = $this->getUserRelationError()) {
            return $user_relation_error;
        }

        $status = Response::STATUS_NO_CONTENT;
        $this->get();

        if ($id && !isset($this->cart['products'][$id])) {
            $status = Response::STATUS_NOT_FOUND;
        } else {
            $this->removeProduct($id);
        }

        return array(
            'status' => $status,
            'data'   => array(),
        );
    }

    public function privilegesCustomer()
    {
        return array(
            'index'  => $this->auth['is_token_auth'],
            'create' => $this->auth['is_token_auth'],
            'update' => $this->auth['is_token_auth'],
            'delete' => $this->auth['is_token_auth'],
        );
    }

    public function privileges()
    {
        return array(
            'index'  => true,
            'create' => true,
            'update' => true,
            'delete' => true,
        );
    }

    /**
     * Strips configuration data and redundant information from cart data.
     *
     * @param array $cart
     *
     * @return array
     */
    protected function stripServiceData($cart)
    {
        foreach ($cart['product_groups'] as $group_id => $group) {
            // remove session product data
            foreach($cart['products'] as $cart_id => $product) {
                unset(
                    $cart['products'][$cart_id]['user_id'],
                    $cart['products'][$cart_id]['timestamp'],
                    $cart['products'][$cart_id]['type'],
                    $cart['products'][$cart_id]['user_type'],
                    $cart['products'][$cart_id]['item_id'],
                    $cart['products'][$cart_id]['item_type'],
                    $cart['products'][$cart_id]['session_id'],
                    $cart['products'][$cart_id]['ip_address'],
                    $cart['products'][$cart_id]['order_id'],

                    $cart['product_groups'][$group_id]['products'][$cart_id]['user_id'],
                    $cart['product_groups'][$group_id]['products'][$cart_id]['timestamp'],
                    $cart['product_groups'][$group_id]['products'][$cart_id]['type'],
                    $cart['product_groups'][$group_id]['products'][$cart_id]['user_type'],
                    $cart['product_groups'][$group_id]['products'][$cart_id]['item_id'],
                    $cart['product_groups'][$group_id]['products'][$cart_id]['item_type'],
                    $cart['product_groups'][$group_id]['products'][$cart_id]['session_id'],
                    $cart['product_groups'][$group_id]['products'][$cart_id]['ip_address'],
                    $cart['product_groups'][$group_id]['products'][$cart_id]['order_id']
                );
            }

            // remove shipping config
            foreach ($group['shippings'] as $shipping_id => $shipping) {
                unset(
                    $cart['product_groups'][$group_id]['shippings'][$shipping_id]['service_params'],
                    $cart['product_groups'][$group_id]['shippings'][$shipping_id]['rate_info'],
                    $cart['shipping'][$shipping_id]['service_params'],
                    $cart['shipping'][$shipping_id]['rate_info']
                );
            }

            // all required data is stored in $cart['chosen_shipping']
            unset(
                $cart['product_groups'][$group_id]['chosen_shippings'],
                $cart['product_groups'][$group_id]['package_info'],
                $cart['product_groups'][$group_id]['package_info_full']
            );
        }

        // remove promotions config
        unset($cart['applied_promotions']);

        return $cart;
    }

    /**
     * Provides company identifier of a storefront.
     *
     * @return int Company ID
     */
    protected function getCompanyId()
    {
        if (!empty($this->parent['company_id'])) {
            $company_id = $this->parent['company_id'];
        } elseif (Registry::get('runtime.simple_ultimate')) {
            $company_id = Registry::get('runtime.forced_company_id');
        } else {
            $company_id = Registry::get('runtime.company_id');
        }

        return $company_id;
    }

    /**
     * Checks whether users are shared between storefronts.
     *
     * @return bool
     */
    protected function areUsersShared()
    {
        return fn_allowed_for('ULTIMATE') && Registry::get('settings.Stores.share_users') == 'Y';
    }

    /**
     * Checks whether user belongs to a company.
     *
     * @param int $company_id Company identifier of a user
     *
     * @return array API response data to return if user doesn't belong to a company or an empty array if belongs
     */
    protected function getUserRelationError($company_id = null)
    {
        if ($company_id == null && isset($this->auth['company_id'])) {
            $company_id = $this->auth['company_id'];
        }

        if (fn_allowed_for('ULTIMATE')
            && $company_id
            && $company_id != $this->getCompanyId()
            && !$this->areUsersShared()
        ) {
            return array(
                'status' => Response::STATUS_FORBIDDEN,
                'data' => array(
                    'message' => __('api_wrong_user_company_relation'),
                ),
            );
        }

        return array();
    }
}
