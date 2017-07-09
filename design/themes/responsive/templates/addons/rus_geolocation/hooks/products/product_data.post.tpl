{capture name="product_edp_`$obj_id`"}
    {if $show_edp && $product.is_edp == "Y"}
        <p class="ty-edp-description">{__("text_edp_product")}.</p>
        <input type="hidden" name="product_data[{$obj_id}][is_edp]" value="Y" />
    {/if}

    {if $show_edp && !$quick_view}
    <div class="cm-ajax ty-block-shipping-geolocation" id="block-info-geolocation">
        {include file="addons/rus_geolocation/views/geolocation/product_shipping_list.tpl"
        object=$shipping_methods}
    </div>
    {/if}
{/capture}
