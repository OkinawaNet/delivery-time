{** block-description:geolocation **}

{script src="js/addons/rus_geolocation/func.js"}

<input type="hidden" name="url_geolocation" value="{$config.current_url}">
<input type="hidden" name="result_ids" value="geolocation_city_link">
<input type="hidden" id="geolocation_provider" value="{$addons.rus_geolocation.geolocation_provider}" />
{assign var="city" value=$smarty.request.city}
<div class="ty-geolocation" id="geolocation_city_link">
    <div>
        <input type="hidden" name="data_geolocation[geocity]" id="geocity" value="{$smarty.session.geocity}" />
        <label class="ty-geolocation-head-city">{__("addon.rus_geolocation.find_city")}: 

        {if !$smarty.session.geocity}
            {include file="common/popupbox.tpl" href="geolocation.popup_geo" link_text=__("addon.rus_geolocation.select_geocities") text=__("addon.rus_geolocation.select_city") id="geolocation_link" content="" but_meta="cm-dialog-opener hidden cm-dialog-auto-size"}
        {else}
            {include file="common/popupbox.tpl" href="geolocation.popup_geo" link_text=$smarty.session.geocity text=__("addon.rus_geolocation.select_city") content="" id="geolocation_link" but_meta="cm-dialog-opener hidden cm-dialog-auto-size"}
        {/if}
        </label>
    </div>
<!--geolocation_city_link--></div>
