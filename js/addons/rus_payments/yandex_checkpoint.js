(function (_, $) {

    (function ($) {

        var methods = {
            /**
             * Initiates refund amount recalculator when refunding an order paid with Yandex.Checkpoint.
             *
             * @param recalc_trigger_elm JQuery selector of an element that triggers totals recalculation
             * @param amount_elm         JQuery selector of an element that contains refund totals
             */
            init_refund_form: function (recalc_trigger_elm, amount_elm) {
                $(recalc_trigger_elm).on('change', function (e) {
                    var sum = 0.0;
                    $('[data-ca-refund-value]:checked').each(function (i) {
                        sum += parseFloat($(this).data('caRefundValue'))
                            * parseFloat($('[data-ca-refund-amount-' + $(this).data('caCartId') + ']').val());
                    });

                    $(amount_elm).val(sum).trigger('blur');
                });

                setTimeout(function () {
                    $(recalc_trigger_elm + ':first').trigger('change');
                }, 100);
            },

            /**
             * Propagates VAT type selector with variants that can be used for current state of "Price includes tax" option.
             *
             * @param price_w_tax_elm JQuery selector of "Price includes tax" element
             * @param vats_elm        JQuery selector of VAT types list
             */
            load_vat_types: function (price_w_tax_elm, vats_elm) {
                var allVariants = $(vats_elm).data('caVatTypesAll');
                var activeVariants;
                if ($(price_w_tax_elm).is(':checked')) {
                    activeVariants = $(vats_elm).data('caVatTypesPriceIncluded');
                } else {
                    activeVariants = $(vats_elm).data('caVatTypesPriceExcluded');
                }
                for (var i in activeVariants) {
                    $(vats_elm).append(
                        '<option value="' + activeVariants[i] + '">' + allVariants[activeVariants[i]] + '</option>'
                    );
                }
                $(vats_elm).val($(vats_elm).data('caSelectedVatType'));
            },

            /**
             * Toggles VAT types based on state of "Price includes tax" option.
             *
             * @param price_w_tax_elm JQuery selector of "Price includes tax" element
             * @param vats_elm        JQuery selector of VAT types list
             */
            init_vat_type_selector: function (price_w_tax_elm, vats_elm) {
                $(price_w_tax_elm).on('change', function () {
                    $(vats_elm).find('option').remove();
                    methods.load_vat_types(price_w_tax_elm, vats_elm);
                });

                $(vats_elm).on('change', function () {
                    $(this).data('caSelectedVatType', $(this).val());
                });

                methods.load_vat_types(price_w_tax_elm, vats_elm);
            }
        };

        $.extend({
            ceYandexCheckpoint: function (method) {
                if (methods[method]) {
                    return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
                } else {
                    $.error('ty.yandex_checkpoint: method ' + method + ' does not exist');
                }
            }
        });

    })($);

    $(document).ready(function () {
        // refund form
        $.ceYandexCheckpoint('init_refund_form', '.yc-refund-recalculator', '#rus_payments_refund_amount');

        // vat types on tax update form
        $.ceYandexCheckpoint('init_vat_type_selector', '#elm_price_includes_tax', '#elm_yandex_checkpoint_vat_type');
    });

})(Tygh, Tygh.$);
