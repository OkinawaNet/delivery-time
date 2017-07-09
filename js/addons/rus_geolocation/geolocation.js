
(function(_, $) {
    $(document).ready(function() {
        var product_id = $('#geo_product_id').val();
        var result_ids = $('#result_ids').val();
        $.ceAjax('request', fn_url('geolocation.product_shipping_list?product_id=' + product_id + '&result_ids=' + result_ids), {
            result_ids: 'geolocation_shipping_methods',
            method: 'get',
            hidden: true
        });
    });
}(Tygh, Tygh.$));
