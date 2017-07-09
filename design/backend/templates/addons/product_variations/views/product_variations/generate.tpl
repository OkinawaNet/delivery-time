{capture name="mainbox"}
    {include file="addons/product_variations/views/product_variations/components/step_{$step}.tpl"}
{/capture}

{include file="common/mainbox.tpl" title=__("product_variations.generating.title", ['[product]' => $product_data.product]) content=$smarty.capture.mainbox buttons=$smarty.capture.buttons}