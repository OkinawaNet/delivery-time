<fieldset>
    {if $code == 'ems'}

        <div class="control-group">
            <label class="control-label" for="ship_ems_mode">{__("ems_mode")}</label>
            <div class="controls">
                <select id="ship_ems_mode" name="shipping_data[service_params][mode]">
                    <option value="regions" {if $shipping.service_params.mode == "regions"}selected="selected"{/if}>{__("ems_region")}</option>
                    <option value="cities" {if $shipping.service_params.mode == "cities"}selected="selected"{/if}>{__("ems_city")}</option>
                </select>
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_ems_delivery_time_plus">{__("ems_delivery_time_plus")}</label>
            <div class="controls">
                <input id="ship_ems_delivery_time_plus" type="text" name="shipping_data[service_params][delivery_time_plus]" size="30" value="{$shipping.service_params.delivery_time_plus}" />
            </div>
        </div>

    {elseif $code == 'russian_pochta'}

        <div class="control-group">
            <label for="ship_russian_post_sending_type" class="control-label">{__("shipping.russianpost.russian_post_sending_type")}:</label>
            <div class="controls">
                <select id="ship_russian_post_sending_type" name="shipping_data[service_params][sending_type]">
                    {foreach from=$sending_type item="s_type" key="k_type"}
                        <option value={$k_type} {if $shipping.service_params.sending_type == $k_type}selected="selected"{/if}>{$s_type}</option>
                    {/foreach}
                </select>
            </div>
        </div>

        <div class="control-group">
            <label for="ship_russian_post_sending_packages" class="control-label">{__("shipping.russianpost.russian_post_sending_packages")}:</label>
            <div class="controls">
                <select id="ship_russian_post_sending_package" name="shipping_data[service_params][sending_package]">
                    {foreach from=$sending_packages item="s_package" key="k_package"}
                        <option value={$k_package} {if $shipping.service_params.sending_package == $k_package}selected="selected"{/if}>{$s_package}</option>
                    {/foreach}
                </select>
            </div>
        </div>

        <div class="control-group">
            <label for="ship_russian_post_sending_categories" class="control-label">{__("shipping.russianpost.russian_post_sending_categories")}:</label>
            <div class="controls">
                <select id="ship_russian_post_sending_categories" name="shipping_data[service_params][sending_category]">
                    {foreach from=$sending_categories item="s_categories" key="k_categories"}
                        <option value={$k_categories} {if $shipping.service_params.sending_category == $k_categories}selected="selected"{/if}>{$s_categories}</option>
                    {/foreach}
                </select>
            </div>
        </div>

        <div class="control-group">
            <label for="ship_russian_post_shipping_option" class="control-label">{__("shipping.russianpost.russian_post_shipping_option")}:</label>
            <div class="controls">
                <select id="ship_russian_post_shipping_option" name="shipping_data[service_params][isavia]">
                    <option value="0" {if $shipping.service_params.isavia == "0"}selected="selected"{/if}>{__("addons.rus_russianpost.ground")}</option>
                    <option value="1" {if $shipping.service_params.isavia == "1"}selected="selected"{/if}>{__("addons.rus_russianpost.avia_possible")}</option>
                    <option value="2" {if $shipping.service_params.isavia == "2"}selected="selected"{/if}>{__("addons.rus_russianpost.avia")}</option>
                </select>
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_delivery">{__("shipping.russianpost.russian_post_cash_on_delivery")}:</label>
            <div class="controls">
                <input id="ship_russian_post_delivery" type="text" name="shipping_data[service_params][cash_on_delivery]" size="30" value="{$shipping.service_params.cash_on_delivery}" />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_delivery_notice">{__("shipping.russianpost.simple_delivery_notice")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][delivery_notice]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][delivery_notice]" value="1" {if $shipping.service_params.services.delivery_notice == "1"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_delivery_notice">{__("shipping.russianpost.registry_delivery_notice")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][registered_notice]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][registered_notice]" value="2" {if $shipping.service_params.services.registered_notice == "2"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_careful">{__("shipping.russianpost.russian_post_shipping_careful")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][careful]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][careful]" value="4" {if $shipping.service_params.services.careful == "4"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_ponderous">{__("shipping.russianpost.cumbersome_parcel")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][ponderous_parcel]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][ponderous_parcel]" value="6" {if $shipping.service_params.services.ponderous_parcel == "6"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_personally">{__("shipping.russianpost.personally")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][personally]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][personally]" value="8" {if $shipping.service_params.services.personally == "8"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_delivery_product">{__("shipping.russianpost.delivery_product")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][delivery_product]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][delivery_product]" value="10" {if $shipping.service_params.services.delivery_product == "10"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_oversize">{__("shipping.russianpost.oversize")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][oversize]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][oversize]" value="12" {if $shipping.service_params.services.oversize == "12"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_insurance">{__("shipping.russianpost.russian_post_shipping_insurance")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][insurance]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][insurance]" value="14" {if $shipping.service_params.services.insurance == "14"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_cash_sender">{__("shipping.russianpost.cash_sender")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][cash_sender]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][cash_sender]" value="24" {if $shipping.service_params.services.cash_sender == "24"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_check_investment">{__("shipping.russianpost.check_investment")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][check_investment]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][check_investment]" value="22" {if $shipping.service_params.services.check_investment == "22"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_compliance_investment">{__("shipping.russianpost.compliance_investment")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][compliance_investment]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][compliance_investment]" value="23" {if $shipping.service_params.services.compliance_investment == "23"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_delivery_courier">{__("shipping.russianpost.delivery_courier")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][delivery_courier]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][delivery_courier]" value="26" {if $shipping.service_params.services.delivery_courier == "26"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_package_pochta">{__("shipping.russianpost.package_pochta")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][package_pochta]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][package_pochta]" value="27" {if $shipping.service_params.services.package_pochta == "27"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_delivery_report">{__("shipping.russianpost.delivery_report")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][delivery_report]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][delivery_report]" value="33" {if $shipping.service_params.services.delivery_report == "33"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_add_barcode">{__("shipping.russianpost.add_barcode")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][add_barcode]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][add_barcode]" value="34" {if $shipping.service_params.services.add_barcode == "34"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_packaging_items">{__("shipping.russianpost.packaging_items")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][packaging_items]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][packaging_items]" value="35" {if $shipping.service_params.services.packaging_items == "35"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_add_sticker">{__("shipping.russianpost.add_sticker")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][add_sticker]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][add_sticker]" value="36" {if $shipping.service_params.services.add_sticker == "36"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_shipping_delivery">{__("shipping.russianpost.shipping_delivery")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][shipping_delivery]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][shipping_delivery]" value="37" {if $shipping.service_params.services.shipping_delivery == "37"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_check_suite">{__("shipping.russianpost.check_suite")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][check_suite]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][check_suite]" value="38" {if $shipping.service_params.services.check_suite == "38"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_return_statement">{__("shipping.russianpost.return_statement")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][return_statement]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][return_statement]" value="39" {if $shipping.service_params.services.return_statement == "39"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_delivery_far_place">{__("shipping.russianpost.delivery_far_place")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][delivery_far_place]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][delivery_far_place]" value="40" {if $shipping.service_params.services.delivery_far_place == "40"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_sms_unit_send">{__("shipping.russianpost.sms_unit_send")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][sms_unit_send]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][sms_unit_send]" value="41" {if $shipping.service_params.services.sms_unit_send == "41"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_sms_unit_recipient">{__("shipping.russianpost.sms_unit_recipient")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][sms_unit_recipient]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][sms_unit_recipient]" value="42" {if $shipping.service_params.services.sms_unit_recipient == "42"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_sms_part_send">{__("shipping.russianpost.sms_part_send")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][sms_part_send]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][sms_part_send]" value="43" {if $shipping.service_params.services.sms_part_send == "43"}checked="checked"{/if} />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_sms_part_recipient">{__("shipping.russianpost.sms_part_recipient")}:</label>
            <div class="controls">
                <input type="hidden" name="shipping_data[service_params][services][sms_part_recipient]" value="N" />
                <input type="checkbox" name="shipping_data[service_params][services][sms_part_recipient]" value="44" {if $shipping.service_params.services.sms_part_recipient == "44"}checked="checked"{/if} />
            </div>
        </div>

        {include file="common/subheader.tpl" title=__("shippings.russianpost.data_tracking")}

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_login">{__("shipping.russianpost.russian_post_login")}:</label>
            <div class="controls">
                <input id="ship_russian_post_login" type="text" name="shipping_data[service_params][api_login]" size="30" value="{$shipping.service_params.api_login}" />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="ship_russian_post_password">{__("shipping.russianpost.russian_post_password")}:</label>
            <div class="controls">
                <input id="ship_russian_post_password" type="text" name="shipping_data[service_params][api_password]" size="30" value="{$shipping.service_params.api_password}" />
            </div>
        </div>

    {elseif $code == 'russian_post_calc'}

        <div class="control-group">
            <label class="control-label" for="user_key">{__("authentication_key")}</label>
            <div class="controls">
                <input id="user_key" type="text" name="shipping_data[service_params][user_key]" size="30" value="{$shipping.service_params.user_key}"/>
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="user_key_password">{__("authentication_password")}</label>
            <div class="controls">
                <input id="user_key_password" type="password" name="shipping_data[service_params][user_key_password]" size="30" value="{$shipping.service_params.user_key_password}" />
            </div>
        </div>

        <div class="control-group">
            <label class="control-label" for="package_type">{__("russianpost_shipping_type")}</label>
            <div class="controls">
                <select id="package_type" name="shipping_data[service_params][shipping_type]">
                    <option value="rp_main" {if $shipping.service_params.shipping_type == "rp_main"}selected="selected"{/if}>{__("ship_russianpost_shipping_type_rp_main")}</option>
                    <option value="rp_1class" {if $shipping.service_params.shipping_type == "rp_1class"}selected="selected"{/if}>{__("ship_russianpost_shipping_type_rp_1class")}</option>
                </select>
            </div>
        </div>

        <span>{__("ship_russianpost_register_text")}</span>
    {/if}

</fieldset>

{if $code == 'russian_post'}
<script type="text/javascript">
//<![CDATA[
var elm = Tygh.$('#ship_russian_post_shipping_type');
fn_disable_rupost_package_type(elm);
elm.on('change', function(e) {$ldelim}
    fn_disable_rupost_package_type(Tygh.$(this));
{$rdelim});
function fn_disable_rupost_package_type(elm) {$ldelim}
    if (elm.val() == 'air') {$ldelim}
        Tygh.$('#ship_russian_post_package_type').find('[value="cen_band"],[value="cen_pos"]').attr('disabled', 'disabled');
    {$rdelim} else {$ldelim}
        Tygh.$('#ship_russian_post_package_type').find('[value="cen_band"],[value="cen_pos"]').removeAttr('disabled');
    {$rdelim}
{$rdelim}
//]]>
</script>
{/if}
