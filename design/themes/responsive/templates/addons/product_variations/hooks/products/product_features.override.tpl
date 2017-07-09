{if $show_features}
    {if !$product.product_features}
        {$product.product_features = $product|fn_get_product_features_list}
    {/if}

    <div class="cm-reload-{$obj_prefix}{$obj_id}" id="product_features_update_{$obj_prefix}{$obj_id}">
        <input type="hidden" name="appearance[show_features]" value="{$show_features}" />
        {include file="views/products/components/product_features_short_list.tpl" features=$product.product_features no_container=true}
    <!--product_features_update_{$obj_prefix}{$obj_id}--></div>
{/if}