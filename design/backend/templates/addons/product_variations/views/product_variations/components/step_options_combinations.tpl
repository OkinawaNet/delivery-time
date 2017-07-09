<form id="select_options_variants_form" method="post" class="form-horizontal form-edit" name="select_options_variants_form">
    <input type="hidden" name="product_id" value="{$product_data.product_id}">

    <table width="100%" class="table table-middle">
        <thead>
            <tr>
                <th width="45%">{__("product")}</th>
                <th width="15%">{__("sku")}</th>
                <th width="15%">{__("price")}</th>
                <th width="15%">{__("list_price")}</th>
                <th width="10%">{__("quantity")}</th>
                <th width="10%">{__("weight")}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$combinations item="item"}
                <tr>
                    <td style="vertical-align: top">
                        <input type="text" value="{$item.name}" name="variations_data[{$item.variation}][name]" class="input-large">
                        <ul>
                            {foreach from=$item.options item="option"}
                                <li><span>{$option.option_name}: </span><span>{$option.variants[$option.value].variant_name}</span></li>
                            {/foreach}
                        </ul>
                    </td>
                    <td style="vertical-align: top">
                        <input type="text" value="{$item.code}" name="variations_data[{$item.variation}][code]" />
                    </td>
                    <td style="vertical-align: top">
                        <input type="text" value="{$item.price|fn_format_price:$primary_currency:null:false}" name="variations_data[{$item.variation}][price]" class="input-mini">
                    </td>
                    <td style="vertical-align: top">
                        <input type="text" value="{$item.list_price|fn_format_price:$primary_currency:null:false}" name="variations_data[{$item.variation}][list_price]" class="input-mini">
                    </td>
                    <td style="vertical-align: top">
                        <input type="text" value="{$item.amount}" name="variations_data[{$item.variation}][amount]" class="input-mini">
                    </td>
                    <td style="vertical-align: top">
                        <input type="text" value="{$item.weight}" name="variations_data[{$item.variation}][weight]" class="input-mini">
                    </td>
                    <td><a class="icon-trash cm-tooltip cm-delete-row" name="remove" id="{$item.variation}" title="{__("remove")}"></a></td>
                </tr>
            {/foreach}
        </tbody>
    </table>

    {capture name="buttons"}
        {include file="buttons/button.tpl" but_text=__("generate") but_name="dispatch[product_variations.generate]" but_role="submit-link" but_meta="btn-primary" but_target_form="select_options_variants_form" allow_href=true}
    {/capture}
</form>