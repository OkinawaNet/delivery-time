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

use Tygh\Registry;

function fn_exim_pickup_set_company_id($company, $pickup_id)
{
    if (Registry::get('runtime.company_id')) {
        $company_id= Registry::get('runtime.company_id');
    } else {
        $company_id = fn_get_company_id_by_name($company);
    }

    db_query("UPDATE ?:store_locations SET company_id = ?i WHERE store_location_id = ?i ", $company_id , $pickup_id);

    fn_ult_update_share_object($pickup_id, 'store_locations', $company_id);

    return true;
}

function fn_exim_pickup_get_destinations($store_location_id, $destinations, $lang_code) {

    $result = '';

    if (!empty($destinations)) {
        $result = array();
        $destinations = explode(',', $destinations);

        foreach ($destinations as $key => $destination_id) {
            $result[] = fn_get_destination_name($destination_id, $lang_code);
        }

        $result = implode(',', $result);
    }

    return $result;
}

function fn_exim_pickup_set_destinations($destinations, $lang_code) {

    $result = '';

    if (!empty($destinations)) {
        $result = array();
        $destinations = explode(',', $destinations);

        foreach($destinations as $destination) {
            $destination_id = db_get_field("SELECT destination_id FROM ?:destination_descriptions WHERE destination = ?s AND lang_code = ?s", $destination, $lang_code);
            
            if (!empty($destination_id)) {
                $result[] = $destination_id;
            }
        }

        $result = implode(',', $result);
    }

    return $result;

}

function fn_import_check_pickup_company_id($pickup_id, &$object)
{
    if (fn_allowed_for('ULTIMATE') && empty($object['company'])) {
        $pickup_id = reset($pickup_id);
        fn_exim_pickup_set_company_id("", $pickup_id);
    }
}
