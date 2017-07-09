<div class="row-fluid">
    <div class="group span6 form-horizontal">
        <div class="control-group">
            <label class="control-label" for="product_type">{__("product_variations.product_type")}</label>
            <div class="controls">
                {$product_types = $app["addons.product_variations.product.manager"]->getProductTypeNames()}
                <input type="hidden" name="product_type" id="product_type">
                <select name="product_type[]" id="product_type" multiple>
                    {foreach from=$product_types item="product_type_name" key="product_type"}
                        <option {if $product_type|in_array:$search.product_type}selected="selected"{/if} value="{$product_type}">{$product_type_name}</option>
                    {/foreach}
                </select>
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for="parent_product_id">{__("product_variations.parent_product")}</label>
            <div class="controls">
                {if $search.parent_product_id}
                    {$item_ids = $search.parent_product_id}
                {else}
                    {$item_ids = null}
                {/if}

                {include file="pickers/products/picker.tpl" input_name="parent_product_id[]" data_id="added_products" item_ids=$item_ids type="links" no_container=true picker_view=true}
            </div>
        </div>
    </div>
</div>