<div itemscope itemtype="http://schema.org/Product">
    <meta itemprop="sku" content="{$product.seo_snippet.sku}" />
    <meta itemprop="name" content="{$product.seo_snippet.name}" />
    <meta itemprop="description" content="{$product.seo_snippet.description}" />

    <div itemprop="offers" itemscope itemtype="http://schema.org/Offer">
        <link itemprop="availability" href="http://schema.org/{$product.seo_snippet.availability}" />
        {if $product.seo_snippet.show_price}
            <meta itemprop="priceCurrency" content="{$product.seo_snippet.price_currency}"/>
            <meta itemprop="price" content="{$product.seo_snippet.price}"/>
        {/if}
    </div>

    {hook name="products:seo_snippet_attributes"}
    {/hook}

</div>