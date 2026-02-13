/**
 * Simple Checkout Lite - Unified JavaScript
 * Reads configuration from global SimpleCheckoutLite object defined in template.
 */
(function($) {
    'use strict';

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function debounce(fn, delay) {
        var timer;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function() {
                fn.apply(context, args);
            }, delay);
        };
    }

    var SCL = {
        config: null,

        init: function(config) {
            this.config = config;
            this.bindEvents();
            this.loadZones();
            this.updateAll();
            this.validateForm();
        },

        bindEvents: function() {
            var self = this;
            var debouncedUpdate = debounce(function() {
                self.updateAll();
            }, 500);

            $('#simple-checkout-form').on('input change', 'input, select, textarea', function() {
                self.validateForm();
            });

            $('#input-country').on('change', function() {
                self.loadZones();
                self.updateAll();
            });

            $('#input-zone').on('change', function() {
                self.updateAll();
            });

            $('#input-address-1, #input-city, #input-postcode').on('change', debouncedUpdate);

            $(document).on('change', 'input[name="shipping_method"]', function() {
                self.setShippingMethod($(this).val());
            });

            $(document).on('change', 'input[name="payment_method"]', function() {
                self.setPaymentMethod($(this).val());
            });

            $('#button-confirm').on('click', function() {
                self.confirmOrder();
            });
        },

        loadZones: function() {
            var country_id = $('#input-country').val();
            var zone_id = $('#input-zone').data('zone-id');
            var $zone = $('#input-zone');
            var selectText = escapeHtml(this.config.text.select_option);

            if (!country_id) {
                if ($zone.is('select')) {
                    $zone.html('<option value="">' + selectText + '</option>');
                }
                return;
            }

            if (!$zone.is('select')) {
                return;
            }

            $.ajax({
                url: this.config.urls.zone + '&country_id=' + country_id,
                dataType: 'json',
                success: function(json) {
                    var html = '<option value="">' + selectText + '</option>';
                    if (json && json.length > 0) {
                        for (var i = 0; i < json.length; i++) {
                            var name = escapeHtml(json[i].name);
                            if (json[i].zone_id == zone_id) {
                                html += '<option value="' + json[i].zone_id + '" selected="selected">' + name + '</option>';
                            } else {
                                html += '<option value="' + json[i].zone_id + '">' + name + '</option>';
                            }
                        }
                    }
                    $zone.html(html);
                }
            });
        },

        updateAll: function() {
            var self = this;

            this.saveCustomerData(function(result) {
                if (self.config.shipping_required && self.config.show_shipping_method) {
                    self.loadShippingMethods(function() {
                        if (self.config.show_payment_method) {
                            self.loadPaymentMethods();
                        } else {
                            self.loadTotals();
                        }
                    });
                } else if (self.config.show_payment_method) {
                    self.loadPaymentMethods();
                } else {
                    self.loadTotals();
                }
            });
        },

        saveCustomerData: function(callback) {
            var self = this;
            var formData = $('#simple-checkout-form').serialize();

            $.ajax({
                url: this.config.urls.save,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(json) {
                    $('.form-group').removeClass('has-error');
                    $('.text-danger').remove();

                    if (json && json.error) {
                        if (typeof json.error === 'object') {
                            for (var field in json.error) {
                                var $input = $('#input-' + field.replace(/_/g, '-'));
                                $input.closest('.form-group').addClass('has-error');
                                $input.after('<div class="text-danger">' + escapeHtml(json.error[field]) + '</div>');
                            }
                        }
                    }

                    if (callback) callback(json || {});
                },
                error: function() {
                    if (callback) callback({error: true});
                }
            });
        },

        loadShippingMethods: function(callback) {
            var self = this;
            var $container = $('#shipping-methods');
            var loadingText = escapeHtml(this.config.text.loading);
            $container.html('<div class="loading-placeholder"><i class="fa fa-spinner fa-spin"></i> ' + loadingText + '</div>');

            $.ajax({
                url: this.config.urls.shipping,
                dataType: 'json',
                success: function(json) {
                    if (json && json.error) {
                        $container.html('<div class="alert alert-warning">' + escapeHtml(json.error) + '</div>');
                    } else if (json && json.shipping_methods) {
                        var html = '';
                        for (var code in json.shipping_methods) {
                            var shipping = json.shipping_methods[code];
                            if (shipping.error) {
                                html += '<div class="alert alert-warning">' + escapeHtml(shipping.error) + '</div>';
                            } else {
                                html += '<p><strong>' + escapeHtml(shipping.title) + '</strong></p>';
                                for (var quote_code in shipping.quote) {
                                    var quote = shipping.quote[quote_code];
                                    var value = code + '.' + quote_code;
                                    var checked = (json.shipping_selected == value) ? ' checked="checked"' : '';
                                    html += '<div class="radio">';
                                    html += '<label>';
                                    html += '<input type="radio" name="shipping_method" value="' + escapeHtml(value) + '"' + checked + ' />';
                                    html += escapeHtml(quote.title) + ' - ' + escapeHtml(quote.text);
                                    html += '</label>';
                                    html += '</div>';
                                }
                            }
                        }
                        if (html) {
                            $container.html(html);
                        } else {
                            $container.html('<div class="alert alert-info">' + escapeHtml(self.config.text.no_shipping) + '</div>');
                        }
                    } else {
                        $container.html('<div class="alert alert-info">' + escapeHtml(self.config.text.no_shipping) + '</div>');
                    }

                    if (callback) callback(json || {});
                },
                error: function() {
                    $container.html('<div class="alert alert-danger">' + escapeHtml(self.config.text.error_loading) + '</div>');
                    if (callback) callback({error: true});
                }
            });
        },

        loadPaymentMethods: function(callback) {
            var self = this;
            var $container = $('#payment-methods');
            if ($container.length === 0) {
                this.loadTotals();
                if (callback) callback({});
                return;
            }

            var loadingText = escapeHtml(this.config.text.loading);
            $container.html('<div class="loading-placeholder"><i class="fa fa-spinner fa-spin"></i> ' + loadingText + '</div>');

            $.ajax({
                url: this.config.urls.payment,
                dataType: 'json',
                success: function(json) {
                    if (json && json.error) {
                        $container.html('<div class="alert alert-warning">' + escapeHtml(json.error) + '</div>');
                    } else if (json && json.payment_methods) {
                        var html = '';
                        for (var code in json.payment_methods) {
                            var payment = json.payment_methods[code];
                            var checked = (json.payment_selected == code) ? ' checked="checked"' : '';
                            html += '<div class="radio">';
                            html += '<label>';
                            html += '<input type="radio" name="payment_method" value="' + escapeHtml(code) + '"' + checked + ' />';
                            if (payment.image) {
                                html += '<img src="' + escapeHtml(payment.image) + '" alt="' + escapeHtml(payment.title) + '" style="max-height: 20px; margin-right: 5px;" /> ';
                            }
                            html += escapeHtml(payment.title);
                            html += '</label>';
                            html += '</div>';
                        }
                        if (html) {
                            $container.html(html);
                        } else {
                            $container.html('<div class="alert alert-info">' + escapeHtml(self.config.text.no_payment) + '</div>');
                        }
                    } else {
                        $container.html('<div class="alert alert-info">' + escapeHtml(self.config.text.no_payment) + '</div>');
                    }

                    if (json && json.totals) {
                        self.renderTotals(json.totals);
                    } else {
                        self.loadTotals();
                    }

                    if (callback) callback(json || {});
                },
                error: function() {
                    $container.html('<div class="alert alert-danger">' + escapeHtml(self.config.text.error_loading) + '</div>');
                    self.loadTotals();
                    if (callback) callback({error: true});
                }
            });
        },

        loadTotals: function(callback) {
            var self = this;

            $.ajax({
                url: this.config.urls.totals,
                dataType: 'json',
                success: function(json) {
                    if (json && json.totals) {
                        self.renderTotals(json.totals);
                    }
                    if (callback) callback(json || {});
                }
            });
        },

        renderTotals: function(totals) {
            var html = '';
            for (var i = 0; i < totals.length; i++) {
                html += '<tr>';
                html += '<td class="text-right"><strong>' + escapeHtml(totals[i].title) + ':</strong></td>';
                html += '<td class="text-right">' + escapeHtml(totals[i].text) + '</td>';
                html += '</tr>';
            }
            $('#totals-table tbody').html(html);
        },

        setShippingMethod: function(method) {
            var self = this;

            $.ajax({
                url: this.config.urls.setShipping,
                type: 'POST',
                data: { shipping_method: method },
                dataType: 'json',
                success: function(json) {
                    if (json && json.success) {
                        self.loadPaymentMethods();
                    }
                }
            });
        },

        setPaymentMethod: function(method) {
            $.ajax({
                url: this.config.urls.setPayment,
                type: 'POST',
                data: { payment_method: method },
                dataType: 'json'
            });
        },

        validateForm: function() {
            var isValid = true;
            var fields = this.config.fields;

            if (fields.firstname === 'required') {
                var val = $('#input-firstname').val();
                if (!val || val.trim().length < 1 || val.trim().length > 32) isValid = false;
            }

            if (fields.lastname === 'required') {
                var val = $('#input-lastname').val();
                if (!val || val.trim().length < 1 || val.trim().length > 32) isValid = false;
            }

            if (fields.email === 'required') {
                var val = $('#input-email').val();
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!val || !emailRegex.test(val)) isValid = false;
            }

            if (fields.telephone === 'required') {
                var val = $('#input-telephone').val();
                if (!val || val.trim().length < 3 || val.trim().length > 32) isValid = false;
            }

            if (fields.address_1 === 'required') {
                var val = $('#input-address-1').val();
                if (!val || val.trim().length < 3 || val.trim().length > 128) isValid = false;
            }

            if (fields.city === 'required') {
                var val = $('#input-city').val();
                if (!val || val.trim().length < 2 || val.trim().length > 128) isValid = false;
            }

            if (fields.postcode === 'required') {
                var val = $('#input-postcode').val();
                if (!val || val.trim().length < 2 || val.trim().length > 10) isValid = false;
            }

            if (fields.country === 'required') {
                var val = $('#input-country').val();
                if (!val || val === '') isValid = false;
            }

            if (fields.zone === 'required') {
                var val = $('#input-zone').val();
                if (!val || val === '') isValid = false;
            }

            if (this.config.require_agree) {
                if (!$('input[name="agree"]').is(':checked')) isValid = false;
            }

            $('#button-confirm').prop('disabled', !isValid);

            return isValid;
        },

        confirmOrder: function() {
            var self = this;
            var $btn = $('#button-confirm');

            if (!this.validateForm()) {
                return;
            }

            var processingText = escapeHtml(this.config.text.processing);
            var btnText = this.config.text.button_confirm;

            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + processingText);

            this.saveCustomerData(function(saveJson) {
                if (saveJson && saveJson.error && typeof saveJson.error === 'object') {
                    $btn.html('<i class="fa fa-check"></i> ' + escapeHtml(btnText));
                    self.validateForm();
                    return;
                }

                var formData = $('#simple-checkout-form').serialize();

                $.ajax({
                    url: self.config.urls.confirm,
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(json) {
                        if (json && json.redirect) {
                            location = json.redirect;
                        } else if (json && json.error) {
                            alert(json.error);
                            $btn.html('<i class="fa fa-check"></i> ' + escapeHtml(btnText));
                            self.validateForm();
                        } else {
                            $btn.html('<i class="fa fa-check"></i> ' + escapeHtml(btnText));
                            self.validateForm();
                        }
                    },
                    error: function() {
                        alert(self.config.text.error_try_again);
                        $btn.html('<i class="fa fa-check"></i> ' + escapeHtml(btnText));
                        self.validateForm();
                    }
                });
            });
        }
    };

    $(document).ready(function() {
        if (typeof SimpleCheckoutLite !== 'undefined') {
            SCL.init(SimpleCheckoutLite);
        }
    });

})(jQuery);
