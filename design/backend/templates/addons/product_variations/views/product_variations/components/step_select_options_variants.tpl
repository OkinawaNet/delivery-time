<form id="select_options_variants_form" action="{""|fn_url}" method="get" class="form-horizontal form-edit" name="select_options_variants_form">
    <input type="hidden" name="product_id" value="{$product_data.product_id}">
    {include file="common/subheader.tpl" title=__("product_variations.generating.select_variants")}

    {foreach from=$product_options item="option"}
        <div class="control-group">
            <label for="elm_{$option.option_id}" class="control-label">{$option.option_name}:</label>
            <div class="controls">
                <select multiple id="elm_{$option.option_id}" name="options_variant_ids[{$option.option_id}][]">
                    {foreach from=$option.variants item="variant"}
                        <option value="{$variant.variant_id}">{$variant.variant_name}</option>
                    {/foreach}
                </select>
            </div>
        </div>
    {/foreach}

    {capture name="buttons"}
        {include file="buttons/button.tpl" but_text=__("next") but_name="dispatch[product_variations.generate]" but_role="submit-link" but_meta="btn-primary" but_target_form="select_options_variants_form" allow_href=true}
    {/capture}
</form>