var myMap;

(function(_, $) {
    $(document).ready(function() {
        var city = $('#geocity').val();

        if (!city) {
            $('#opener_geolocation_link').trigger('click');

            if ('map' in window) {
                ymaps.ready(init);
            }
        }
    });

    $.ceEvent('on', 'ce.commoninit', function(context) {
        $("#auto_geocity").autocomplete({
            source: function( request, response ) {
                getListCities(request, response);
            },
            open: function () {
                var dialog = $(this).closest('.ui-dialog');
                if(dialog.length > 0){
                    $('.ui-autocomplete.ui-front').zIndex(dialog.zIndex()+1);
                }
            }
        });

        var maps = context.find('#map');
        var city = $('#geocity').val();
        if (maps.length) {
            if (city) {
                ymaps.ready(init_map);
            } else {
                ymaps.ready(init);
            }
        }

        function init_map () {
            myMap = new ymaps.Map('map', {
                center: [55, 34],
                zoom: 9
            });

            if ($('#geocity').val()) {
                ymaps.geocode($('#geocity').val(), {
                    results: 1
                }).then(function (result) {
                    var firstGeoObject = result.geoObjects.get(0),
                        coords = firstGeoObject.geometry.getCoordinates(),
                        bounds = firstGeoObject.properties.get('boundedBy');

                    myMap.geoObjects.add(firstGeoObject);
                    myMap.setBounds(bounds, {
                        checkZoomRange: true
                    });
                });
            }
        }

        function getListCities(request, response) {
            $.ceAjax('request', fn_url('geolocation.autocomplete_city?q=' + encodeURIComponent(request.term)), {
                callback: function(data) {
                    response(data.autocomplete);
                }
            });
        }
    });

    function init()
    {
        var description_city = '';
        var city = '';
        var country_code = '';
        var geolocation = ymaps.geolocation;
        myMap = new ymaps.Map('map', {
            center: [55, 34],
            zoom: 9
        });

        if (!$('#geocity').val()) {
            var geolocation_provider = $('#geolocation_provider').val();

            if (!geolocation_provider) {
                geolocation_provider = 'browser';
            }

            geolocation.get({
                provider: geolocation_provider,
                mapStateAutoApply: true
            }).then(fn_get_geolocation_result, fn_get_geolocation_error);
        } else {
            ymaps.geocode($('#geocity').val(), {
                results: 1
            }).then(function (result) {
                var firstGeoObject = result.geoObjects.get(0),
                    bounds = firstGeoObject.properties.get('boundedBy');

                myMap.geoObjects.add(firstGeoObject);
                myMap.setBounds(bounds, {
                    checkZoomRange: true
                });
            });

            fn_get_geolocation_new_city($('#geocity').val());
        }

        function fn_get_geolocation_result(result)
        {
            var firstGeoObject = result.geoObjects.get(0);
            var select_city = $('#geocity').val();

            description_city = firstGeoObject.properties.get('description');
            city = firstGeoObject.properties.get('metaDataProperty.GeocoderMetaData.AddressDetails.Country.AdministrativeArea.SubAdministrativeArea.Locality.LocalityName');
            country_code = firstGeoObject.properties.get('metaDataProperty.GeocoderMetaData.AddressDetails.Country.CountryNameCode');

            if (select_city) {
                city = select_city;
            }
            $('#geocity').val(city);

            ymaps.geocode(city, {
                results: 1
            }).then(function (result) {
                var firstGeoObject = result.geoObjects.get(0),
                    bounds = firstGeoObject.properties.get('boundedBy');

                myMap.geoObjects.add(firstGeoObject);
                myMap.setBounds(bounds, {
                    checkZoomRange: true
                });
            });

            fn_get_geolocation_new_city(city);
        }

        function fn_get_geolocation_error(error)
        {
            city = $('#geocity').val();
            if (!city) {
                city = $('#default_city').val();
                $('#geocity').val(city);
                fn_get_geolocation_new_city(city);
            }

            ymaps.geocode(city, {
                results: 1
            }).then(function (result) {
                var firstGeoObject = result.geoObjects.get(0),
                    coords = firstGeoObject.geometry.getCoordinates(),
                    bounds = firstGeoObject.properties.get('boundedBy');

                myMap.geoObjects.add(firstGeoObject);
                myMap.setBounds(bounds, {
                    checkZoomRange: true
                });
            });
        }
    }
}(Tygh, Tygh.$));

function fn_get_geolocation_new_city(city, result_ids)
{
    var url = $('input[name=pull_url_geolocation]').val();

    $.ceAjax('request', url, {
        result_ids: 'geolocation_block',
        method: 'post',
        full_render: true,
        data: {
            geocity: city
        }
    });
}

function fn_get_geolocation_choose_city(city, result_ids)
{
    var geo_url = $('input[name=url_geolocation]').val();
    var url = $('input[name=pull_url_geolocation]').val();

    $.ceAjax('request', url, {
        result_ids: result_ids,
        method: 'post',
        full_render: true,
        data: {
            geocity: city,
            url: geo_url
        }
    });

    if ('geo_product_id' in window) {
        result_ids = 'geolocation_shipping_methods';
        product_id = $("#geo_product_id").val();

        $.ceAjax('request', fn_url('geolocation.product_shipping_list?product_id=' + product_id + '&result_ids=' + result_ids), {
            result_ids: result_ids,
            method: 'get'
        });
    }
}

function fn_get_geolocation_button_city(city)
{
    var geo_url = $('input[name=url_geolocation]').val();
    var url = $('input[name=pull_url_geolocation]').val();
    var auto_geocity = $("#auto_geocity").val();
    var result_ids = 'geolocation_city_link';

    if (auto_geocity || !city) {
        city = auto_geocity;
    }

    $.ceAjax('request', url, {
        result_ids: result_ids,
        method: 'post',
        full_render: true,
        data: {
            geocity: city,
            url: geo_url
        }
    });

    if ('geo_product_id' in window) {
        result_ids = 'geolocation_shipping_methods';
        product_id = $("#geo_product_id").val();

        $.ceAjax('request', fn_url('geolocation.product_shipping_list?product_id=' + product_id + '&result_ids=' + result_ids), {
            result_ids: result_ids,
            method: 'get'
        });
    }
}

