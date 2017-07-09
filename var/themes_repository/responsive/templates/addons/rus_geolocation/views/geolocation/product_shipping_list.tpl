{script src="js/addons/rus_geolocation/geolocation.js"}

<input type="hidden" id="geo_product_id" value="{$product.product_id}" />
<input type="hidden" name="url_geo_shipping" value="{$config.current_url}">

<div class="cm-ajax" id="geolocation_shipping_methods">
{if $smarty.session.geocity && $show_data}
    <input type="hidden" id="result_ids" value="geolocation_shipping_methods" />

    {if $addons.rus_geolocation.geolocation_shippings == "Y"}
    <div class="ty-geo-shipping__wrapper" id="shipping_methods">
        {if $shipping_methods}
            <span class="ty-geo_title">{__("addon.rus_geolocation.title_shippings", ["[city]" => $smarty.session.geocity])}</span>
            <span class="ty-geo_additional_title">{__("addon.rus_geolocation.title_calculate_shippings")}</span>
            <div>
                <ul>
                {foreach from=$shipping_methods item="shipping_method" name="geolocation_shipping_methods"}
                    <li class="ty-geo_shipping__item {if $smarty.foreach.geolocation_shipping_methods.iteration > 3}hidden{/if}">
                        <span class="ty-geo_shipping__name">{$shipping_method.shipping}</span>
                        <span class="ty-control-group__label">{include file="common/price.tpl" value=$shipping_method.rate class="ty-geo_shipping_price"}</span>
                        <span class="ty-geo_delivery_time">{if $shipping_method.service_delivery_time}{$shipping_method.service_delivery_time}{else}-{/if}</span>
                    </li>
                {/foreach}
                </ul>
                {if $shipping_methods|count > 3}
                    <div class="ty-geo_shipping__item">
                        <a id="button_shippings" onclick="Tygh.$('.ty-geo_shipping__item').removeClass('hidden'); Tygh.$('#button_shippings').toggle();">{__("view_all")}</a>
                    </div>
                {/if}
            </div>
        {else}
            <span class="ty-geo_title">{__("addon.rus_geolocation.title_not_shippings", ["[city]" => $smarty.session.geocity])}</span>
        {/if}
    </div>
    {/if}
{/if}
<!--geolocation_shipping_methods--></div>
