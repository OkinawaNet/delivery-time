<div class="control-group">
    <label for="elm_yandex_checkpoint_vat_type"
           class="control-label"
    >
        {__("addons.rus_payments.yandex_checkpoint.vat_type")}
        {include file="common/tooltip.tpl" tooltip=__("addons.rus_payments.yandex_checkpoint.will_be_passed_to_checkpoint")}
    </label>
    <div class="controls">
        <select name="tax_data[yandex_checkpoint_vat_type]"
                id="elm_yandex_checkpoint_vat_type"
                data-ca-vat-types-all="{$yandex_checkpoint_vat_types|json_encode}"
                data-ca-vat-types-price-included="{$yandex_checkpoint_vat_types_price_included|json_encode}"
                data-ca-vat-types-price-excluded="{$yandex_checkpoint_vat_types_price_excluded|json_encode}"
                data-ca-selected-vat-type="{$tax.yandex_checkpoint_vat_type}"
        >
        </select>
    </div>
</div>