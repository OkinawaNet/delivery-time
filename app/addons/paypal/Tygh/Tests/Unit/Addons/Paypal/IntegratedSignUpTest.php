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

namespace Tygh\Tests\Unit\Addons\Paypal;

use Tygh\Tests\Unit\ATestCase;

class IntegratedSignUpTest extends ATestCase
{
    public $runTestInSeparateProcess = true;
    public $backupGlobals = false;
    public $preserveGlobalState = false;

    protected function setUp()
    {
        define('BOOTSTRAP', true);
        define('AREA', 'A');
        define('ACCOUNT_TYPE', 'admin');

        $this->requireCore('addons/paypal/func.php');
        $this->requireMockFunction('fn_allowed_for');
    }

    /**
     * Checks that referral URL is generated and returned to a merchant.
     */
    public function testGeneral()
    {
        $request = fn_paypal_build_signup_request(
            $company_id = 1,
            $user_id = 1,
            $payment_id = 1,
            $config_mode = 'test',
            $store_url = 'http://example.com/index.php?dispatch=paypal_connector.end_signup&payment_id=1',
            $currency = 'USD',
            $protocol = 'http',
            $placement_info = array(
                'company_support_department' => 'no-reply@example.com',
                'company_name' => 'Simtech',
                'company_address' => '44 Main street',
                'company_city' => 'Boston',
                'company_state' => 'MA',
                'company_country' => 'US',
                'company_zipcode' => '02134',
                'company_website' => 'http://example.com',
                'company_phone' => '+19177227425',
            ),
            $user_data = array(
                'email' => 'admin@example.com',
                'lang_code' => 'en',
                'firstname' => 'First',
                'lastname' => 'Last',
                'b_address' => '44 Main street',
                'b_address_2' => 'test',
                'b_city' => 'Boston',
                'b_state' => 'MA',
                'b_country' => 'US',
                'b_zipcode' => '02116',
                'phone' => '+15555555555',
            ),
            $company_data = array(
                'email' => '',
            ),
            $phone_validation_rules = array(
                'US' => array(
                    '1',
                )
            )
        );

        $ch = curl_init('http://cs-cart.com/index.php');

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // do not follow redirect
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        // return response headers to parse redirect URL
        curl_setopt($ch, CURLOPT_HEADER, true);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

        $response = curl_exec($ch);
        preg_match_all('/^Location:(.*)$/mi', $response, $matches);
        curl_close($ch);

        $referral_url = array();
        if (!empty($matches[1])) {
            $referral_url = trim($matches[1][0]);
            $referral_url = parse_url($referral_url);
            if (!empty($referral_url['query'])) {
                parse_str($referral_url['query'], $referral_url['query']);
            }
        }

        $this->assertArrayHasKey('host', $referral_url);
        $this->assertEquals($referral_url['host'], 'www.sandbox.paypal.com');

        $this->assertArrayHasKey('query', $referral_url);
        $this->assertArrayHasKey('token', $referral_url['query']);
        $this->assertNotEmpty($referral_url['query']['token']);

        $this->assertArrayHasKey('displayMode', $referral_url['query']);
        $this->assertEquals($referral_url['query']['displayMode'], 'minibrowser');
    }
}
