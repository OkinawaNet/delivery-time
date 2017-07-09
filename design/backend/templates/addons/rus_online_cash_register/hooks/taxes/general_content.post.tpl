{script src="js/addons/rus_online_cash_register/settings.js"}

<div class="control-group {if $tax.price_includes_tax != "Y"}hidden{/if}" id="control_group_cash_register_tax_included">
    <label class="control-label" for="elm_tax_rus_online_cash_register_price_included_{$id}">{__("rus_online_cash_register.tax_use_online_cash_register")}:</label>
    <div class="controls">
        <select name="tax_data[cash_register_tax_id]" {if $tax.price_includes_tax != "Y"}disabled{/if} id="elm_tax_rus_online_cash_register_price_included_{$id}">
            {foreach from=$cash_register_taxes key="item_id" item="item"}
                {if !isset($item.is_tax_included) || $item.is_tax_included}
                    <option {if $cash_register_tax_id == $item_id}selected="selected"{/if} value="{$item_id}">{$item.name}</option>
                {/if}
            {/foreach}
        </select>
    </div>
</div>
<div class="control-group {if $tax.price_includes_tax == "Y"}hidden{/if}" id="control_group_cash_register_tax_excluded">
    <label class="control-label" for="elm_tax_rus_online_cash_register_price_excluded_{$id}">{__("rus_online_cash_register.tax_use_online_cash_register")}:</label>
    <div class="controls">
        <select name="tax_data[cash_register_tax_id]" {if $tax.price_includes_tax == "Y"}disabled{/if} id="elm_tax_rus_online_cash_register_price_excluded_{$id}">
            {foreach from=$cash_register_taxes key="item_id" item="item"}
                {if !isset($item.is_tax_included) || !$item.is_tax_included}
                    <option {if $cash_register_tax_id == $item_id}selected="selected"{/if} value="{$item_id}">{$item.name}</option>
                {/if}
            {/foreach}
        </select>
    </div>
</div>