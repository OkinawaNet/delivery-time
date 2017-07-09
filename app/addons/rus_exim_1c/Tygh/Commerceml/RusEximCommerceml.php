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

namespace Tygh\Commerceml;

use Tygh\Settings;
use Tygh\Registry;
use Tygh\Storage;
use Tygh\Enum\ProductFeatures;
use Tygh\Database\Connection;
use Tygh\Commerceml\Logs;

class RusEximCommerceml
{
    /**
     * @var \Tygh\Database\Connection $db Database connection
     */
    protected $db;
    protected $log;

    public $path_file = '';
    public $url;
    public $path_commerceml;
    public $url_commerceml;
    public $url_images;
    public $default_category;
    public $company_id = 0;
    public $cml;
    public $s_commerceml;
    public $count_import;
    public $user_data = array();
    public $import_params = array();

    public $categories_commerceml = array();
    public $features_commerceml = array();

    public function __construct(Connection $db, Logs $log, $path_commerceml)
    {
        $this->db = $db;
        $this->log = $log;

        $this->path_commerceml = $path_commerceml;
        $this->path_file = 'exim/1C_' . date('dmY') . '/';
    }

    public function addMessageLog($message)
    {
        $this->log->write("Data : " . date("d-m-Y h:i:s") . " - " . $message);
    }

    public function showMessageError($message)
    {
        $this->addMessageLog($message);

        header('WWW-Authenticate: Basic realm="Authorization required"');
        header('HTTP/1.0 401 Unauthorized');
        fn_echo($message);
    }

    public function getParamsCommerceml()
    {
        $this->cml = fn_get_schema('cml_fields', 'fields_names');
        $this->s_commerceml = Registry::get('addons.rus_exim_1c');

        return array($this->cml, $this->s_commerceml);
    }

    public function checkParameterFileUpload()
    {
        $message = "";
        $log_message = "";

        if ($this->s_commerceml['status'] != 'A') {
            $message = "Addon Commerceml disabled";
        }

        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $_data['user_login'] = $_SERVER['PHP_AUTH_USER'];

            list($status, $user_data, $user_login, $password, $salt) = fn_auth_routines($_data, array());

            $this->import_params['user_data'] = $user_data;

            if ($user_login != $_SERVER['PHP_AUTH_USER'] || empty($user_data['password']) || $user_data['password'] != fn_generate_salted_password($_SERVER['PHP_AUTH_PW'], $salt)) {
                $message = "\n Error in login or password user";
            }

            if (!$this->checkPatternPermissionsCommerceml($user_data)) {
                $message = "\n Privileges for user not setted";
            }

            $log_message = $this->getCompanyStore($user_data);

        } else {
            $message = "\n Enter login and password user";
        }

        if (!empty($message) || !empty($log_message)) {
            $this->showMessageError($message);
            $this->addMessageLog($log_message);

            return true;
        }

