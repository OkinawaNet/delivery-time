<div class="ty-min-qty-description cm-reload-{$obj_prefix}{$obj_id}" id="qty_description_{$obj_prefix}{$obj_id}">
    {if $min_qty && $product.min_qty}
        {__("text_cart_min_qty", ["[product]" => $product.product, "[quantity]" => $product.min_qty])}.
    {/if}
<!--qty_description_{$obj_prefix}{$obj_id}--></div>