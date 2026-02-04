/**
 * Simple Checkout Lite - JavaScript
 * AJAX обновление методов доставки, оплаты и итогов
 */
(function($) {
    'use strict';

    var SCL = {
        init: function() {
            this.bindEvents();
            this.loadZones();
            this.updateAll();
        },

        bindEvents: function() {
            var self = this;

            // Country change - reload zones
            $('#input-country').on('change', function() {
                self.loadZones();
                self.updateAll();
            });

            // Zone change - update shipping/payment
            $('#input-zone').on('change', function() {
                self.updateAll();
            });

            // Address fields change - update shipping/payment
            $('#input-address-1, #input-city, #input-postcode').on('change', function() {
                self.updateAll();
            });

            // Shipping method change
            $(document).on('change', 'input[name="shipping_method"]', function() {
                self.setShippingMethod($(this).val());
            });

            // Payment method change
            $(document).on('change', 'input[name="payment_method"]', function() {
                self.setPaymentMethod($(this).val());
            });

            // Confirm button
            $('#button-confirm').on('click', function() {
                self.confirmOrder();
            });
        },

        loadZones: function() {
            var country_id = $('#input-country').val();
            var zone_id = $('#input-zone').data('zone-id');

            if (!country_id) {
                $('#input-zone').html('<option value="">-- Выберите --</option>');
                return;
            }

            $.ajax({
                url: SimpleCheckoutLite.urls.zone + '&country_id=' + country_id,
                dataType: 'json',
                success: function(json) {
                    var html = '<option value="">-- Выберите --</option>';
                    if (json.length > 0) {
                        for (var i = 0; i < json.length; i++) {
                            if (json[i].zone_id == zone_id) {
                                html += '<option value="' + json[i].zone_id + '" selected="selected">' + json[i].name + '</option>';
                            } else {
                                html += '<option value="' + json[i].zone_id + '">' + json[i].name + '</option>';
                            }
                        }
                    }
                    $('#input-zone').html(html);
                }
            });
        },

        updateAll: function() {
            var self = this;

            // First save customer data
            this.saveCustomerData(function() {
                // Then load shipping methods
                if (SimpleCheckoutLite.shipping_required && SimpleCheckoutLite.show_shipping_method) {
                    self.loadShippingMethods(function() {
                        // After shipping, load payment methods
                        self.loadPaymentMethods();
                    });
                } else {
                    self.loadPaymentMethods();
                }
            });
        },

        saveCustomerData: function(callback) {
            var formData = $('#simple-checkout-form').serialize();

            $.ajax({
                url: SimpleCheckoutLite.urls.save,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(json) {
                    // Clear previous errors
                    $('.form-group').removeClass('has-error');
                    $('.text-danger').remove();

                    if (json.error) {
                        for (var field in json.error) {
                            var $input = $('#input-' + field.replace('_', '-'));
                            $input.closest('.form-group').addClass('has-error');
                            $input.after('<div class="text-danger">' + json.error[field] + '</div>');
                        }
                    }

                    if (callback) callback(json);
                },
                error: function(xhr, status, error) {
                    console.error('Save error:', error);
                    if (callback) callback({error: true});
                }
            });
        },

        loadShippingMethods: function(callback) {
            var $container = $('#shipping-methods');
            $container.html('<p><i class="fa fa-spinner fa-spin"></i> Загрузка...</p>');

            $.ajax({
                url: SimpleCheckoutLite.urls.shipping,
                dataType: 'json',
                success: function(json) {
                    if (json.error) {
                        $container.html('<div class="alert alert-warning">' + json.error + '</div>');
                    } else if (json.shipping_methods) {
                        var html = '';
                        for (var code in json.shipping_methods) {
                            var shipping = json.shipping_methods[code];
                            if (shipping.error) {
                                html += '<div class="alert alert-warning">' + shipping.error + '</div>';
                            } else {
                                html += '<p><strong>' + shipping.title + '</strong></p>';
                                for (var quote_code in shipping.quote) {
                                    var quote = shipping.quote[quote_code];
                                    var value = code + '.' + quote_code;
                                    var checked = (json.shipping_selected == value) ? ' checked="checked"' : '';
                                    html += '<div class="radio">';
                                    html += '<label>';
                                    html += '<input type="radio" name="shipping_method" value="' + value + '"' + checked + ' />';
                                    html += quote.title + ' - ' + quote.text;
                                    html += '</label>';
                                    html += '</div>';
                                }
                            }
                        }
                        if (html) {
                            $container.html(html);
                        } else {
                            $container.html('<div class="alert alert-info">Нет доступных способов доставки</div>');
                        }
                    }

                    if (callback) callback(json);
                },
                error: function(xhr, status, error) {
                    console.error('Shipping error:', error);
                    $container.html('<div class="alert alert-danger">Ошибка загрузки</div>');
                    if (callback) callback({error: true});
                }
            });
        },

        loadPaymentMethods: function(callback) {
            var $container = $('#payment-methods');
            $container.html('<p><i class="fa fa-spinner fa-spin"></i> Загрузка...</p>');

            $.ajax({
                url: SimpleCheckoutLite.urls.payment,
                dataType: 'json',
                success: function(json) {
                    if (json.error) {
                        $container.html('<div class="alert alert-warning">' + json.error + '</div>');
                    } else if (json.payment_methods) {
                        var html = '';
                        for (var code in json.payment_methods) {
                            var payment = json.payment_methods[code];
                            var checked = (json.payment_selected == code) ? ' checked="checked"' : '';
                            html += '<div class="radio">';
                            html += '<label>';
                            html += '<input type="radio" name="payment_method" value="' + code + '"' + checked + ' />';
                            if (payment.image) {
                                html += '<img src="' + payment.image + '" alt="' + payment.title + '" /> ';
                            }
                            html += payment.title;
                            html += '</label>';
                            html += '</div>';
                        }
                        if (html) {
                            $container.html(html);
                        } else {
                            $container.html('<div class="alert alert-info">Нет доступных способов оплаты</div>');
                        }
                    }

                    // Update totals
                    if (json.totals) {
                        var totalsHtml = '';
                        for (var i = 0; i < json.totals.length; i++) {
                            totalsHtml += '<tr>';
                            totalsHtml += '<td class="text-right"><strong>' + json.totals[i].title + ':</strong></td>';
                            totalsHtml += '<td class="text-right">' + json.totals[i].text + '</td>';
                            totalsHtml += '</tr>';
                        }
                        $('#totals-table tbody').html(totalsHtml);
                    }

                    if (callback) callback(json);
                },
                error: function(xhr, status, error) {
                    console.error('Payment error:', error);
                    $container.html('<div class="alert alert-danger">Ошибка загрузки</div>');
                    if (callback) callback({error: true});
                }
            });
        },

        setShippingMethod: function(method) {
            var self = this;

            $.ajax({
                url: 'index.php?route=extension/module/simple_checkout_lite/setShipping',
                type: 'POST',
                data: { shipping_method: method },
                dataType: 'json',
                success: function(json) {
                    if (json.success) {
                        self.loadPaymentMethods();
                    }
                }
            });
        },

        setPaymentMethod: function(method) {
            $.ajax({
                url: 'index.php?route=extension/module/simple_checkout_lite/setPayment',
                type: 'POST',
                data: { payment_method: method },
                dataType: 'json',
                success: function(json) {
                    // Payment set
                }
            });
        },

        confirmOrder: function() {
            var self = this;
            var $btn = $('#button-confirm');

            // Disable button
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Обработка...');

            // First save customer data
            this.saveCustomerData(function(saveJson) {
                if (saveJson.error) {
                    $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Подтвердить заказ');
                    return;
                }

                // Then confirm order
                var formData = $('#simple-checkout-form').serialize();

                $.ajax({
                    url: SimpleCheckoutLite.urls.confirm,
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(json) {
                        if (json.redirect) {
                            location = json.redirect;
                        } else if (json.error) {
                            alert(json.error);
                            $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Подтвердить заказ');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Confirm error:', error);
                        alert('Произошла ошибка. Попробуйте еще раз.');
                        $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Подтвердить заказ');
                    }
                });
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if (typeof SimpleCheckoutLite !== 'undefined') {
            SCL.init();
        }
    });

})(jQuery);
