{assign var="show_qty" value=false}
{if !$details_page}
    {if (!$product.hide_stock_info && !(($product_amount <= 0 || $product_amount < $product.min_qty) && ($product.avail_since > $smarty.const.TIME)))}
        <span class="ty-qty-out-of-stock ty-control-group__item" id="out_of_stock_info_{$obj_prefix}{$obj_id}">{$out_of_stock_text}</span>
    {/if}
{elseif (($product.out_of_stock_actions == "S") && ($product.tracking != "ProductTracking::TRACK_WITH_OPTIONS"|enum || $product.product_type === "\Tygh\Addons\ProductVariations\Product\Manager::PRODUCT_TYPE_CONFIGURABLE"|constant))}
    {$product_id = $product.product_id}

    {if $product.variation_product_id}
        {$product_id = $product.variation_product_id}
    {/if}

    <div class="ty-control-group">
        <label for="sw_product_notify_{$obj_prefix}{$obj_id}" class="ty-strong">
            <input id="sw_product_notify_{$obj_prefix}{$obj_id}" type="checkbox" class="checkbox cm-switch-availability cm-switch-visibility" name="product_notify" {if $product_notification_enabled == "Y"}checked="checked"{/if} onclick="
            {if !$auth.user_id}
                    if (!this.checked) {
                        Tygh.$.ceAjax('request', '{"products.product_notifications?enable="|fn_url}' + 'N&amp;product_id={$product_id}&amp;email=' + $('#product_notify_email_{$obj_prefix}{$obj_id}').get(0).value, {$ldelim}cache: false{$rdelim});
                    }
            {else}
                    Tygh.$.ceAjax('request', '{"products.product_notifications?enable="|fn_url}' + (this.checked ? 'Y' : 'N') + '&amp;product_id=' + '{$product_id}', {$ldelim}cache: false{$rdelim});
            {/if}
                    "/>{__("notify_when_back_in_stock")}
        </label>
    </div>
    {if !$auth.user_id }
        <div class="ty-control-group ty-input-append ty-product-notify-email {if $product_notification_enabled != "Y"}hidden{/if}" id="product_notify_{$obj_prefix}{$obj_id}">

            <input type="hidden" name="enable" value="Y" disabled />
            <input type="hidden" name="product_id" value="{$product_id}" disabled />

            <label id="product_notify_email_label" for="product_notify_email_{$obj_prefix}{$obj_id}" class="cm-required cm-email hidden">{__("email")}</label>
            <input type="text" name="email" id="product_notify_email_{$obj_prefix}{$obj_id}" size="20" value="{$product_notification_email|default:__("enter_email")}" class="ty-product-notify-email__input cm-hint" title="{__("enter_email")}" disabled />

            <button class="ty-btn-go cm-ajax" type="submit" name="dispatch[products.product_notifications]" title="{__("go")}"><i class="ty-btn-go__icon ty-icon-right-dir"></i></button>

        </div>
    {/if}
{/if}