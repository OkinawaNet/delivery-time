{if $product.product_type === "\Tygh\Addons\ProductVariations\Product\Manager::PRODUCT_TYPE_CONFIGURABLE"|constant}
    <li>{btn type="list" text=__("product_variations.variations") href="products.manage?parent_product_id=`$product.product_id`&product_type={"\Tygh\Addons\ProductVariations\Product\Manager::PRODUCT_TYPE_VARIATION"|constant}"}</li>
{/if}