        return false;
    }

    public function exportDataCheckauth($service_exchange)
    {
        $this->addMessageLog("Send data checkauth: " . \Tygh::$app['session']->getName());

        fn_echo("success\n");

        if (!empty($service_exchange) && $service_exchange != 'exim_cml') {
            fn_echo($service_exchange . "\n");
        } else {
            fn_echo(\Tygh::$app['session']->getName() . "\n");
        }

        fn_echo(\Tygh::$app['session']->getID());

        return "success";
    }

    public function exportDataInit()
    {
        $data_init = '';
        $this->addMessageLog("Send file limit: " . FILE_LIMIT);

        $data_init = "zip=no";
        fn_echo("zip=no\n");
        fn_echo("file_limit=" . FILE_LIMIT . "\n");

        return $data_init;
    }

    /**
     * Generates URLs for the files and images uploaded from accounting systems.
     */
    public function getDirCommerceML()
    {
        $data_path = $this->path_file;

        $this->path_commerceml = fn_get_files_dir_path() . $data_path;
        $this->url_commerceml = Registry::get('config.http_location') . '/' . fn_get_rel_dir($this->path_commerceml);

        if (!empty($data_path)) {
            $this->url_images = Storage::instance('images')->getAbsolutePath('from_1c/');
        } else {
            $this->url_images = fn_get_files_dir_path() . 'from_1c/';
        }

        return array($this->path_commerceml, $this->url_commerceml, $this->url_images);
    }

    public function checkPatternPermissionsCommerceml($user_data)
    {
        if (empty($user_data['usergroups'])) {
            return true;
        }

        foreach ($user_data['usergroups'] as $usergroup) {
            $privilege = $this->db->getField("SELECT privilege FROM ?:usergroup_privileges WHERE usergroup_id = ?i AND privilege = 'exim_1c'", $usergroup['usergroup_id']);

            if ((!empty($privilege)) && ($usergroup['status'] == 'A')) {
                return true;
            }
        }

        return false;
    }

    public function getCompanyStore($user_data)
    {
        $log_message = "";
        if (PRODUCT_EDITION == 'ULTIMATE') {
            if (Registry::get('runtime.simple_ultimate')) {
                $this->company_id = Registry::get('runtime.forced_company_id');
            } else {
                if ($user_data['company_id'] == 0) {
                    $log_message = "For import used store administrator";
                    fn_echo('SHOP IS NOT SIMPLE');
                } else {
                    $this->company_id = $user_data['company_id'];
                    Registry::set('runtime.company_id', $this->company_id);
                }
            }

        } elseif ($user_data['user_type'] == 'V') {
            if ($user_data['company_id'] == 0) {
                $log_message = "For import used store administrator";
                fn_echo('SHOP IS NOT SIMPLE');
            } else {
                $this->company_id = $user_data['company_id'];
                Registry::set('runtime.company_id', $this->company_id);
            }

        } else {
            Registry::set('runtime.company_id', $this->company_id);
        }

        return $log_message;
    }

    public function getCompanySettings()
    {
        $company_settings = Settings::instance()->getValues('rus_exim_1c', 'ADDON', false, $this->company_id);

        if (!empty($company_settings)) {
            $this->s_commerceml = $company_settings;
        }

        return $this->s_commerceml;
    }

    public function getFileCommerceml($filename)
    {
        $text_message = "Parsing file data " . $filename;

        $xml = @simplexml_load_file($this->path_commerceml . $filename);

        if ($xml === false) {
            $text_message .= "\n Can not read file " . $filename;

            return array('', false, $text_message);
        }

        return array($xml, true, $text_message);
    }

    public function xmlCheckValidate($file_path)
    {
        $t_commerceml = $t_product = false;
        $xml_validate = true;
        $xml = new \XMLReader();
        if (file_exists($file_path) && ($xml->open($file_path)) && (filesize($file_path) != 0)) {
            while (@$xml->read()) {
                if($xml->nodeType == \XMLReader::END_ELEMENT){
                    if ($xml->name === $this->cml['commerceml']) {
                        $t_commerceml = true;
                    }
                    if (($xml->name === $this->cml['catalog']) || ($xml->name === $this->cml['packages']) || ($xml->name === $this->cml['document'])) {
                        $t_product = true;
                    }
                }
            }

            if (!$t_commerceml || !$t_product) {
                $xml_validate = false;
            }
        }

        return $xml_validate;
    }

    public function createImportFile($filename)
    {
        $this->addMessageLog("Loadding data file " . $filename);

        $file_mode = 'w';
        list($path_commerceml, $url_commerceml, $url_images) = $this->getDirCommerceML();

        if (!is_dir($path_commerceml)) {
            fn_mkdir($path_commerceml);
            @chmod($path_commerceml, 0777);
        }
        $file_path = $path_commerceml . $filename;

        $xml_validate = $this->xmlCheckValidate($file_path);

        if ($this->isFileProductImage($filename)) {
            if (!is_dir($url_images)) {
                fn_mkdir($url_images);
            }
            $file_path = $url_images . $filename;
        }

        $export_data = file_get_contents('php://input');
        if ((!$xml_validate) || empty($export_data)) {
            $file_mode = 'a';
        }

        if ($this->checkFileDescription($filename)) {
            $file_mode = 'w';
        }

        $file = @fopen($file_path, $file_mode);
        if (!$file) {
            $this->addMessageLog("File " . $filename . " can not create");
            return false;
        }
        fwrite($file, $export_data);
        fclose($file);
        @chmod($file_path, 0777);

        return true;
    }

    public function getCountImportElements($import_data)
    {
        $count_imports = 0;

        if (!isset(\Tygh::$app['session']['exim_1c']['count_imports'])) {
            \Tygh::$app['session']['exim_1c']['count_imports'] = 0;
        }

        if (!empty($import_data->{$this->cml['classifier']}->{$this->cml['properties']})) {
            $count_imports += substr_count($import_data->{$this->cml['classifier']}->{$this->cml['properties']}->asXML(), '<' . $this->cml['property'] . '>');
        }

        if (!empty($import_data -> {$this->cml['catalog']} -> {$this->cml['products']})) {
            $count_imports += substr_count($import_data -> {$this->cml['catalog']} -> {$this->cml['products']}->asXML(), '<' . $this->cml['product'] . '>');
        }

        return $count_imports;
    }

    public function checkCountImportElements($count_imports, $import_param)
    {
        if ($count_imports <= \Tygh::$app['session']['exim_1c'][$import_param]) {
            return true;
        }

        if ($count_imports - \Tygh::$app['session']['exim_1c'][$import_param] < COUNT_1C_IMPORT) {
            return true;
        }

        if ($count_imports < COUNT_1C_IMPORT) {
            return true;
        }

        return false;
    }

    public function initProcessImportData($import_param, $service_exchange)
    {
        $import_pos = 0;
        $pos_start = 0;

        if ($service_exchange == '') {
            if (isset(\Tygh::$app['session']['exim_1c'][$import_param])) {
                $pos_start = \Tygh::$app['session']['exim_1c'][$import_param];
            }
        } else {
            \Tygh::$app['session']['exim_1c'][$import_param] = 0;
        }

        return array($import_pos, $pos_start);
    }

    public function checkProcessImportData($_import, $property, $import_param, &$import_pos, $progress = false)
    {
        if (substr_count($_import->asXML(), '<' . $property . '>') == \Tygh::$app['session']['exim_1c']['count_imports'] + \Tygh::$app['session']['exim_1c']['f_count_imports']) {
            \Tygh::$app['session']['exim_1c'][$import_param] = 1;

            if ($progress) {
                $import_pos = 0;
            }
        }
    }

    public function finishImportData($progress, $import_param, $import_pos, $service_exchange, $manual)
    {
        if ($service_exchange == '') {
            if ($progress) {
                \Tygh::$app['session']['exim_1c'][$import_param] = $import_pos;
                \Tygh::$app['session']['exim_1c']['count_imports'] += \Tygh::$app['session']['exim_1c']['f_count_imports'];
                fn_echo('processed: ' . \Tygh::$app['session']['exim_1c'][$import_param] . "\n");

                if ($manual) {
                    fn_redirect(Registry::get('config.current_url'));
                    \Tygh::$app['session']['exim_1c'] = array();
                    \Tygh::$app['session']['exim_1c']['f_count_imports'] = 0;
                }
            }
        } else {
            fn_echo("success\n");
        }
    }

    public function importDataProductFile($import_data)
    {
        $this->addMessageLog("Started import date to file import.xml, parameter service_exchange = '" . $this->import_params['service_exchange'] . "'");

        $cml = $this->cml;
        $import_file = true;

        if (!isset(\Tygh::$app['session']['exim_1c'])) {
            \Tygh::$app['session']['exim_1c'] = array();
            \Tygh::$app['session']['exim_1c']['f_count_imports'] = 0;
        }

        $progress = false;

        if ($this->import_params['service_exchange'] == '') {
            $count_imports = $this->getCountImportElements($import_data);
            \Tygh::$app['session']['exim_1c']['f_count_imports'] = 0;

            if ($this->checkCountImportElements($count_imports, 'count_imports')) {
                fn_echo("success\n");
            } else {
                fn_echo("progress\n");
                $import_file = false;
            }

        }

        if (!empty(\Tygh::$app['session']['exim_1c']['categories_commerceml'])) {
            $this->categories_commerceml = \Tygh::$app['session']['exim_1c']['categories_commerceml'];
        }

        if (!empty(\Tygh::$app['session']['exim_1c']['features_commerceml'])) {
            $this->features_commerceml = \Tygh::$app['session']['exim_1c']['features_commerceml'];
        }

        if (empty(\Tygh::$app['session']['exim_1c']['import_products']) && empty(\Tygh::$app['session']['exim_1c']['import_features'])) {

            if (($this->s_commerceml['exim_1c_allow_import_categories'] == 'Y') && !empty($import_data->{$cml['classifier']}->{$cml['groups']})) {

                $this->importCategoriesFile($import_data->{$cml['classifier']}->{$cml['groups']}, $this->import_params);
            }
        }

        if (($this->s_commerceml['exim_1c_allow_import_features'] == 'Y') && !empty($import_data->{$cml['classifier']}->{$cml['properties']})) {

            if (!isset(\Tygh::$app['session']['exim_1c']['import_features'])) {

                list($import_pos, $pos_start) = $this->initProcessImportData('features_products', $this->import_params['service_exchange']);

                $this->importFeaturesFile($import_data->{$cml['classifier']}->{$cml['properties']}, $this->import_params, $pos_start, $import_pos, $progress);

                $this->checkProcessImportData($import_data->{$cml['classifier']}->{$cml['properties']}, $cml['property'], 'import_features', $import_pos, $progress);

                $this->finishImportData($progress, 'features_products', $import_pos, $this->import_params['service_exchange'], $this->import_params['manual']);
            }

        } else {
            \Tygh::$app['session']['exim_1c']['import_features'] = 1;
        }

        if (isset(\Tygh::$app['session']['exim_1c']['import_features']) && !$progress) {
            $import_pos = 0;

            if (isset($import_data -> {$cml['catalog']} -> {$cml['products']})) {
                if (!empty(\Tygh::$app['session']['exim_1c']['categories_commerceml'])) {
                    $this->categories_commerceml = \Tygh::$app['session']['exim_1c']['categories_commerceml'];
                }

                if (!empty(\Tygh::$app['session']['exim_1c']['features_commerceml'])) {
                    $this->features_commerceml = \Tygh::$app['session']['exim_1c']['features_commerceml'];
                }

                $this->importProductsFile($import_data -> {$cml['catalog']} -> {$cml['products']}, $this->import_params, $import_pos);

                if ($import_file) {
                    \Tygh::$app['session']['exim_1c'] = array();
                    \Tygh::$app['session']['exim_1c']['f_count_imports'] = 0;
                }

            } else {
                fn_echo("success\n");
            }
        }

        if ($import_file) {
            \Tygh::$app['session']['exim_1c'] = array();
            \Tygh::$app['session']['exim_1c']['f_count_imports'] = 0;
        }
    }

    public function importCategoriesFile($data_categories, $import_params, $parent_id = 0)
    {
        $categories_import = array();
        $cml = $this->cml;
        $default_category = $this->s_commerceml['exim_1c_default_category'];
        $link_type = $this->s_commerceml['exim_1c_import_type_categories'];
        if (isset($data_categories -> {$cml['group']})) {
            foreach ($data_categories -> {$cml['group']} as $_group) {
                $category_ids = $this->getCompanyIdByLinkType($link_type, $_group);

                $category_id = 0;
                if (!empty($category_ids)) {
                    $category_id = $this->db->getField("SELECT category_id FROM ?:categories WHERE category_id IN (?a) AND company_id = ?i", $category_ids, $this->company_id);
                }

                if (empty($category_id)) {
                    $this->addMessageLog("New category: " . strval($_group -> {$this->cml['name']}));
                }

                $category_data = $this->getDataCategoryByFile($_group, $category_id, $parent_id, $import_params['lang_code']);

                if ($import_params['user_data']['user_type'] != 'V') {
                    $category_id = fn_update_category($category_data, $category_id, $import_params['lang_code']);
                    $this->addMessageLog("Add category: " . $category_data['category']);
                } else {
                    $category_id = $default_category;
                    $id = $this->db->getField("SELECT category_id FROM ?:category_descriptions WHERE lang_code = ?s AND category = ?s", $import_params['lang_code'], strval($_group -> {$cml['name']}));

                    if (!empty($id)) {
                        $category_id = $id;
                    }
                }

                $categories_import[strval($_group -> {$cml['id']})] = $category_id;
                if (isset($_group -> {$cml['groups']} -> {$cml['group']})) {
                    $this->importCategoriesFile($_group -> {$cml['groups']}, $import_params, $category_id);
                }
            }

            if (!empty($this->categories_commerceml)) {
                $_categories_commerceml = $this->categories_commerceml;
                $this->categories_commerceml = fn_array_merge($_categories_commerceml, $categories_import);
            } else {
                $this->categories_commerceml = $categories_import;
            }

            if (!empty($this->categories_commerceml)) {
                \Tygh::$app['session']['exim_1c']['categories_commerceml'] = $this->categories_commerceml;
            }
        }
    }

    public function getCompanyIdByLinkType($link_type, $_group)
    {
        if ($link_type == 'name') {
            $category_ids = $this->db->getColumn('SELECT category_id FROM ?:category_descriptions WHERE category = ?s', strval($_group -> {$this->cml['name']}));

        } else {
            $category_ids = $this->db->getColumn('SELECT category_id FROM ?:categories WHERE external_id = ?s', strval($_group -> {$this->cml['id']}));
        }

        return $category_ids;
    }

    public function getDataCategoryByFile($_group, $category_id, $parent_id, $lang_code)
    {
        $category_data = array(
            'category' => strval($_group -> {$this->cml['name']}),
            'lang_code' => $lang_code,
            'timestamp' => time(),
            'company_id' => $this->company_id,
            'external_id' => strval($_group -> {$this->cml['id']})
        );

        if (empty($category_id)) {
            $category_data['status'] = 'A';
            $category_data['parent_id'] = $parent_id;
        }

        return $category_data;
    }

    public function importFeaturesFile($data_features, $import_params, $data_pos_start, &$import_pos, &$progress)
    {
        $cml = $this->cml;
        $features_import = array();
        if (isset($data_features -> {$cml['property']})) {
            $promo_text = trim($this->s_commerceml['exim_1c_property_product']);
            $shipping_params = $this->getShippingFeatures();
            $features_list = fn_explode("\n", $this->s_commerceml['exim_1c_features_list']);
            $deny_or_allow_list = $this->s_commerceml['exim_1c_deny_or_allow'];
            $company_id = $this->company_id;
            foreach ($data_features -> {$cml['property']} as $_feature) {
                if ($import_params['service_exchange'] == '') {
                    $import_pos++;

                    if ($import_pos % COUNT_IMPORT_PRODUCT == 0) {
                        fn_echo('imported: ' . COUNT_IMPORT_PRODUCT . "\n");
                    }

                    if ($import_pos < $data_pos_start) {
                        continue;
                    }

                    if (\Tygh::$app['session']['exim_1c']['f_count_imports'] >= COUNT_1C_IMPORT) {
                        $progress = true;
                        break;
                    }
                    \Tygh::$app['session']['exim_1c']['f_count_imports']++;
                }

                $_variants = array();
                $feature_data = array();
                $feature_name = strval($_feature -> {$cml['name']});

                if ($deny_or_allow_list == 'do_not_import') {
                    if (in_array($feature_name, $features_list)) {
                        $this->addMessageLog("Feature is not added (do not import): " . $feature_name);
                        continue;
                    }
                } elseif ($deny_or_allow_list == 'import_only') {
                    if (!in_array($feature_name, $features_list)) {
                        $this->addMessageLog("Feature is not added (import only): " . $feature_name);
                        continue;
                    }
                }

                $feature_id = $this->db->getField("SELECT feature_id FROM ?:product_features WHERE external_id = ?s", strval($_feature -> {$cml['id']}));
                $new_feature = false;

                if (empty($feature_id)) {
                    $new_feature = true;
                    $feature_id = 0;
                }

                $f_variants = array();
                if (!empty($_feature -> {$cml['variants_values']})) {
                    $_feature_data = $_feature -> {$cml['variants_values']} -> {$cml['directory']};
                    foreach ($_feature_data as $_variant) {
                        $_variants[strval($_variant -> {$cml['id_value']})]['id'] = strval($_variant -> {$cml['id_value']});
                        $_variants[strval($_variant -> {$cml['id_value']})]['value'] = strval($_variant -> {$cml['value']});
                        $f_variants[strval($_variant -> {$cml['id_value']})]['external_id'] = strval($_variant -> {$cml['id_value']});
                        $f_variants[strval($_variant -> {$cml['id_value']})]['variant'] = strval($_variant -> {$cml['value']});
                    }
                }

                $feature_data = $this->dataFeatures($feature_name, $feature_id, strval($_feature -> {$cml['type_field']}), $this->s_commerceml['exim_1c_used_brand'], $this->s_commerceml['exim_1c_property_for_manufacturer'], strval($_feature -> {$cml['id']}));

                if ($this->displayFeatures($feature_name, $shipping_params)) {
                    if ($promo_text != $feature_name) {

                        if (!empty($f_variants)) {
                            $feature_data['variants'] = $f_variants;
                        }

                        $feature_id = fn_update_product_feature($feature_data, $feature_id);
                        $this->addMessageLog("Feature is added: " . $feature_name);

                        if ($new_feature) {
                            $this->db->query("INSERT INTO ?:ult_objects_sharing VALUES ($company_id, $feature_id, 'product_features')");
                        }
                    } else {
                        fn_delete_feature($feature_id);
                        $feature_id = 0;
                    }
                } else {
                    fn_delete_feature($feature_id);
                    $feature_id = 0;
                }
                $features_import[strval($_feature -> {$cml['id']})]['id'] = $feature_id;
                $features_import[strval($_feature -> {$cml['id']})]['name'] = $feature_name;
                $features_import[strval($_feature -> {$cml['id']})]['type'] = $feature_data['feature_type'];

                if (!empty($_variants)) {
                    $features_import[strval($_feature -> {$cml['id']})]['variants'] = $_variants;
                }
            }
        }

        $feature_data = array();
        if ($this->s_commerceml['exim_1c_used_brand'] == 'field_brand') {
            $company_id = $this->company_id;
            $feature_id = $this->db->getField("SELECT feature_id FROM ?:product_features WHERE external_id = ?s AND company_id = ?i", "brand1c", $company_id);
            $new_feature = false;

            if (empty($feature_id)) {
                $new_feature = true;
                $feature_id = 0;
            }

            $feature_data = $this->dataFeatures($cml['brand'], $feature_id, ProductFeatures::EXTENDED, $this->s_commerceml['exim_1c_used_brand'], $this->s_commerceml['exim_1c_property_for_manufacturer'], "brand1c");
            $_feature_id = fn_update_product_feature($feature_data, $feature_id);
            $this->addMessageLog("Feature brand is added");

            if ($feature_id == 0) {
                $this->db->query("INSERT INTO ?:ult_objects_sharing VALUES ($company_id, $_feature_id, 'product_features')");
            }

            $features_import['brand1c']['id'] = (!empty($feature_id)) ? $feature_id : $_feature_id;
            $features_import['brand1c']['name'] = $cml['brand'];
        }

        if (!empty($features_import)) {
            if (!empty($this->features_commerceml)) {
                $_features_commerceml = $this->features_commerceml;
                $this->features_commerceml = fn_array_merge($_features_commerceml, $features_import);
            } else {
                $this->features_commerceml = $features_import;
            }
        }

        if (!empty($this->features_commerceml)) {
            \Tygh::$app['session']['exim_1c']['features_commerceml'] = $this->features_commerceml;
        }

        if ($import_params['service_exchange'] == '') {
            if (\Tygh::$app['session']['exim_1c']['f_count_imports'] + 1 >= COUNT_1C_IMPORT) {
                $progress = true;
            }
        }
    }

    public function dataFeatures($feature_name, $feature_id, $f_type, $used_brand, $property_for_manufacturer, $external_id)
    {
        $feature_type = $this->db->getField("SELECT feature_type FROM ?:product_features WHERE external_id = ?s", $external_id);

        if (empty($feature_type)) {
            $feature_type = ProductFeatures::TEXT_SELECTBOX;
        }

        $data = array(
            'variants' => array(),
            'description' => $feature_name,
            'company_id' => $this->company_id,
            'external_id' => $external_id,
            'feature_type' => $feature_type
        );

        $feature_type = ProductFeatures::TEXT_SELECTBOX;
        if ($f_type == 'Число') {
            $feature_type = ProductFeatures::NUMBER_SELECTBOX;
        }

        if ($used_brand == 'feature_product') {
            $brand_feature = trim($property_for_manufacturer);
            if (!empty($brand_feature) && ($brand_feature == $feature_name)) {
                $feature_type = ProductFeatures::EXTENDED;
                $data['feature_type'] = $feature_type;
            }
        }

        if ($f_type == ProductFeatures::EXTENDED) {
            $feature_type = ProductFeatures::EXTENDED;
            $data['feature_type'] = $feature_type;
        }

        if (empty($feature_id)) {
            $data['position'] = 0;
            $data['parent_id'] = 0;
            $data['prefix'] = '';
            $data['suffix'] = '';
            $data['display_on_catalog'] = "Y";
            $data['display_on_product'] = "Y";
            $data['feature_type'] = $feature_type;
        }

        return $data;
    }

    public function displayFeatures($feature_name, $shipping_params)
    {
        foreach ($shipping_params as $s_param) {
            if (in_array($feature_name, $s_param['fields'])) {
                if ($s_param['display'] == 'Y') {
                    return true;
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    public function getShippingFeatures()
    {
        return array(
            array(
                'name' => 'weight_property',
                'fields' => fn_explode("\n", $this->s_commerceml['exim_1c_weight_property']),
                'display' => $this->s_commerceml['exim_1c_display_weight'],
            ),
            array(
                'name' => 'free_shipping',
                'fields' => fn_explode("\n", $this->s_commerceml['exim_1c_free_shipping']),
                'display' => $this->s_commerceml['exim_1c_display_free_shipping'],
            ),
            array(
                'name' => 'shipping_cost',
                'fields' => fn_explode("\n", $this->s_commerceml['exim_1c_shipping_cost']),
                'display' => '',
            ),
            array(
                'name' => 'number_of_items',
                'fields' => fn_explode("\n", $this->s_commerceml['exim_1c_number_of_items']),
                'display' => '',
            ),
            array(
                'name' => 'box_length',
                'fields' => fn_explode("\n", $this->s_commerceml['exim_1c_box_length']),
                'display' => '',
            ),
            array(
                'name' => 'box_width',
                'fields' => fn_explode("\n", $this->s_commerceml['exim_1c_box_width']),
                'display' => '',
            ),
            array(
                'name' => 'box_height',
                'fields' => fn_explode("\n", $this->s_commerceml['exim_1c_box_height']),
                'display' => '',
            ),
        );
    }

    public function importProductsFile($data_products, $import_params, $import_pos = 0)
    {
        $old_features_commerceml = $this->db->getHash(
            "SELECT a.external_id, a.feature_id as id, a.feature_type as type, b.description as name"
            . " FROM ?:product_features as a"
            . " LEFT JOIN ?:product_features_descriptions as b"
                . " ON a.feature_id = b.feature_id"
            . " WHERE external_id <> '' AND lang_code = ?s",
            'external_id',
            CART_LANGUAGE
        );

        if (!empty($old_features_commerceml)) {
            $old_variants_commerceml = $this->db->getMultiHash(
                "SELECT a.feature_id, a.external_id as id, b.variant as value"
                . " FROM ?:product_feature_variants as a"
                . " LEFT JOIN ?:product_feature_variant_descriptions as b"
                    . " ON a.variant_id = b.variant_id"
                . " WHERE external_id <> '' AND lang_code = ?s",
                array('feature_id', 'id'),
                CART_LANGUAGE
            );

            foreach ($old_features_commerceml as $external_id => $_feature_commerceml) {
                if (!empty($old_variants_commerceml[$_feature_commerceml['id']])) {
                    $old_features_commerceml[$external_id]['variants'] = $old_variants_commerceml[$_feature_commerceml['id']];
                }
            }
        }

        if (!empty($this->features_commerceml)) {
            $this->features_commerceml = fn_array_merge($old_features_commerceml, $this->features_commerceml);
        } else {
            $this->features_commerceml = $old_features_commerceml;
        }

        if (!empty($this->categories_commerceml)) {
            $categories_commerceml = $this->categories_commerceml;
        } else {
            $categories_commerceml = $this->db->getSingleHash(
                "SELECT external_id, category_id"
                . " FROM ?:categories"
                . " WHERE external_id <> ''",
                array('external_id', 'category_id')
            );
        }

        list(, $product_pos_start) = $this->initProcessImportData('import_products', $import_params['service_exchange']);

        $offers_pos = $import_pos;
        $progress = false;

        foreach ($data_products -> {$this->cml['product']} as $_product) {
            if ($import_params['service_exchange'] == '') {
                $offers_pos++;

                if ($offers_pos % COUNT_IMPORT_PRODUCT == 0) {
                    fn_echo('imported: ' . COUNT_IMPORT_PRODUCT . "\n");
                }

                if ($offers_pos < $product_pos_start) {
                    continue;
                }

                if (\Tygh::$app['session']['exim_1c']['f_count_imports'] >= COUNT_1C_IMPORT) {
                    $progress = true;
                    break;
                }

                \Tygh::$app['session']['exim_1c']['f_count_imports']++;
            }

            $log_message = $this->addDataProductByFile($_product, $this->cml, $categories_commerceml, $import_params);
            $this->addMessageLog($log_message);
        }

        $this->finishImportData($progress, 'import_products', $offers_pos - $import_pos, $import_params['service_exchange'], $import_params['manual']);

        if (!$progress) {
            unset(\Tygh::$app['session']['exim_1c']['import_products']);
        }
    }

    public function startSessionUpload($param_session, $product_count)
    {
        if (!isset(\Tygh::$app['session']['exim_1c'][$param_session])) {
            $product_pos_start = 0;
        } else {
            $product_pos_start = \Tygh::$app['session']['exim_1c'][$param_session];
        }

        if ($product_count > COUNT_1C_IMPORT) {
            if (($product_count - $product_pos_start) > COUNT_1C_IMPORT) {
                fn_echo("progress\n");
            } else {
                fn_echo("success\n");
            }
        } else {
            fn_echo("success\n");
        }

        return $product_pos_start;
    }

    public function finishSessionUpload($progress, $param_session, $upload_count)
    {
        if ($progress) {
            if (!isset(\Tygh::$app['session']['exim_1c'])) {
                \Tygh::$app['session']['exim_1c'] = array();
            }

            \Tygh::$app['session']['exim_1c'][$param_session] = $upload_count;
            fn_echo('processed: ' . \Tygh::$app['session']['exim_1c'][$param_session] . "\n");

            if ($this->import_params['manual']) {
                fn_redirect(Registry::get('config.current_url'));
            }

        } else {
            fn_echo("success\n");
            unset(\Tygh::$app['session']['exim_1c'][$param_session]);
        }
    }

    public function addDataProductByFile($_product, $cml, $categories_commerceml, $import_params)
    {
        $allow_import_features = $this->s_commerceml['exim_1c_allow_import_features'];
        $add_tax = $this->s_commerceml['exim_1c_add_tax'];
        $schema_version = $this->s_commerceml['exim_1c_schema_version'];
        $link_type = $this->s_commerceml['exim_1c_import_type'];
        $log_message = "";

        if (empty($_product -> {$cml['name']})) {
            $log_message = "Name is not set for product with id: " . $_product -> {$cml['id']};

            return $log_message;
        }

        list($guid_product, $combination_id) = $this->getProductIdByFile($_product -> {$cml['id']});

        $product_data = $this->getProductDataByLinkType($link_type, $_product, $cml);

        $product_update = !empty($product_data['update_1c']) ? $product_data['update_1c'] : 'Y';
        $product_id = (!empty($product_data['product_id'])) ? $product_data['product_id'] : 0;

        if (!empty($_product -> attributes() -> {$cml['status']}) && strval($_product -> attributes() -> {$cml['status']}) == $cml['delete']) {
            if ($product_id != 0) {
                fn_delete_product($product_id);
                $log_message = "\n Deleted product: " . strval($_product -> {$cml['name']});
            }

            return $log_message;
        }

        if (!empty($_product -> {$cml['status']}) && strval($_product -> {$cml['status']}) == $cml['delete']) {
            if ($product_id != 0) {
                fn_delete_product($product_id);
                $log_message = "\n Deleted product: " . strval($_product -> {$cml['name']});
            }

            return $log_message;
        }

        if ($this->checkUploadProduct($product_id, $product_update)) {
            $product = $this->dataProductFile($_product, $product_id, $guid_product, $categories_commerceml, $import_params);

            if ($product_id == 0) {
                $this->newDataProductFile($product, $import_params);
            }

            $this->db->query("UPDATE ?:products SET company_id = ?i WHERE product_id = ?i", $this->company_id, $product_id);

            if ((isset($_product -> {$cml['properties_values']} -> {$cml['property_values']}) || isset($_product -> {$cml['manufacturer']})) && ($allow_import_features == 'Y') && (!empty($this->features_commerceml))) {
                $product = $this->dataProductFeatures($_product, $product, $import_params);
            }

            if (isset($_product -> {$cml['value_fields']} -> {$cml['value_field']})) {
                $this->dataProductFields($_product, $product);
            }

            if (isset($_product -> {$cml['taxes_rates']}) && ($add_tax == 'Y')) {
                $product['tax_ids'] = $this->addProductTaxes($_product -> {$cml['taxes_rates']}, $product_id);
            }

            $product_id = fn_update_product($product, $product_id, $import_params['lang_code']);

            $log_message = "\n Added product: " . $product['product'] . " commerceml_id: " . strval($_product -> {$cml['id']});

            // Import product features
            if (!empty($product['features'])) {
                $variants_data['product_id'] = $product_id;
                $variants_data['lang_code'] = $import_params['lang_code'];
                $variants_data['category_id'] = $product['category_id'];
                $this->addProductFeatures($product['features'], $variants_data, $import_params);
            }

            // Import images
            $image_main = true;
            if (isset($_product -> {$cml['image']})) {
                foreach ($_product -> {$cml['image']} as $image) {
                    $filename = fn_basename(strval($image));
                    $this->addProductImage($filename, $image_main, $product_id, $import_params);
                    $image_main = false;
                }
            }

            // Import combinations
            if (isset($_product -> {$cml['product_features']} -> {$cml['product_feature']}) && $schema_version == '2.07') {
                $this->addProductCombinationsNewSchema($_product -> {$cml['product_features']} -> {$cml['product_feature']}, $product_id, $import_params, $combination_id);
            }
        }

        return $log_message;
    }

    public function checkUploadProduct($product_id, $product_update)
    {
        $upload_product = false;
        $type_import_products = $this->s_commerceml['exim_1c_import_products'];

        if ($type_import_products == 'all_products') {
            $upload_product = true;
        }

        if ($product_id == 0 && ($type_import_products == 'new_products' || $type_import_products == 'new_update_products')) {
            $upload_product = true;
        }

        if ($product_id != 0 && $type_import_products == 'update_products') {
            $upload_product = true;
        }

        if ($upload_product && ($product_update == 'Y' || $product_id == 0)) {
            $upload_product = true;
        }

        return $upload_product;
    }

    public function getProductIdByFile($commerceml_id)
    {
        $ids = fn_explode('#', $commerceml_id);
        $product_id = array_shift($ids);
        $combination_id = 0;
        if (!empty($ids)) {
            $combination_id = reset($ids);
        }

        return array($product_id, $combination_id);
    }

    public function getProductDataByLinkType($link_type, $_product, $cml)
    {
        list($guid_product, $combination_id) = $this->getProductIdByFile($_product -> {$cml['id']});

        $article = strval($_product -> {$cml['article']});
        $barcode = strval($_product -> {$cml['bar']});

        $product_data = array();
        if ($link_type == 'article') {
            $product_data = $this->db->getRow(
                "SELECT product_id, update_1c FROM ?:products WHERE product_code = ?s",
                $article
            );

        } elseif ($link_type == 'barcode') {
            $product_data = $this->db->getRow(
                "SELECT product_id, update_1c FROM ?:products WHERE product_code = ?s",
                $barcode
            );

        } else {
            $product_data = $this->db->getRow(
                "SELECT product_id, update_1c FROM ?:products WHERE external_id = ?s",
                $guid_product
            );
        }

        return $product_data;
    }

    public function dataProductFile($d_product, $product_id, $external_id, $categories_commerceml, $import_params)
    {
        $cml = $this->cml;
        $import_product_name = $this->s_commerceml['exim_1c_import_product_name'];
        $import_product_code = $this->s_commerceml['exim_1c_import_product_code'];
        $import_full_description = $this->s_commerceml['exim_1c_import_full_description'];
        $import_short_description = $this->s_commerceml['exim_1c_import_short_description'];
        $import_page_title = $this->s_commerceml['exim_1c_page_title'];
        $default_category = $this->s_commerceml['exim_1c_default_category'];
        $allow_import_categories = $this->s_commerceml['exim_1c_allow_import_categories'];

        $product = array();
        $product['external_id'] = $external_id;

        $requisites = $d_product -> {$cml['value_fields']} -> {$cml['value_field']};
        list($full_name, $product_code, $html_description) = $this->getAdditionalDataProduct($requisites, $cml);

        if (!empty($d_product -> {$cml['image']})) {
            foreach ($d_product -> {$cml['image']} as $file_description) {
                $filename = fn_basename(strval($file_description));
                if ($this->checkFileDescription($filename)){
                    $html_description = @file_get_contents($this->path_commerceml . $filename);
                }
            }
        }

        $product['product'] = $this->getProductNameByType($import_product_name, $d_product, $full_name, $cml);
        $product['product_code'] = $this->getProductCodeByTypeCode($import_product_code, $d_product, $product_code, $cml);

        if ($import_full_description != 'not_import') {
            $product['full_description'] = $this->getProductDescriptionByType($import_full_description, $d_product, $html_description, $full_name, $cml);
        }

        if ($import_short_description != 'not_import') {
            $product['short_description'] = $this->getProductDescriptionByType($import_short_description, $d_product, $html_description, $full_name, $cml);
        }

        if ($import_page_title != 'not_import') {
            $product['page_title'] = $this->getProductPageTitle($import_page_title, $d_product, $full_name, $cml);
        }

        $product['company_id'] = ($import_params['user_data']['user_type'] == 'V') ? $import_params['user_data']['company_id'] : $this->company_id;

        $category_id = 0;
        if ($allow_import_categories == 'Y') {
            if (!empty($d_product -> {$cml['groups']} -> {$cml['id']})) {
                $category_id = !empty($categories_commerceml[strval($d_product -> {$cml['groups']} -> {$cml['id']})]) ? $categories_commerceml[strval($d_product -> {$cml['groups']} -> {$cml['id']})] : 0;

                if ($product_id == 0) {
                    $product['main_category'] = $category_id;
                }
            }

        } else {
            if ($product_id == 0) {
                $product['main_category'] = $default_category;
            }
        }

        if (!empty($product_id)) {
            $product['category_ids'] = $this->db->getColumn("SELECT category_id FROM ?:products_categories WHERE product_id = ?i", $product_id);

            if (empty($product['main_category'])) {
                $g_category_id = $this->db->getField("SELECT category_id FROM ?:products_categories WHERE product_id = ?i AND link_type = 'M'", $product_id);

                if ($g_category_id == $category_id) {
                    $_category_id = $this->db->getField("SELECT category_id FROM ?:products_categories WHERE product_id = ?i AND link_type = 'A'", $product_id);

                    $g_category_id = (!empty($_category_id)) ? $_category_id : $g_category_id;
                }
            }

            if ($category_id == 0) {
                $category_id = $this->db->getField("SELECT category_id FROM ?:products_categories WHERE product_id = ?i", $product_id);
                $category_id = $this->db->getField("SELECT category_id FROM ?:categories WHERE category_id = ?i AND company_id = ?i", $category_id, $this->company_id);

                if (!empty($g_category_id)) {
                    $category_id = $g_category_id;
                }
            }
        }

        if ($category_id == 0) {
            if (!empty($default_category)) {
                $category_id = $default_category;
            } else {
                $category_id = $this->getDefaultCategory();
            }
        }

        $product['category_id'] = $category_id;

        if ($category_id != 0) { 
            $product['main_category'] = (!empty($g_category_id)) ? $g_category_id : $category_id;
            $product['category_ids'][] = $category_id;
            $product['category_ids'] = array_unique($product['category_ids']);
        }

        return $product;
    }

    public function getAdditionalDataProduct($requisites, $cml)
    {
        $full_name = $product_code = $html_description = '';

        foreach ($requisites as $requisite) {
            if (strval($requisite -> {$cml['name']}) == $cml['full_name']) {
                $full_name = strval($requisite -> {$cml['value']});
            }
            if (strval($requisite -> {$cml['name']}) == $cml['code']) {
                $product_code = strval($requisite -> {$cml['value']});
            }
            if (strval($requisite -> {$cml['name']}) == $cml['html_description']) {
                $html_description = strval($requisite -> {$cml['value']});
            }
        }

        return array($full_name, $product_code, $html_description);
    }

    public function getProductNameByType($import_product_name, $d_product, $full_name, $cml)
    {
        $product_name = strval($d_product -> {$cml['name']});

        if (($import_product_name == 'full_name') && (!empty($full_name))) {
            $product_name = $full_name;
        }

        return $product_name;
    }

    public function getProductCodeByTypeCode($import_product_code, $d_product, $_code, $cml)
    {
        $article = strval($d_product -> {$cml['article']});
        $product_code = !empty($article) ? $article : '';

        if ($import_product_code == 'code') {
            $product_code = $_code;
        } elseif ($import_product_code == 'bar') {
            $product_code = strval($d_product -> {$cml['bar']});
        }

        return $product_code;
    }

    public function getProductDescriptionByType($type_description, $d_product, $html_description, $full_name, $cml)
    {
        $description = '';

        if ($type_description == 'description') {
            $description = nl2br($d_product -> {$cml['description']});

        } elseif ($type_description == 'html_description') {
            $description = $html_description;

        } elseif ($type_description == 'full_name') {
            $description = $full_name;
        }

        return $description;
    }

    public function getProductPageTitle($import_page_title, $d_product, $full_name, $cml)
    {
        $page_title = '';

        if ($import_page_title == 'name') {
            $page_title = trim($d_product -> {$cml['name']}, " -");

        } elseif ($import_page_title == 'full_name') {
            $page_title = trim($full_name, " -");
        }

        return $page_title;
    }

    public function newDataProductFile(&$product, $import_params)
    {
        $type_import_products = $this->s_commerceml['exim_1c_import_products'];

        $product['price'] = '0.00';
        $product['list_price'] = '0.00';
        $product['lower_limit'] = 1;
        $product['details_layout'] = 'default';
        $product['lang_code'] = $import_params['lang_code'];
        $product['status'] = 'A';
        if ($type_import_products == 'new_products' || $type_import_products == 'new_update_products') {
            $product['status'] = 'N';
        }
    }

    public function checkFileDescription($filename)
    {
        $file_array = fn_explode('.', $filename);
        if (is_array($file_array)) {
            $type = mb_strtolower(array_pop($file_array));
            if (in_array($type, array('txt', 'html'))) {
                return true;
            }
        }

        return false;
    }

    public function getDefaultCategory()
    {
        $default_category = $this->s_commerceml['exim_1c_default_category'];
        $default_category = $this->db->getField("SELECT category_id FROM ?:categories WHERE category_id = ?i", $default_category);
        if (!empty($default_category)) {
            return $default_category;
        } else {
            if (empty($this->default_category)) {
                $category_data = array(
                    'category' => 'Default category',
                    'status' => 'D',
                    'parent_id' => 0,
                    'company_id' => $this->company_id
                );
                $this->default_category = fn_update_category($category_data, 0);
                Registry::set('addons.rus_exim_1c.exim_1c_default_category', $this->default_category);
            }

            return $this->default_category;
        }
    }

    public function addProductFeatures($data_features, $variants_data, $import_params)
    {
        foreach ($data_features as $p_feature) {
            $variant_feature = array_merge($p_feature, $variants_data);

            if (!empty($variants_data['category_id'])) {
                $feature_categories = fn_explode(',', $this->db->getField("SELECT categories_path FROM ?:product_features WHERE feature_id = ?i", $p_feature['feature_id']));
                if (!in_array($variants_data['category_id'], $feature_categories)) {
                    $feature_categories[] = $variants_data['category_id'];
                    $feature_categories = array_diff($feature_categories, array(''));
                    $this->db->query("UPDATE ?:product_features SET categories_path = ?s WHERE feature_id = ?i", implode(',', $feature_categories), $p_feature['feature_id']);
                }
            }

            $this->addFeatureValues($variant_feature);
        }
    }

    public function addFeatureValues($variants_data)
    {
        if (!empty($variants_data['variant_id'])) {
            $variant_id = $this->db->getField(
                "SELECT variant_id"
                . " FROM ?:product_features_values"
                . " WHERE feature_id = ?i AND product_id = ?i",
                $variants_data['feature_id'],
                $variants_data['product_id']
            );

            if (!empty($variant_id)) {
                $this->db->query(
                    "DELETE FROM ?:product_features_values"
                    . " WHERE feature_id = ?i AND product_id = ?i",
                    $variants_data['feature_id'],
                    $variants_data['product_id']
                );
            }

            foreach (fn_get_translation_languages() as $lang_code => $lang_data) {
                $variants_data['lang_code'] = $lang_code;

                db_replace_into('product_features_values', $variants_data);
            }
        }
    }

    public function dataProductFeatures($data_product, $product, $import_params)
    {
        $property_for_promo_text = trim($this->s_commerceml['exim_1c_property_product']);
        $cml = $this->cml;
        $features_commerceml = $this->features_commerceml;
        $product['promo_text'] = '';

        if (!empty($data_product -> {$cml['properties_values']} -> {$cml['property_values']})) {
            foreach ($data_product -> {$cml['properties_values']} -> {$cml['property_values']} as $_feature) {
                $variant_data = array();
                $feature_id = strval($_feature -> {$cml['id']});
                if (!isset($features_commerceml[$feature_id])) {
                    continue;
                }

                if (!isset($_feature -> {$cml['value']}) || trim(strval($_feature -> {$cml['value']})) == '') {
                    continue;
                }

                $p_feature_name = strval($_feature -> {$cml['value']});
                $feature_name = trim($features_commerceml[$feature_id]['name'], " ");
                if (!empty($features_commerceml[$feature_id])) {
                    $product_params = $this->dataShippingParams($p_feature_name, $feature_name);
                    if (!empty($product_params)) {
                        $product = array_merge($product, $product_params);
                    }

                    if (!empty($property_for_promo_text) && ($property_for_promo_text == $feature_name)) {
                        if (!empty($features_commerceml[$feature_id]['variants'])) {
                            $product['promo_text'] = $features_commerceml[$feature_id]['variants'][$p_feature_name]['value'];
                        } else {
                            $product['promo_text'] = $p_feature_name;
                        }
                    }
                }

                if (!empty($features_commerceml[$feature_id]['id'])) {
                    $variant_data['feature_id'] = $features_commerceml[$feature_id]['id'];
                    $variant_data['feature_types'] = $features_commerceml[$feature_id]['type'];
                    $variant_data['feature_type'] = $features_commerceml[$feature_id]['type'];
                    $variant_data['lang_code'] = $import_params['lang_code'];
                    $variant_data['feature_type'] = $features_commerceml[$feature_id]['type'];

                    $d_variants = fn_get_product_feature_data($variant_data['feature_id'], true, false, $import_params['lang_code']);

                    if (!empty($d_variants['feature_id']) && $d_variants['feature_id'] == $variant_data['feature_id']) {
                        $variant_data = $d_variants;
                    }

                    if ($variant_data['feature_type'] == ProductFeatures::NUMBER_SELECTBOX) {
                        $p_feature_name = str_replace(',', '.', $p_feature_name);
                        $variant_data['value_int'] = $p_feature_name;
                    }

                    if ($variant_data['feature_type'] == ProductFeatures::NUMBER_FIELD) {
                        $p_feature_name = str_replace(',', '.', $p_feature_name);
                        $variant_data['value_int'] = $p_feature_name;
                    }

                    $is_id = false;
                    $variant = '';
                    if (!empty($features_commerceml[$feature_id]['variants'])) {
                        foreach ($features_commerceml[$feature_id]['variants'] as $_variant) {
                            if ($p_feature_name == $_variant['id']) {
                                $variant = $_variant['value'];
                                $is_id = true;
                                break;
                            }
                        }

                        if (!$is_id) {
                            $variant = $p_feature_name;
                        }
                    } else {
                        $variant = $p_feature_name;
                    }
                    $variant_data['variant'] = $variant;

                    if ($variant_data['feature_type'] == ProductFeatures::TEXT_FIELD) {
                        $variant_data['value'] = $variant;
                    }

                    list($d_variant, $params_variant) = $this->checkFeatureVariant($variant_data['feature_id'], $variant_data['variant'], $import_params['lang_code']);
                    if (!empty($d_variant)) {
                        $variant_data['variant_id'] = $d_variant;
                    } else {
                        $variant_data['variant_id'] = fn_add_feature_variant($variant_data['feature_id'], array('variant' => $variant));
                    }

                    $product['features'][$feature_id] = $variant_data;
                }
            }
        }

        $variant_data = array();
        if ($this->s_commerceml['exim_1c_used_brand'] == 'field_brand') {
            if (isset($data_product -> {$cml['manufacturer']})) {
                $variant_data['feature_id'] = $features_commerceml['brand1c']['id'];
                $variant_data['lang_code'] = $import_params['lang_code'];
                $variant_id = $this->db->getField(
                    "SELECT variant_id"
                    . " FROM ?:product_feature_variants"
                    . " WHERE feature_id = ?i AND external_id = ?s",
                    $variant_data['feature_id'],
                    strval($data_product -> {$cml['manufacturer']} -> {$cml['id']})
                );

                $variant = strval($data_product -> {$cml['manufacturer']} -> {$cml['name']});
                if (empty($variant_id)) {
                    $variant_data['variant_id'] = fn_add_feature_variant($variant_data['feature_id'], array('variant' => $variant));
                    $this->db->query("UPDATE ?:product_feature_variants SET external_id = ?s WHERE variant_id = ?i", strval($data_product -> {$cml['manufacturer']} -> {$cml['id']}), $variant_data['variant_id']);
                } else {
                    $variant_data['variant_id'] = $variant_id;
                }

                $product['features'][$variant_data['feature_id']] = $variant_data;
            }
        }

        return $product;
    }

    public function dataProductFields($data_product, &$product)
    {
        $cml = $this->cml;

        if (!empty($data_product -> {$cml['value_fields']} -> {$cml['value_field']})) {
            foreach ($data_product -> {$cml['value_fields']} -> {$cml['value_field']} as $value_field) {
                $_name_field = strval($value_field -> {$cml['name']});
                $_v_field = strval($value_field -> {$cml['value']});

                if (!empty($_v_field)) {
                    $product_params = $this->dataShippingParams($_v_field, $_name_field);

                    if (!empty($product_params)) {
                        $product = array_merge($product, $product_params);
                    }
                }
            }
        }
    }

    public function checkFeatureVariant($feature_id, $variant, $lang_code)
    {
        $variant_exists = $this->db->getField(
            "SELECT ?:product_feature_variant_descriptions.variant_id"
            . " FROM ?:product_feature_variant_descriptions"
            . " LEFT JOIN ?:product_feature_variants"
                . " ON ?:product_feature_variant_descriptions.variant_id = ?:product_feature_variants.variant_id"
            . " WHERE ?:product_feature_variants.feature_id = ?i"
                . " AND ?:product_feature_variant_descriptions.variant = ?s"
                . " AND ?:product_feature_variant_descriptions.lang_code = ?s",
            $feature_id, $variant, $lang_code
        );
        $result = (!empty($variant_exists)) ? false : true;

        return array($variant_exists, $result);
    }

    public function dataShippingParams($p_feature_name, $features_name)
    {
        $product_params = array();
        $shipping_params = $this->getShippingFeatures();
        foreach ($shipping_params as $shipping_param) {
            if (in_array($features_name, $shipping_param['fields'])) {
                if ($shipping_param['name'] == 'weight_property') {
                    $_value = preg_replace('/,/i', '.', $p_feature_name);
                    $product_params['weight'] = (float) $_value;
                }

                if ($shipping_param['name'] == 'free_shipping') {
                    if ($p_feature_name == $this->cml['yes']) {
                        $product_params['free_shipping'] = 'Y';
                    } else {
                        $product_params['free_shipping'] = '';
                    }
                }

                if ($shipping_param['name'] == 'shipping_cost') {
                    $_value = preg_replace('/,/i', '.', $p_feature_name);
                    $product_params['shipping_freight'] = (float) $_value;
                }

                if ($shipping_param['name'] == 'number_of_items') {
                    $product_params['min_items_in_box'] = (int) $p_feature_name;
                    $product_params['max_items_in_box'] = (int) $p_feature_name;
                }

                if ($shipping_param['name'] == 'box_length') {
                    $product_params['box_length'] = (int) $p_feature_name;
                }

                if ($shipping_param['name'] == 'box_width') {
                    $product_params['box_width'] = (int) $p_feature_name;
                }

                if ($shipping_param['name'] == 'box_height') {
                    $product_params['box_height'] = (int) $p_feature_name;
                }
            }
        }

        return $product_params;
    }

    public function addProductTaxes($product_tax, $product_id)
    {
        $tax_ids = array();
        $cml = $this->cml;

        if (!empty($product_id)) {
            $tax_id = $this->db->getColumn("SELECT tax_ids FROM ?:products WHERE product_id = ?i AND company_id = ?i", $product_id, $this->company_id);

            if (!empty($tax_id)) {
                $_tax_id = reset($tax_id);
                $tax_ids = fn_explode(',', $_tax_id);
            }
        }

        $product_taxes = $this->db->getColumn("SELECT tax_id FROM ?:rus_exim_1c_taxes WHERE tax_1c = ?s AND company_id = ?i", strval($product_tax -> {$cml['tax_rate']} -> {$cml['rate_t']}), $this->company_id);
        if (empty($product_taxes)) {
            $product_taxes = array();
        }

        $tax_ids = array_merge($tax_ids, $product_taxes);
        $tax_ids = array_unique($tax_ids);
        $tax_ids = array_diff($tax_ids, array('', ' ', null));

        return $tax_ids;
    }

    public function addProductImage($filename, $image_main, $product_id, $import_params)
    {
        $url_images = $this->url_images;
        if (file_exists($url_images . mb_strtolower($filename))) {
            $filename = mb_strtolower($filename);
        }

        $all_images_is_additional = $this->s_commerceml['exim_1c_all_images_is_additional'];
        if ($this->isFileProductImage($filename)) {
            $images_type = 'A';
            if ($image_main && ($all_images_is_additional != 'Y')) {
                $images_type = 'M';
                $image_main = false;
            }

            $this->updateProductImage($filename, $product_id, $images_type, $import_params['lang_code']);
        }
    }

    public function isFileProductImage($filename)
    {
        $file_array = fn_explode('.', $filename);
        if (is_array($file_array)) {
            $type = mb_strtolower(array_pop($file_array));
            if (in_array($type, array('jpg', 'jpeg', 'png', 'gif'))) {
                return true;
            }
        }

        return false;
    }

    public function updateProductImage($filename, $product_id, $type, $lang_code)
    {
        $url_images = $this->url_images;

        if (file_exists($url_images . $filename)) {
            $detail_file = fn_explode('.', $filename);
            $type_file = array_shift($detail_file);
            $condition = $this->db->quote(" AND images.image_path LIKE ?s", "%" . $type_file . "%");
            $images = $this->db->getArray(
                "SELECT images.image_id, images_links.pair_id"
                . " FROM ?:images AS images"
                . " LEFT JOIN ?:images_links AS images_links ON images.image_id = images_links.detailed_id"
                . " WHERE images_links.object_id = ?i $condition", $product_id);

            if (!empty($images) && !empty($type)) {
                foreach ($images as $k_image => $image) {
                    $this->db->query("UPDATE ?:images_links SET type = ?s WHERE pair_id = ?i", $type, $image['pair_id']);
                    $images[$k_image]['type'] = $type;
                }
            }

            $image_data[] = array(
                'name' => $filename,
                'path' => $url_images . $filename,
                'size' => filesize($url_images . $filename),
            );

            if (!empty($images)) {
                $pair_data = $images;
            } else {
                $pair_data[] = array(
                    'pair_id' => '',
                    'type' => $type,
                    'object_id' => 0
                );
            }

            $pair_ids = fn_update_image_pairs(array(), $image_data, $pair_data, $product_id, 'product', array(), 1, $lang_code);
        }
    }

    public function addProductCombinationsNewSchema($combinations, $product_id, $import_params, $combination_id = 0, $variant_data = array(), $article_option = "")
    {
        $add_options_combination = array();
        $amount = 0;
        $cml = $this->cml;
        $import_mode = $this->s_commerceml['exim_1c_import_mode_offers'];

        $option_params = array(
            'product_id' => $product_id,
            'option_name' => $this->s_commerceml['exim_1c_import_option_name'],
            'company_id' => $this->company_id,
            'option_type' => $this->s_commerceml['exim_1c_type_option'],
            'required' => 'N',
            'inventory' => 'Y',
            'multiupload' => 'M'
        );

        $option_id = $this->dataProductOption($product_id, $import_params['lang_code']);

        if (empty($combination_id)) {
            $option_data = $option_params;
            foreach ($combinations as $_combination) {
                if (!empty($option_id)) {
                    list($data_variant, $r_data_variants) = $this->dataProductVariants($option_id, $import_params['lang_code'], strval($_combination -> {$cml['name']}), strval($_combination -> {$cml['id']}));
                }

                $variant_id = (!empty($data_variant['variant_id'])) ? $data_variant['variant_id'] : 0;
                if (!empty($r_data_variants)) {
                    $option_data['variants'] = $r_data_variants;
                }

                $option_data['variants'][] = array(
                    'variant_name' => strval($_combination -> {$cml['name']}),
                    'variant_id' => $variant_id,
                    'external_id' => strval($_combination -> {$cml['id']})
                );

                $option_id = fn_update_product_option($option_data, $option_id, $import_params['lang_code']);
                $this->addMessageLog('Added option = ' . $option_data['option_name'] . ', variant = ' . strval($_combination -> {$cml['name']}));

                list($data_variant, $r_data_variants) = $this->dataProductVariants($option_id, $import_params['lang_code'], strval($_combination -> {$cml['name']}), strval($_combination -> {$cml['id']}));
                if (!empty($data_variant['variant_id'])) {
                    $add_options_combination[$option_id] = $data_variant['variant_id'];
                    $this->addNewCombination($product_id, strval($_combination -> {$cml['id']}), $add_options_combination, $import_params, $article_option);
                }
            }

        } else {
            if ($import_mode == 'standart') {
                $option_data = $option_params;
                $d_variant = array(
                    'variant_name' => '',
                    'lang_code' => $import_params['lang_code'],
                    'external_id' => $combination_id
                );
                foreach ($combinations as $_combination) {
                    $d_variant['variant_name'] .= strval($_combination -> {$cml['name']}) . ': ' . strval($_combination -> {$cml['value']}) . '; ';
                }

                if (!empty($variant_data['price'])) {
                    if ($this->s_commerceml['exim_1c_option_price'] == 'N') {
                        $d_variant['modifier'] = $variant_data['price'];
                    } else {
                        $d_variant['modifier'] = '0.00';
                    }
                } 

                if (!empty($option_id)) {
                    list($data_variant, $option_data['variants']) = $this->dataProductVariants($option_id, $import_params['lang_code'], $d_variant['variant_name'], $combination_id);
                }

                $d_variant['variant_id'] = (!empty($data_variant['variant_id'])) ? $data_variant['variant_id'] : 0;
                $option_data['variants'][] = $d_variant;
                $option_id = fn_update_product_option($option_data, $option_id, $import_params['lang_code']);
                $this->addMessageLog('Added option = ' . $option_data['option_name'] . ', variant = ' . $d_variant['variant_name']);

                list($data_variant, $r_data_variants) = $this->dataProductVariants($option_id, $import_params['lang_code'], $d_variant['variant_name'], $combination_id);
                if (!empty($data_variant['variant_id'])) {
                    $add_options_combination[$option_id] = $data_variant['variant_id'];
                }

            } elseif ($import_mode == 'global_option') {
                $option_params['product_id'] = 0;
                foreach ($combinations as $_combination) {
                    $option_data = $option_params;
                    $combination_name = strval($_combination -> {$cml['name']});
                    $combination_value = strval($_combination -> {$cml['value']});

                    $option_id = $this->dataProductOption(0, $import_params['lang_code'], $combination_name);
                    $option_data['option_name'] = $combination_name;

                    if (!empty($_combination -> {$cml['id']})) {
                        $option_data['external_id'] = strval($_combination -> {$cml['id']});
                    }

                    $option_data['variants'][] = array(
                        'variant_id' => 0,
                        'variant_name' => $combination_value,
                        'lang_code' => $import_params['lang_code'],
                        'modifier_type' => 'A',
                        'modifier' => 0,
                        'weight_modifier' => 0,
                        'weight_modifier_type' => 'A'
                    );
                    if ($option_id != 0) {
                        list($data_variant, $r_data_variants) = $this->dataProductVariants($option_id, $import_params['lang_code'], $combination_value);
                        if (!empty($data_variant)) {
                            $option_data['variants'] = array($data_variant);
                        }

                        if (!empty($r_data_variants)) {
                            $option_data['variants'] = array_merge($option_data['variants'], $r_data_variants);
                        }
                    }

                    $option_id = fn_update_product_option($option_data, $option_id, $import_params['lang_code']);
                    $this->addMessageLog('Added option = ' . $option_data['option_name'] . ', variant = ' . $combination_value);
                    $this->db->query("REPLACE INTO ?:product_global_option_links ?e", array(
                        'option_id' => $option_id,
                        'product_id' => $product_id
                    ));

                    list($data_variant, $r_data_variants) = $this->dataProductVariants($option_id, $import_params['lang_code'], $combination_value);
                    if (!empty($data_variant['variant_id'])) {
                        $add_options_combination[$option_id] = $data_variant['variant_id'];
                    }
                }

            } elseif ($import_mode == 'individual_option') {
                foreach ($combinations as $_combination) {
                    $option_data = $option_params;
                    $combination_name = strval($_combination -> {$cml['name']});
                    $combination_value = strval($_combination -> {$cml['value']});

                    $option_id = $this->dataProductOption($product_id, $import_params['lang_code'], $combination_name);
                    $option_data['option_name'] = $combination_name;

                    if (!empty($_combination -> {$cml['id']})) {
                        $option_data['external_id'] = strval($_combination -> {$cml['id']});
                    }

                    $option_data['variants'][] = array(
                        'option_id' => $option_id,
                        'variant_id' => 0,
                        'variant_name' => $combination_value,
                        'lang_code' => $import_params['lang_code'],
                        'modifier_type' => 'A',
                        'modifier' => 0,
                        'weight_modifier' => 0,
                        'weight_modifier_type' => 'A'
                    );

                    list($data_variant, $r_data_variants) = $this->dataProductVariants($option_id, $import_params['lang_code'], $combination_value);

                    if (!empty($data_variant)) {
                        $option_data['variants'] = array($data_variant);
                    }

                    if (!empty($r_data_variants)) {
                        $option_data['variants'] = array_merge($option_data['variants'], $r_data_variants);
                    }

                    $option_id = fn_update_product_option($option_data, $option_id, $import_params['lang_code']);
                    $this->addMessageLog('Added option = ' . $option_data['option_name'] . ', variant = ' . $combination_value);
                    list($data_variant, $r_data_variants) = $this->dataProductVariants($option_id, $import_params['lang_code'], $combination_value);
                    if (!empty($data_variant)) {
                        $add_options_combination[$option_id] = $data_variant['variant_id'];
                    }
                }

            }

            if (!empty($variant_data['amount'])) {
                $amount = $variant_data['amount'];
            }

            if (!empty($add_options_combination)) {
                $this->addNewCombination($product_id, $combination_id, $add_options_combination, $import_params, $amount, $article_option);
            }
        }
    }

    public function dataProductOption($product_id, $lang_code, $combination_name = "")
    {
        $option_id = 0;

        $condition = $join = '';

        $join = $this->db->quote("LEFT JOIN ?:product_options_descriptions AS options_descriptions ON options.option_id = options_descriptions.option_id ");

        $condition = $this->db->quote(" AND options.product_id = ?i AND options_descriptions.lang_code = ?s ", $product_id, $lang_code);

        if (!empty($combination_name)) {
            $condition .= $this->db->quote(" AND options_descriptions.option_name = ?s", $combination_name);
        }

        $option_id = $this->db->getField("SELECT options.option_id FROM ?:product_options AS options $join WHERE 1 $condition ");

        return $option_id;
    }

    public function dataProductVariants($option_id, $lang_code, $variant_name = "", $combination_id = 0)
    {
        $fields = array (
            'variants.variant_id',
            'variants.external_id',
            'variants.modifier_type',
            'variants.modifier',
            'variants.weight_modifier',
            'variants.weight_modifier_type',
            'variants_descriptions.variant_name',
        );

        $condition = $condition2 = $join = '';

        $join = $this->db->quote("LEFT JOIN ?:product_option_variants_descriptions AS variants_descriptions ON variants.variant_id = variants_descriptions.variant_id ");

        $condition = $this->db->quote(" AND variants.option_id = ?i AND variants_descriptions.lang_code = ?s ", $option_id, $lang_code);

        if (!empty($combination_id)) {
            $condition2 = $this->db->quote(" AND variants.external_id = ?s", $combination_id);
        }

        $data_variant = $this->db->getRow("SELECT " . implode(', ', $fields) . " FROM ?:product_option_variants AS variants $join WHERE variants_descriptions.variant_name = ?s $condition $condition2 ", $variant_name);
        $r_data_variants = $this->db->getArray("SELECT " . implode(', ', $fields) . " FROM ?:product_option_variants AS variants $join WHERE variants_descriptions.variant_name <> ?s $condition ", $variant_name);

        return array($data_variant, $r_data_variants);
    }

    public function addNewCombination($product_id, $combination_id, $add_options_combination, $import_params, $amount = 0, $article_option = "")
    {
        $old_combination_hash = $this->db->getField("SELECT combination_hash FROM ?:product_options_inventory WHERE external_id = ?s", $combination_id);
        $o_article = $this->db->getField("SELECT product_code FROM ?:product_options_inventory WHERE external_id = ?s", $combination_id);
        $image_pair_id = $this->db->getField("SELECT pair_id FROM ?:images_links WHERE object_id = ?i", $old_combination_hash);
        $this->db->query("DELETE FROM ?:product_options_inventory WHERE external_id = ?s AND product_id = ?i", $combination_id, $product_id);

        if (!empty($o_article)) {
            $article_option = $o_article;
        }

        $combination_data = array(
            'product_code' => $article_option,
            'product_id' => $product_id,
            'combination_hash' => fn_generate_cart_id($product_id, array('product_options' => $add_options_combination)),
            'combination' => fn_get_options_combination($add_options_combination),
            'external_id' => $combination_id
        );

        if (isset($amount)) {
            $combination_data['amount'] = $amount;
            $this->addProductOptionException($add_options_combination, $product_id, $import_params, $amount);
        }

        $variant_combination = $this->db->getField("SELECT combination_hash FROM ?:product_options_inventory WHERE combination_hash = ?s", $combination_data['combination_hash']);
        if (empty($variant_combination)) {
            $this->db->query("REPLACE INTO ?:product_options_inventory ?e", $combination_data);
        } else {
            $this->db->query("UPDATE ?:product_options_inventory SET ?u WHERE combination_hash = ?s", $combination_data, $combination_data['combination_hash']);
        }

        if (!empty($image_pair_id)) {
            $this->db->query("UPDATE ?:images_links SET object_id = ?i WHERE pair_id = ?i", $combination_data['combination_hash'], $image_pair_id);
        }
    }

    public function addProductOptionException($add_options_combination, $product_id, $import_params, $amount = 0)
    {
        $s_combination = serialize($add_options_combination);
        $hide_product = $this->s_commerceml['exim_1c_add_out_of_stock'];
        $exception_id = $this->db->getField("SELECT * FROM ?:product_options_exceptions WHERE product_id = ?i AND combination = ?s", $product_id, $s_combination);
        if (!empty($exception_id)) {
            $this->db->query("DELETE FROM ?:product_options_exceptions WHERE exception_id = ?i", $exception_id);
        }

        if (($amount <= 0) && ($hide_product == 'Y')) {
            $combination = array(
                'product_id' => $product_id,
                'combination' => $s_combination,
            );

            $this->db->query("REPLACE INTO ?:product_options_exceptions ?e", $combination);
        }
    }

    public function importDataOffersFile($xml, $service_exchange, $lang_code, $manual = false)
    {
        $this->addMessageLog("Started import date to file offers.xml, parameter service_exchange = " . $service_exchange);

        $cml = $this->cml;
        $import_params = array(
            'service_exchange' => $service_exchange,
            'lang_code' => $lang_code,
            'manual' => $manual
        );

        if (isset($xml -> {$cml['packages']} -> {$cml['offers']} -> {$cml['offer']})) {
            $this->importProductOffersFile($xml -> {$cml['packages']}, $import_params);
        } else {
            fn_echo("success\n");
        }
    }

    public function importProductOffersFile($data_offers, $import_params)
    {
        $cml = $this->cml;
        $create_prices = $this->s_commerceml['exim_1c_create_prices'];
        $schema_version = $this->s_commerceml['exim_1c_schema_version'];
        $import_mode = $this->s_commerceml['exim_1c_import_mode_offers'];
        $negative_amount = Registry::get('settings.General.allow_negative_amount');

        $all_currencies = $this->dataProductCurrencies();

        if (isset($data_offers -> {$cml['prices_types']} -> {$cml['price_type']})) {
            $price_offers = $this->dataPriceOffers($data_offers -> {$cml['prices_types']} -> {$cml['price_type']});

            if ($create_prices == 'Y') {
                $data_prices = $this->db->getArray("SELECT price_1c, type, usergroup_id FROM ?:rus_exim_1c_prices WHERE company_id = ?i", $this->company_id);

                if (empty($data_prices)) {
                    $data_prices = $this->db->getArray("SELECT price_1c, type, usergroup_id FROM ?:rus_exim_1c_prices");
                }

                $prices_commerseml = $this->getPricesDataFromFile($data_offers -> {$cml['prices_types']} -> {$cml['price_type']}, $data_prices);
            }
        }

        if (!isset(\Tygh::$app['session']['exim_1c']['import_offers'])) {
            $offer_pos_start = 0;
        } else {
            $offer_pos_start = \Tygh::$app['session']['exim_1c']['import_offers'];
        }

        if ($import_params['service_exchange'] == '') {

            if (count($data_offers -> {$cml['offers']} -> {$cml['offer']}) > COUNT_1C_IMPORT) {

                if ((count($data_offers -> {$cml['offers']} -> {$cml['offer']}) - $offer_pos_start) > COUNT_1C_IMPORT) {
                    fn_echo("progress\n");
                } else {
                    fn_echo("success\n");
                }

            } else {
                fn_echo("success\n");
            }
        }
        $offers_pos = 0;
        $progress = false;
        $options_data = $global_options_data = array();
        $count_import_offers = 0;

        foreach ($data_offers -> {$cml['offers']} -> {$cml['offer']} as $offer) {
            $offers_pos++;

            if ($offers_pos < $offer_pos_start) {
                continue;
            }

            if ($offers_pos - $offer_pos_start + 1 > COUNT_1C_IMPORT && $import_params['service_exchange'] == '') {
                $progress = true;
                break;
            }

            $product = array();
            $amount = 0;
            $combination_id = 0;
            $ids = fn_explode('#', strval($offer -> {$cml['id']}));
            $guid_product = array_shift($ids);

            if (!empty($ids)) {
                $combination_id = reset($ids);
            }

            $product_data = $this->db->getRow("SELECT product_id, update_1c, status FROM ?:products WHERE external_id = ?s", $guid_product);
            $product_id = !empty($product_data['product_id']) ? $product_data['product_id'] : 0;
            $update_product = !empty($product_data['update_1c']) ? $product_data['update_1c'] : 'N';

            if (!($this->checkImportPrices($product_data))) {
                continue;
            }

            $count_import_offers++;
            if (isset($offer -> {$cml['amount']}) && !empty($offer -> {$cml['amount']})) {
                $amount = strval($offer -> {$cml['amount']});

            } elseif (isset($offer -> {$cml['store']})) {
                foreach ($offer -> {$cml['store']} as $store) {
                    $amount += strval($store[$cml['in_stock']]);
                }
            }

            $prices = array();
            if (isset($offer -> {$cml['prices']}) && !empty($price_offers)) {
                $_price_offers = $price_offers;

                foreach ($offer -> {$cml['prices']} -> {$cml['price']} as $c_price) {
                    if (!empty($c_price -> {$cml['currency']}) && !empty($_price_offers[strval($c_price -> {$cml['price_id']})]['coefficient']) && !empty($all_currencies[strval($c_price -> {$cml['currency']})]['coefficient'])) {
                        $_price_offers[strval($c_price -> {$cml['price_id']})]['coefficient'] = $all_currencies[strval($c_price -> {$cml['currency']})]['coefficient'];
                    }
                }

                $product_prices = $this->conversionProductPrices($offer -> {$cml['prices']} -> {$cml['price']}, $_price_offers);

                if ($create_prices == 'Y') {
                    $prices = $this->dataProductPrice($product_prices, $prices_commerseml);

                } elseif (!empty($product_prices[strval($offer -> {$cml['prices']} -> {$cml['price']} -> {$cml['price_id']})]['price'])) {
                    $prices['base_price'] = $product_prices[strval($offer -> {$cml['prices']} -> {$cml['price']} -> {$cml['price_id']})]['price'];

                } else {
                    $prices['base_price'] = 0;
                }
            }

            if (empty($prices)) {
                $prices['base_price'] = 0;
            }

            if ($amount < 0 && $negative_amount == 'N') {
                $amount = 0;
            }
            $o_amount = $amount;

            if (!empty($product_amount[$product_id])) {
                $o_amount = $o_amount + $product_amount[$product_id]['amount'];
            }

            $product_amount[$product_id]['amount'] = $o_amount;
            $product['status'] = $this->updateProductStatus($product_id, $product_data['status'], $product_amount[$product_id]['amount']);

            if (empty($combination_id)) {
                $product['amount'] = $amount;
                $this->db->query("UPDATE ?:products SET ?u WHERE product_id = ?i", $product, $product_id);
                $this->addProductPrice($product_id, $prices);
                $this->addMessageLog('Added product = ' . strval($offer -> {$cml['name']}) . ', price = ' . $prices['base_price'] . ' and amount = ' . $amount);

            } else {
                $product['tracking'] = 'O';
                $this->db->query("UPDATE ?:products SET ?u WHERE product_id = ?i", $product, $product_id);

                if ($schema_version == '2.07') {
                    $this->addProductPrice($product_id, array('base_price' => 0));
                    $option_id = $this->dataProductOption($product_id, $import_params['lang_code']);
                    $variant_id = $this->db->getField("SELECT variant_id FROM ?:product_option_variants WHERE external_id = ?s AND option_id = ?i", $combination_id, $option_id);

                    if (!empty($option_id) && !empty($variant_id)) {
                        $price = $prices['base_price'];

                        if ($this->s_commerceml['exim_1c_option_price'] == 'Y') {
                            $price = '0.00';
                        }
                        $this->db->query("UPDATE ?:product_option_variants SET modifier = ?d WHERE variant_id = ?i", $price, $variant_id);
                        $add_options_combination = array($option_id => $variant_id);
                        $this->addNewCombination($product_id, $combination_id, $add_options_combination, $import_params, $amount);
                        $this->addMessageLog('Added product = ' . strval($offer -> {$cml['name']}) . ', option_id = ' . $option_id . ', variant_id = ' . $variant_id . ', price = ' . $prices['base_price'] . ' and amount = ' . $amount);

                    } elseif (empty($variant_id) && $import_mode == 'global_option') {
                        $data_combination = $this->db->getRow("SELECT combination_hash, combination FROM ?:product_options_inventory WHERE external_id = ?s AND product_id = ?i", $combination_id, $product_id);
                        $add_options_combination = fn_get_product_options_by_combination($data_combination['combination']);
                        $this->addProductOptionException($add_options_combination, $product_id, $import_params, $amount);

                        if (!empty($data_combination['combination_hash'])) {
                            $image_pair_id = $this->db->getField("SELECT pair_id FROM ?:images_links WHERE object_id = ?i", $data_combination['combination_hash']);
                            $this->db->query("UPDATE ?:product_options_inventory SET amount = ?i WHERE combination_hash = ?s", $amount, $data_combination['combination_hash']);

                            if (!empty($image_pair_id)) {
                                $this->db->query("UPDATE ?:images_links SET object_id = ?i WHERE pair_id = ?i", $data_combination['combination_hash'], $image_pair_id);
                            }
                        }

                        $this->addMessageLog('Added global option product = ' . strval($offer -> {$cml['name']}) . ', price = ' . $prices['base_price'] . ' and amount = ' . $amount);
                    } elseif (empty($variant_id) && ($import_mode == 'individual_option')) {
                        $data_combination = $this->db->getRow("SELECT combination_hash, combination FROM ?:product_options_inventory WHERE external_id = ?s AND product_id = ?i", $combination_id, $product_id);
                        $add_options_combination = fn_get_product_options_by_combination($data_combination['combination']);
                        $this->addProductOptionException($add_options_combination, $product_id, $import_params, $amount);

                        if (!empty($data_combination['combination_hash'])) {
                            $image_pair_id = $this->db->getField("SELECT pair_id FROM ?:images_links WHERE object_id = ?i", $data_combination['combination_hash']);
                            $this->db->query("UPDATE ?:product_options_inventory SET amount = ?i WHERE combination_hash = ?s", $amount, $data_combination['combination_hash']);

                            if (!empty($image_pair_id)) {
                                $this->db->query("UPDATE ?:images_links SET object_id = ?i WHERE pair_id = ?i", $data_combination['combination_hash'], $image_pair_id);
                            }
                        }
                        $this->addMessageLog('Added individual option product = ' . strval($offer -> {$cml['name']}) . ', price = ' . $prices['base_price'] . ' and amount = ' . $amount);
                    }
                } else {
                    if (!empty($offer -> {$cml['product_features']} -> {$cml['product_feature']})) {
                        $variant_data = array(
                            'amount' => $amount
                        );

                        if ($import_mode == 'standart') {
                            $this->addProductPrice($product_id, array('base_price' => 0));
                            $variant_data['price'] = $prices['base_price'];
                        }

                        if (!empty($product_amount[$product_id][$combination_id])) {
                            $amount = $amount + $product_amount[$product_id]['amount'];
                        }
                        $product_amount[$product_id]['amount'] = $amount;
                        $this->addProductCombinationsNewSchema($offer -> {$cml['product_features']} -> {$cml['product_feature']}, $product_id, $import_params, $combination_id, $variant_data);
                        $this->addMessageLog('Added option product = ' . strval($offer -> {$cml['name']}) . ', price = ' . $prices['base_price'] . ' and amount = ' . $amount);
                    }
                }

                if ($this->s_commerceml['exim_1c_option_price'] == 'Y') {
                    $this->addProductPrice($product_id, $prices);
                }
            }

            if ($import_params['service_exchange'] == '' && ($count_import_offers == COUNT_IMPORT_PRODUCT)) {
                fn_echo("imported: " . $count_import_offers . "\n");
                $count_import_offers = 0;
            }
        }

        if ($progress) {
            if (!isset(\Tygh::$app['session']['exim_1c'])) {
                \Tygh::$app['session']['exim_1c'] = array();
            }
            \Tygh::$app['session']['exim_1c']['import_offers'] = $offers_pos;
            fn_echo('processed: ' . \Tygh::$app['session']['exim_1c']['import_offers'] . "\n");

            if ($import_params['manual']) {
                fn_redirect(Registry::get('config.current_url'));
            }
        } else {
            fn_echo("success\n");
            unset(\Tygh::$app['session']['exim_1c']['import_offers']);
        }
    }

    /**
     * Creates an array with products prices.
     * Prices from the import file are added to the array when the name of the price matches
     * the name entered in the admin panel.
     *
     * @param object  $prices_file   The simplexml object with prices from the imported file.
     * @param array   $data_prices   The array with the names of price fields;
     *                               these names are entered in the admin panel.
     *
     * @return The array with the products prices.
     */
    public function getPricesDataFromFile($prices_file, $data_prices)
    {
        $cml = $this->cml;
        $prices_commerseml = array();
        foreach ($prices_file as $_price) {
            foreach ($data_prices as $d_price) {
                if ($d_price['price_1c'] == strval($_price -> {$cml['name']})) {
                    $d_price['external_id'] = strval($_price -> {$cml['id']});
                    $prices_commerseml[] = $d_price;
                }
            }
        }

        return $prices_commerseml;
    }

    /**
     * Creates an array with prices.
     * If the name of the price field from in the admin panel matches the name of
     * a price field from the import file, then the array element is marked as valid.
     *
     * @param object  $prices_file   The simplexml object with prices from the imported file.
     * @param array   $data_prices   The array with the names of price fields;
     *                               these names are entered in the admin panel.
     *
     * @return The array with the products prices.
     */
    public function validatePricesByFile($prices_file, $data_prices)
    {
        $cml = $this->cml;
        $prices_commerseml = array();

        foreach ($data_prices as $d_price) {
            foreach ($prices_file as $_price) {
                if ($d_price['price_1c'] == strval($_price -> {$cml['name']})) {
                    $d_price['valid'] = true;
                }
            }

            $prices_commerseml[$d_price['price_1c']] = $d_price;
        }

        return $prices_commerseml;
    }

    public function checkImportPrices($product_data)
    {
        $import_prices = true;
        $type_import_products = $this->s_commerceml['exim_1c_import_products'];

        if (empty($product_data['product_id']) || ($product_data['product_id'] == 0)) {
            $import_prices = false;
        }

        if (!empty($product_data['update_1c']) && ($product_data['update_1c'] == 'N')) {
            $import_prices = false;
        }

        if ($type_import_products == 'new_products' && !empty($product_data['status']) && ($product_data['status'] != 'N')) {
            $import_prices = false;
        }

        return $import_prices;
    }

    public function dataProductPrice($product_prices, $prices_commerseml)
    {
        $cml = $this->cml;
        $prices = array();
        $list_prices = array();
        foreach ($product_prices as $external_id => $p_price) {
            foreach ($prices_commerseml as $p_commerseml) {
                if (!empty($p_commerseml['external_id'])) {
                    if ($external_id == $p_commerseml['external_id']) {
                        if ($p_commerseml['type'] == 'base') {
                            $prices['base_price'] = $p_price['price'];
                        }

                        if (($p_commerseml['type'] == 'list')) {
                            $prices['list_price'] = $p_price['price'];
                            $list_prices[] = $p_price['price'];
                        }

                        if ($p_commerseml['usergroup_id'] > 0) {
                            $prices['qty_prices'][] = array(
                                'price' => $p_price['price'],
                                'usergroup_id' => $p_commerseml['usergroup_id']
                            );
                        }
                    }
                }
            }
        }

        if (!empty($prices['list_price']) && !empty($prices['base_price'])) {
            if ($prices['list_price'] < $prices['base_price']) {
                $prices['list_price'] = 0;

                foreach ($list_prices as $list_price) {
                    if ($list_price >= $prices['base_price']) {
                        $prices['list_price'] = $list_price;
                    }
                }
            }
        }

        return $prices;
    }

    public function updateProductStatus($product_id, $product_status, $amount)
    {
        $new_status = $product_status;
        $hide_product = $this->s_commerceml['exim_1c_add_out_of_stock'];
        if ($hide_product == 'Y') {
            if (($product_status == 'N' || $product_status == 'H') && $amount > 0) {
                $new_status = 'A';

            } elseif (($product_status == 'N') && ($amount <= 0)) {
                $new_status = 'H';
            }
        } else {
            if ($product_status == 'N') {
                $new_status = 'A';
            }
        }

        $this->db->query("UPDATE ?:products SET status = ?s WHERE product_id = ?i", $product_status, $product_id);

        return $new_status;
    }

    public function addProductPrice($product_id, $prices)
    {
        $price_create = fn_ult_is_shared_product($product_id, $this->company_id);

        $is_price = $this->db->getField("SELECT product_id FROM ?:ult_product_prices WHERE product_id = ?i", $product_id);
        if (!empty($is_price)) {
            $price_create = 'Y';
        }

        if (isset($prices['base_price'])) {
            $price = array(
                'product_id' => $product_id,
                'price' => $prices['base_price'],
                'lower_limit' => 1,
                'usergroup_id' => 0
            );

            $this->db->query("DELETE FROM ?:product_prices WHERE lower_limit = 1 AND usergroup_id = 0 AND product_id = ?i", $product_id);
            $data_price = $this->db->getField("SELECT * FROM ?:product_prices WHERE lower_limit = 1 AND usergroup_id = 0 AND price = ?d AND product_id = ?i", $prices['base_price'], $product_id);
            if (empty($data_price)) {
                $this->db->query("REPLACE INTO ?:product_prices ?e", $price);
            }

            if ($price_create == 'Y') {
                $price['create'] = $price_create;
            }

            fn_update_product_prices($product_id, $price, $this->company_id);
        }

        if (isset($prices['list_price'])) {
            $this->db->query("UPDATE ?:products SET list_price = ?d WHERE product_id = ?i", $prices['list_price'], $product_id);
        }

        $product_data = array();
        if (isset($prices['qty_prices'])) {
            foreach ($prices['qty_prices'] as $_price) {
                $price = array(
                    'product_id' => $product_id,
                    'price' => $_price['price'],
                    'lower_limit' => 1,
                    'usergroup_id' => $_price['usergroup_id'],
                );
                $this->db->query("DELETE FROM ?:product_prices WHERE lower_limit = 1 AND usergroup_id = ?i AND product_id = ?i", $_price['usergroup_id'], $product_id);
                $this->db->query("REPLACE INTO ?:product_prices ?e", $price);

                if ($price_create == 'Y') {
                    $price['create'] = $price_create;
                }
                $product_data['prices'][] = $price;
            }

            fn_update_product_prices($product_id, $product_data, $this->company_id);

            $data_price = $this->db->getField("SELECT * FROM ?:product_prices WHERE lower_limit = 1 AND usergroup_id = 0 AND product_id = ?i", $product_id);
            if (empty($data_price)) {
                $price = array(
                    'product_id' => $product_id,
                    'price' => '0.00',
                    'lower_limit' => 1,
                    'usergroup_id' => 0
                );

                $this->db->query("REPLACE INTO ?:product_prices ?e", $price);
            }
        }
    }

    public function exportDataOrders($lang_code)
    {
        $params = array(
            'company_id' => $this->company_id,
            'company_name' => true,
            'place' => 'exim_1c',
        );

        $statuses = $this->s_commerceml['exim_1c_order_statuses'];
        if (!empty($statuses)) {
            foreach($statuses as $key => $status) {
                if (!empty($status)) {
                    $params['status'][] = $key;
                }
            }
        }

        list($orders, $search) = fn_get_orders($params);
        header("Content-type: text/xml; charset=utf-8");
        fn_echo("\xEF\xBB\xBF");
        $xml = new \XMLWriter();
        $xml -> openMemory();
        $xml -> startDocument();
        $xml -> startElement($this->cml['commerce_information']);
        foreach ($orders as $k => $data) {
            $order_data = fn_get_order_info($data['order_id']);
            $xml = $this->dataOrderToFile($xml, $order_data, $lang_code);
        }
        $xml -> endElement();
        fn_echo($xml -> outputMemory());
    }

    public function exportAllProductsToOrders($lang_code)
    {
        $params = array(
            'company_id' => $this->company_id,
            'company_name' => true
        );

        $statuses = $this->s_commerceml['exim_1c_order_statuses'];
        $params['status'] = reset($statuses);

        header("Content-type: text/xml; charset=utf-8");
        fn_echo("\xEF\xBB\xBF");
        $xml = new \XMLWriter();
        $xml -> openMemory();
        $xml -> startDocument();
        $xml -> startElement($this->cml['commerce_information']);

        $payment_id = $this->db->getField("SELECT payment_id FROM ?:payments");
        $payment_data = fn_get_payment_method_data($payment_id);

        $shipping_id = $this->db->getField("SELECT shipping_id FROM ?:shippings");
        $shipping_data[] = fn_get_shipping_info($shipping_id, $lang_code);

        $info_taxes = fn_get_taxes($lang_code);
        $d_taxes = fn_calculate_tax_rates($info_taxes, 1, 0, array(), \Tygh::$app['session']['cart']);

        $company_data = fn_get_company_data($params['company_id'], $lang_code);
        $order_data = array(
            'order_id' => $company_data['company_id'] . '_' . $company_data['company'],
            'secondary_currency' => CART_PRIMARY_CURRENCY,
            'notes' => '',
            'status' => $params['status'],
            'payment_method' => $payment_data,
            'shipping' => $shipping_data,
            'fields' => array(),
            'shipping_cost' => 0
        );
        $order_data = array_merge($order_data, $this->import_params['user_data']);
        $order_data['company'] = $company_data['company'];
        $order_data['timestamp'] = TIME;

        $product_params = array(
            'filter_params' => array(
                'update_1c' => 'Y'
            )
        );
        list($data_products, $product_params) = fn_get_products($product_params);

        $total = 0;
        foreach ($data_products as $product_id => $data_product) {
            $total += $data_product['price'];
            $data_products[$product_id]['amount'] = 1;
            $data_products[$product_id]['subtotal'] = $data_product['price'];
            $data_products[$product_id]['base_price'] = $data_product['price'];
            $data_products[$product_id]['item_id'] = $product_id;

            if (!empty($data_product['tax_ids'])) {
                $p_taxes = explode(',', $data_product['tax_ids']);

                foreach ($p_taxes as $tax_id) {
                    if (!empty($d_taxes[$tax_id])) {
                        $d_taxes[$tax_id]['applies']['items']['P'][$product_id] = 1;
                    }
                }
            }
        }

        $order_data['total'] = $total;
        $order_data['taxes'] = $d_taxes;
        $order_data['products'] = $data_products;

        $xml = $this->dataOrderToFile($xml, $order_data, $lang_code);

        $xml -> endElement();
        fn_echo($xml -> outputMemory());
    }

    public function dataOrderToFile($xml, $order_data, $lang_code)
    {
        $export_statuses = $this->s_commerceml['exim_1c_export_statuses'];
        $cml = $this->cml;

        $order_xml = $this->getOrderDataForXml($order_data, $cml);

        if (empty($order_data['firstname'])) {
            unset($order_data['firstname']);
        }
        if (empty($order_data['lastname'])) {
            unset($order_data['lastname']);
        }
        if (empty($order_data['phone'])) {
            unset($order_data['phone']);
        }
        $order_data = fn_fill_contact_info_from_address($order_data);
        $order_xml[$cml['contractors']][$cml['contractor']] = $this->getDataOrderUser($order_data);

        if (!empty($order_data['fields'])) {
            $fields_export = $this->exportFieldsToFile($order_data['fields']);
        }

        if (!empty($fields_export)) {
            foreach ($fields_export as $field_export) {
                $order_xml[$cml['contractors']][$cml['contractor']][$field_export['description']] = $field_export['value'];
            }
        }

        $rate_discounts = 0;
        if (!empty($order_data['subtotal']) && (!empty($order_data['discount']) || !empty($order_data['subtotal_discount']))) {
            $o_subtotal = 0;

            if (!empty($order_data['discount'])) {
                foreach ($order_data['products'] as $product) {
                    $o_subtotal = $o_subtotal + $product['price'];
                }
            }

            if (empty($o_subtotal)) {
                $o_subtotal = $order_data['subtotal'] - $order_data['discount'];
            }

            if (($order_data['subtotal_discount'] > 0) && ($order_data['subtotal_discount'] < $o_subtotal)) {
                $rate_discounts = $order_data['subtotal_discount'] * 100 / $o_subtotal;

                $order_xml[$cml['discounts']][$cml['discount']] = array(
                    $cml['name'] => $cml['orders_discount'],
                    $cml['total'] => $order_data['subtotal_discount'],
                    $cml['rate_discounts'] => $rate_discounts,
                    $cml['in_total'] => 'true'
                );
            }
        }

        $order_xml[$cml['products']] = $this->dataOrderProducts($xml, $order_data, $rate_discounts);

        $data_status = fn_get_statuses('O', $order_data['status']);

        $status = (!empty($data_status)) ? $data_status[$order_data['status']]['description'] : $order_data['status'];

        if (empty($status)) {
            $status = 'O';
        }

        if ($export_statuses == 'Y') {
            $order_xml[$cml['value_fields']][][$cml['value_field']] = array(
                $cml['name'] => $cml['status_order'],
                $cml['value'] => $status
            );
        }

        list($payment, $shipping) = $this->getAdditionalOrderData($order_data);

        $order_xml[$cml['value_fields']][][$cml['value_field']] = array(
            $cml['name'] => $cml['payment'],
            $cml['value'] => $payment
        );

        $order_xml[$cml['value_fields']][][$cml['value_field']] = array(
            $cml['name'] => $cml['shipping'],
            $cml['value'] => $shipping
        );

        $xml = $this->parseArrayToXml($xml, array($cml['document'] => $order_xml));

        return $xml;
    }

    public function getOrderDataForXml($order_data, $cml)
    {
        $currency = (!empty($order_data['secondary_currency'])) ? $order_data['secondary_currency'] : CART_PRIMARY_CURRENCY;
        $notes = $order_data['notes'];

        $array_order_xml = array(
            $cml['id'] => $order_data['order_id'],
            $cml['number'] => $order_data['order_id'],
            $cml['date'] => date('Y-m-d', $order_data['timestamp']),
            $cml['time'] => date('H:i:s', $order_data['timestamp']),
            $cml['operation'] => $cml['order'],
            $cml['role'] => $cml['seller'],
            $cml['rate'] => 1,
            $cml['total'] => $order_data['total'],
            $cml['currency'] => $currency,
            $cml['notes'] => $notes
        );

        return $array_order_xml;
    }

    public function getAdditionalOrderData($order_data)
    {
        $payment = (!empty($order_data['payment_method']['payment'])) ? $order_data['payment_method']['payment'] : "-";
        $shipping = (!empty($order_data['shipping'][0]['shipping'])) ? $order_data['shipping'][0]['shipping'] : "-";

        return array($payment, $shipping);
    }

    /**
     * Searches for the field with the specified name with the b or s prefix; returns the value of that field.
     *
     * @param $order_data  The array with the order data.
     * @param $field_name  The name of the field to search for.
     *
     * @return string The value of the found field.
     */
    public function getContactInfoFromAddress($order_data, $field_name)
    {
        $main_address = SHIPPING_ADDRESS_PREFIX . '_' . $field_name;
        $alt_address = BILLING_ADDRESS_PREFIX . '_' . $field_name;

        if (Registry::get('settings.Checkout.address_position') == 'billing_first') {
            $main_address = BILLING_ADDRESS_PREFIX . '_' . $field_name;
            $alt_address = SHIPPING_ADDRESS_PREFIX . '_' . $field_name;
        }

        if (!empty($order_data[$main_address])) {
            $data_field = trim($order_data[$main_address]);

        } elseif (!empty($order_data[$alt_address])) {
            $data_field = trim($order_data[$alt_address]);
        }

        if (empty($data_field)) {
            $data_field = '-';
        }

        return $data_field;
    }

    /**
     * Prepares the array of user data for export to the accounting systems.
     *
     * @param $order_data The array with the order data.
     *
     * @return array The array with the user data.
     */
    public function getDataOrderUser($order_data)
    {
        $cml = $this->cml;
        $user_id = '0' . $order_data['order_id'];
        $unregistered = $cml['yes'];
        if (!empty($order_data['user_id'])) {
            $user_id = $order_data['user_id'];
            $unregistered = $cml['no'];
        }

        if (!isset($order_data['firstname'])) {
            $order_data['firstname'] = '-';
        }

        if (!isset($order_data['lastname'])) {
            $order_data['lastname'] = '-';
        }

        if (!isset($order_data['phone'])) {
            $order_data['phone'] = '-';
        }

        $name_company = empty($order_data['company']) ? $order_data['lastname'] . ' ' . $order_data['firstname'] : $order_data['company'];

        $zipcode = $this->getContactInfoFromAddress($order_data, 'zipcode');
        $country = $this->getContactInfoFromAddress($order_data, 'country_descr');
        $city = $this->getContactInfoFromAddress($order_data, 'city');
        $address1 = $this->getContactInfoFromAddress($order_data, 'address');
        $address2 = $this->getContactInfoFromAddress($order_data, 'address_2');

        $user_xml = array(
            $cml['id'] => $user_id,
            $cml['unregistered'] => $unregistered,
            $cml['name'] => $name_company,
            $cml['role'] => $cml['seller'],
            $cml['full_name_contractor'] => $order_data['lastname'] . ' ' . $order_data['firstname'],
            $cml['lastname'] => $order_data['lastname'],
            $cml['firstname'] => $order_data['firstname']
        );

        $user_xml[$cml['address']][$cml['presentation']] = "$zipcode, $country, $city, $address1 $address2";
        $user_xml[$cml['address']][][$cml['address_field']] = array(
            $cml['type'] => $cml['post_code'],
            $cml['value'] => $zipcode
        );
        $user_xml[$cml['address']][][$cml['address_field']] = array(
            $cml['type'] => $cml['country'],
            $cml['value'] => $country
        );
        $user_xml[$cml['address']][][$cml['address_field']] = array(
            $cml['type'] => $cml['city'],
            $cml['value'] => $city
        );
        $user_xml[$cml['address']][][$cml['address_field']] = array(
            $cml['type'] => $cml['address'],
            $cml['value'] => "$address1 $address2"
        );

        $phone = (!empty($order_data['phone'])) ? $order_data['phone'] : '-';
        $user_xml[$cml['contacts']][][$cml['contact']] = array(
            $cml['type'] => $cml['mail'],
            $cml['value'] => $order_data['email']
        );
        $user_xml[$cml['contacts']][][$cml['contact']] = array(
            $cml['type'] => $cml['work_phone'],
            $cml['value'] => $phone
        );

        return $user_xml;
    }

    /**
     * Changes the identifier and name of the product depending on the option from the external accounting system
     * 
     * @param int     $product_id        The ID of the product in the store
     * @param array   $product_options   The array with the options of the product
     * @param string  $external_id       The ID of the product in the external accounting system
     * @param string  $product_name      The name of the product
     *
     * @return void
    */
    public function setDataProductByOptions($product_id, $product_options, &$external_id, &$product_name)
    {
        $combinations = '';
        $name_combinations = array();
        $options_ids = array();

        foreach ($product_options as $option_value) {
            $combinations[$option_value['option_id']] = $option_value['option_id'] . '_' . $option_value['value'];
            $name_combinations[$option_value['option_id']] = $option_value['option_name'] . ': ' . $option_value['variant_name'];
            $options_ids[$option_value['option_id']] = $option_value['value'];
        }

        ksort($combinations);
        ksort($name_combinations);
        $combinations = implode('_', $combinations);
        $name_combination = implode('; ', $name_combinations);

        $options_inventory = $this->db->getRow(
            "SELECT external_id, combination_hash"
            . " FROM ?:product_options_inventory"
            . " WHERE product_id = ?i AND combination = ?s",
            $product_id,
            $combinations
        );

        if (empty($options_inventory) && !empty($options_ids)) {
            $combination_hash = fn_generate_cart_id($product_id, array('product_options' => $options_ids));

            $options_inventory = $this->db->getRow(
                "SELECT external_id, combination_hash"
                . " FROM ?:product_options_inventory"
                . " WHERE product_id = ?i AND combination_hash = ?s",
                $product_id,
                $combination_hash
            );
        }

        if (!empty($options_inventory['external_id'])) {
            $external_id = $external_id . '#' . $options_inventory['external_id'];
            $product_name = $product_name . '#' . $name_combination;

        }
    }

    public function dataOrderProducts($xml, $order_data, $discount = 0)
    {
        $cml = $this->cml;
        $export_options = $this->s_commerceml['exim_1c_product_options'];

        $add_tax = $this->s_commerceml['exim_1c_add_tax'];
        if (!empty($order_data['taxes']) && $add_tax == 'Y') {
            $data_taxes = $this->dataOrderTaxs($order_data['taxes']);
        }

        if ($this->s_commerceml['exim_1c_order_shipping'] == 'Y' && $order_data['shipping_cost'] > 0) {
            $data_product = array(
                $cml['id'] => 'ORDER_DELIVERY',
                $cml['name'] => $cml['delivery_order'],
                $cml['price_per_item'] => $order_data['shipping_cost'],
                $cml['amount'] => 1,
                $cml['total'] => $order_data['shipping_cost'],
                $cml['multiply'] => 1,
            );
            $data_product[$cml['base_unit']]['attribute'] = array(
                $cml['code'] => '796',
                $cml['full_name_unit'] => $cml['item'],
                'text' => $cml['item']
            );
            $data_product[$cml['value_fields']][][$cml['value_field']] = array(
                $cml['name'] => $cml['spec_nomenclature'],
                $cml['value'] => $cml['service']
            );
            $data_product[$cml['value_fields']][][$cml['value_field']] = array(
                $cml['name'] => $cml['type_nomenclature'],
                $cml['value'] => $cml['service']
            );

            $data_products[][$cml['product']] = $data_product;
        }

        if (!empty($order_data['payment_surcharge']) && $order_data['payment_surcharge'] > 0) {
            $data_product = array(
                $cml['id'] => 'Payment_surcharge',
                $cml['name'] => $cml['payment_surcharge'],
                $cml['price_per_item'] => $order_data['payment_surcharge'],
                $cml['amount'] => 1,
                $cml['total'] => $order_data['payment_surcharge'],
                $cml['multiply'] => 1,
            );
            $data_product[$cml['base_unit']]['attribute'] = array(
                $cml['code'] => '796',
                $cml['full_name_unit'] => $cml['item'],
                'text' => $cml['item']
            );
            $data_product[$cml['value_fields']][][$cml['value_field']] = array(
                $cml['name'] => $cml['spec_nomenclature'],
                $cml['value'] => $cml['service']
            );
            $data_product[$cml['value_fields']][][$cml['value_field']] = array(
                $cml['name'] => $cml['type_nomenclature'],
                $cml['value'] => $cml['service']
            );

            $data_products[][$cml['product']] = $data_product;
        }

        foreach ($order_data['products'] as $product) {
            $product_discount = 0;
            $product_subtotal = $product['subtotal'];
            $external_id = $this->db->getField("SELECT external_id FROM ?:products WHERE product_id = ?i", $product['product_id']);
            $external_id = (!empty($external_id)) ? $external_id : $product['product_id'];
            $product_name = $product['product'];
            if (!empty($product['product_options']) && $export_options == 'Y') {
                $this->setDataProductByOptions($product['product_id'], $product['product_options'], $external_id, $product_name);
            }

            $data_product = array(
                $cml['id'] => $external_id,
                $cml['code'] => $product['product_id'],
                $cml['article'] => $product['product_code'],
                $cml['name'] => $product_name,
                $cml['price_per_item'] => $product['base_price'],
                $cml['amount'] => $product['amount'],
                $cml['multiply'] => 1
            );
            $data_product[$cml['base_unit']]['attribute'] = array(
                $cml['code'] => '796',
                $cml['full_name_unit'] => $cml['item'],
                'text' => $cml['item']
            );

            if (!empty($discount)) {
                $p_subtotal = $product['price'] * $product['amount'];
                $product_discount = $p_subtotal * $discount / 100;

                if ($p_subtotal > $product_discount) {
                    $data_product[$cml['discounts']][][$cml['discount']] = array(
                        $cml['name'] => $cml['product_discount'],
                        $cml['total'] => $product_discount,
                        $cml['in_total'] => 'false'
                    );
                }
            }

            if(isset($product['discount'])) {
                $data_product[$cml['discounts']][][$cml['discount']] = array(
                    $cml['name'] => $cml['product_discount'],
                    $cml['total'] => $product['discount'],
                    $cml['in_total'] => 'true'
                );
            }

            if (!empty($data_taxes['products'][$product['item_id']])) {
                $tax_value = 0;
                $subtotal = $product['subtotal'] - $product_discount;
                foreach ($data_taxes['products'][$product['item_id']] as $product_tax) {
                    $data_product[$cml['taxes_rates']][][$cml['tax_rate']] = array(
                        $cml['name'] => $product_tax['name'],
                        $cml['rate_t'] => $product_tax['value']
                    );

                    if ($product_tax['tax_in_total'] == 'false') {
                        $tax_value = $tax_value + ($subtotal * $product_tax['rate_value'] / 100);
                    }
                }

                $product_subtotal = $product['subtotal'] + $tax_value;
            }
            $data_product[$cml['total']] = $product_subtotal;
            $data_product[$cml['value_fields']][][$cml['value_field']] = array(
                $cml['name'] => $cml['spec_nomenclature'],
                $cml['value'] => $cml['product']
            );
            $data_product[$cml['value_fields']][][$cml['value_field']] = array(
                $cml['name'] => $cml['type_nomenclature'],
                $cml['value'] => $cml['product']
            );

            $data_products[][$cml['product']] = $data_product;
        }

        return $data_products;
    }

    public function parseArrayToXml($xml, $data_xml)
    {
        if (!empty($data_xml) && is_array($data_xml)) {
            foreach ($data_xml as $name_tag => $data_tag) {
                if (is_numeric($name_tag)) {
                    $this->parseArrayToXml($xml, $data_tag);

                } elseif ($name_tag == 'attribute') {
                    foreach ($data_tag as $k_attribute => $v_attribute) {
                        if ($k_attribute == 'text') {
                            $xml->text($v_attribute);
                        } else {
                            $xml->writeAttribute($k_attribute, $v_attribute);
                        }
                    }

                } else {
                    if (is_array($data_tag)) {
                        $xml -> startElement($name_tag);
                        $this->parseArrayToXml($xml, $data_tag);
                        $xml -> endElement();
                    } else {
                        $name_tag = str_replace(' ', '', $name_tag);
                        $xml -> writeElement($name_tag, $data_tag);
                    }
                }
            }
        }

        return $xml;
    }

    public function importFileOrders($xml, $lang_code)
    {
        $cml = $this->cml;
        if (isset($xml->{$cml['document']})) {
            $orders_data = $xml->{$cml['document']};

            $statuses = array();
            $data_status = fn_get_statuses('O');
            if (!empty($data_status)) {
                foreach ($data_status as $status) {
                    $statuses[$status['description']] = array(
                        'status' => $status['status'],
                        'description' => $status['description']
                    );
                }
            }

            foreach ($orders_data as $order_data) {
                $import_id = strval($order_data->{$cml['id']});
                $order_id = strval($order_data->{$cml['number']});

                //Check the database for an order with the specified ID exported from the accounting system
                $_order_id = $this->db->getField("SELECT order_id FROM ?:orders WHERE order_id = ?i", $import_id);
                if (!empty($_order_id)) {
                    $order_id = $_order_id;
                }

                foreach ($order_data->{$cml['value_fields']}->{$cml['value_field']} as $data_field) {
                    if (!empty($order_id) && ($data_field->{$cml['name']} == $cml['status_order']) && (!empty($statuses[strval($data_field->{$cml['value']})]))) {
                        $this->db->query("UPDATE ?:orders SET status = ?s WHERE order_id = ?i", $statuses[strval($data_field->{$cml['value']})]['status'], $order_id);
                    }
                }
            }
        }
    }

    public function exportFieldsToFile($fields_orders)
    {
        $export_fields = array();
        foreach ($fields_orders as $field_id => $field_value) {
            if (!empty($field_value)) {
                $profile_field = fn_get_profile_fields('ALL', array(), CART_LANGUAGE, array('field_id' => $field_id));

                if (!empty($profile_field['checkout_export_1c']) && $profile_field['checkout_export_1c'] == 'Y') {
                    $export_fields[$profile_field['description']]['description'] = $profile_field['description'];
                    $export_fields[$profile_field['description']]['value'] = $field_value;
                }
            }
        }

        return $export_fields;
    }

    public function dataOrderTaxs($taxes)
    {
        $data_taxes = array();
        $products_tax = array();
        $commerceml_tax = $this->db->getHash(
            "SELECT *"
            . " FROM ?:rus_exim_1c_taxes"
            . " WHERE company_id = ?i",
            'tax_id',
            $this->company_id
        );

        if (!empty($taxes)) {
            foreach ($taxes as $k_tax => $tax) {
                $tax_in_total = ($tax['price_includes_tax'] == 'Y') ? 'true' : 'false';
                $tax_value = (!empty($commerceml_tax[$k_tax])) ? $commerceml_tax[$k_tax]['tax_1c'] : $tax['rate_value'];
                $order_tax = array(
                    'name' => $tax['description'],
                    'value' => $tax_value,
                    'tax_in_total' => $tax_in_total,
                    'rate_value' => $tax['rate_value']
                );

                if (!empty($tax['applies']['items']['P'])) {
                    foreach ($tax['applies']['items']['P'] as $product_item => $product) {
                        $products_tax[$product_item][$k_tax] = $order_tax;
                    }

                    $data_taxes['products'] = $products_tax;
                }

                $data_taxes['orders'][$k_tax] = $order_tax;
            }
        }

        return $data_taxes;
    }

    /**
     * Checks if the imported file has the fields with the names that match
     * the names of price fields entered in the administration panel
     * 
     * @param object  $data_offers   The simplexml object with prices from the imported file.
     * @param int     $company_id    Company identifier
     *
     * @return The array with the results of the check.
    */
    public function checkPricesOffers($data_offers, $company_id)
    {
        $cml = $this->cml;
        $data_prices = $this->db->getArray("SELECT price_1c, type, usergroup_id FROM ?:rus_exim_1c_prices");

        if (!empty($company_id)) {
            $data_prices = $this->db->getArray("SELECT price_1c, usergroup_id, type FROM ?:rus_exim_1c_prices WHERE company_id = ?i", $company_id);
        }

        if (isset($data_offers->{$cml['prices_types']}->{$cml['price_type']})) {
            $data_prices = $this->validatePricesByFile($data_offers -> {$cml['prices_types']} -> {$cml['price_type']}, $data_prices);
        }

        return $data_prices;
    }

    public function dataProductCurrencies()
    {
        $data_currencies = array();
        $product_currencies = $this->db->getArray("SELECT * FROM ?:rus_commerceml_currencies");
        $currencies = Registry::get('currencies');

        foreach ($product_currencies as $product_currency) {
            foreach ($currencies as $currency) {
                if ($product_currency['currency_id'] == $currency['currency_id']) {
                    $data_currencies[$product_currency['commerceml_currency']]['coefficient'] = $currency['coefficient'];
                }
            }
        }

        return $data_currencies;
    }

    public function dataPriceOffers($prices)
    {
        $cml = $this->cml;
        $price_offers = array();
        $data_currencies = $this->dataProductCurrencies();

        foreach ($prices as $price) {
            $price_offers[strval($price->{$cml['price_id']})] = array(
                'currency_id' => strval($price -> {$cml['price_id']}),
                'currency' => strval($price -> {$cml['currency']})
            );

            if (!empty($data_currencies[strval($price -> {$cml['currency']})])) {
                $price_offers[strval($price->{$cml['id']})]['coefficient'] = $data_currencies[strval($price -> {$cml['currency']})]['coefficient'];
            } else {
                $price_offers[strval($price->{$cml['id']})]['coefficient'] = 1;
            }
        }

        return $price_offers;
    }

    public function conversionProductPrices($p_prices, $price_offers)
    {
        $cml = $this->cml;
        $product_prices = array();

        if (!empty($p_prices) && !empty($price_offers)) {
            foreach ($p_prices as $p_price) {
                $price = strval($p_price -> {$cml['price_per_item']});
                if (!empty($price_offers[strval($p_price -> {$cml['price_id']})]['coefficient'])) {
                    $price = $price * $price_offers[strval($p_price -> {$cml['price_id']})]['coefficient'];
                }

                $product_prices[strval($p_price -> {$cml['price_id']})] = array(
                    'price' => $price
                );
            }
        }

        return $product_prices;
    }
}