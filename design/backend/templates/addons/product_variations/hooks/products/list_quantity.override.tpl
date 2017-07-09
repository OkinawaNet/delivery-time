{if $product.product_type === "\Tygh\Addons\ProductVariations\Product\Manager::PRODUCT_TYPE_CONFIGURABLE"|constant}
    {include file="buttons/button.tpl" but_text=__("edit") but_href="products.manage?parent_product_id=`$product.product_id`&product_type={"\Tygh\Addons\ProductVariations\Product\Manager::PRODUCT_TYPE_VARIATION"|constant}" but_role="edit"}
{/if}