
<script src="//api-maps.yandex.ru/2.1/?lang=ru_RU" type="text/javascript"></script>
<input type="hidden" name="result_ids" value="geolocation_block, map">
<input type="hidden" name="pull_url_geolocation" value="{$config.current_url}">

<div class="ty-block-geolocation">
    <div class="ty-block-geolocation_list">
        <div id="geolocation_block">
            <form name="geolocation_form" action="{""|fn_url}" method="post" class="ty-form-geolocation-city cm_ajax cm-ajax-full-render cm-form-dialog-closer">
                <input type="hidden" id="default_city" name="default_city" value="{$settings.General.default_city}">

                <input type="hidden" name="data_geolocation[geocity]" id="geocity" value="{$smarty.session.geocity}" />
                <div class="ty-control-group ty-geolocation-city">
                    <label  class="ty-control-group__label">{__("addon.rus_geolocation.find_city")}</label>
                    <input type="text" id="auto_geocity" class="ty-search-block__input" name="data_geolocation[geocity]" {if $smarty.session.geocity}value="{$smarty.session.geocity}"{else}value="{$geocity}"{/if} x-autocomplete="auto_geocity" autocomplete="on" onkeypress="if(event.keyCode == 13) return false;" />
                </div>
                <hr />

                {if is_array($data_cities)}
                    <div class="ty-geolocation_list_cities" data-ce-top="100" data-ce-padding="20" >
                        <ul>
                            {$count = 0}
                            {foreach from=$data_cities item="data_city"}
                                <li>
                                    <a class="cm-dialog-closer" id="choose-list-city" onclick="fn_get_geolocation_choose_city('{$data_city.city}', 'geolocation_city_link')">{$data_city.city}</a>
                                </li>
                            {/foreach}
                        </ul>
                    </div>
                {/if}
            </form>
        <!--geolocation_block--></div>
    </div>

    <div class="ty-block-geolocation_map">
        <div id="map" class="ty-geo_map">
            <input type="hidden" name="result_ids" value="map">
        </div>
    </div>
</div>

<div class="buttons-container clearfix buttons-container-picker">
    <a class="ty-btn__primary ty-btn__big ty-btn cm-dialog-closer" onclick="fn_get_geolocation_button_city('{$smarty.session.geocity}')" id="select_city1">{__("addon.rus_geolocation.choose_cities")}</a>
</div>
