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
use Tygh\RusSpsr;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($mode == 'configure') {
    if ($_REQUEST['module'] == 'russian_post') {
        $sending_type = fn_get_schema('russianpost', 'sending_type', 'php', true);
        $sending_categories = fn_get_schema('russianpost', 'sending_categories', 'php', true);
        $sending_packages = fn_get_schema('russianpost', 'sending_packages', 'php', true);

        Tygh::$app['view']->assign('sending_type', $sending_type['send_type']);
        Tygh::$app['view']->assign('sending_categories', $sending_categories['send_categories']);
        Tygh::$app['view']->assign('sending_packages', $sending_packages['send_packages']);
    }
}
