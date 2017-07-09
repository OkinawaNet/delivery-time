<div class="ty-control-group">
    {if $cart.user_data.phone}
        {assign var="phone" value=$cart.user_data.phone}
    {else}
        {if $cart.user_data.b_phone && !$cart.user_data.s_phone}
            {assign var="phone" value=$cart.user_data.b_phone}
        {elseif !$cart.user_data.b_phone && $cart.user_data.s_phone}
            {assign var="phone" value=$cart.user_data.s_phone}
        {elseif $cart.user_data.b_phone && $cart.user_data.s_phone}
            {if $settings.Checkout.address_position == "billing_first"}
                {assign var="phone" value=$cart.user_data.b_phone}
            {else}
                {assign var="phone" value=$cart.user_data.s_phone}
            {/if}
        {else}
            {assign var="phone" value=""}
        {/if}
    {/if}
    <label for="customer_phone" class="ty-control-group__title">{__("phone")}</label>
    <input id="customer_phone" size="35" type="text" name="payment_info[customer_phone]" value="{$phone}" class="ty-input-text cm-autocomplete-off" />
</div>
