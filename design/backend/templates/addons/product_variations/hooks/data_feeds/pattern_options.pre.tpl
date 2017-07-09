<div class="control-group">
    <label for="elm_datafeed_product_types" class="control-label">{__("product_type")}:</label>
    <div class="controls">
        <input type="hidden" name="datafeed_data[export_options][product_types]">
        <select name="datafeed_data[export_options][product_types][]" multiple="multiple" id="elm_datafeed_product_types">
            {foreach from=$product_types item="product_type_name" key="product_type_id"}
                <option {if $datafeed_data.export_options.product_types && in_array($product_type_id, $datafeed_data.export_options.product_types)}selected="selected"{/if} value="{$product_type_id}">{$product_type_name}</option>
            {/foreach}
        </select>
    </div>
</div>