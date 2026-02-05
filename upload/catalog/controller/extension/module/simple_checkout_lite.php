<?php
/**
 * Simple Checkout Lite - Catalog Controller
 * One Page Checkout для ocStore 3.0.3.7 / unishop2_free
 */
class ControllerExtensionModuleSimpleCheckoutLite extends Controller {

    public function index() {
        // Check if module is enabled
        if (!$this->config->get('module_simple_checkout_lite_status')) {
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        // Check cart has products
        if (!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) {
            $this->response->redirect($this->url->link('checkout/cart', '', true));
            return;
        }

        // Check products have minimum quantity
        $products = $this->cart->getProducts();
        foreach ($products as $product) {
            $product_total = 0;
            foreach ($products as $product_2) {
                if ($product_2['product_id'] == $product['product_id']) {
                    $product_total += $product_2['quantity'];
                }
            }
            if ($product['minimum'] > $product_total) {
                $this->response->redirect($this->url->link('checkout/cart', '', true));
                return;
            }
        }

        $this->load->language('checkout/checkout');
        $this->load->language('extension/module/simple_checkout_lite');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_cart'),
            'href' => $this->url->link('checkout/cart')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/simple_checkout_lite')
        );

        $data['heading_title'] = $this->language->get('heading_title');

        // Check if customer is logged in
        $data['logged'] = $this->customer->isLogged();
        $data['guest_checkout'] = $this->config->get('module_simple_checkout_lite_guest');

        // Get field settings
        $fields = array('firstname', 'lastname', 'email', 'telephone', 'address_1', 'address_2', 'city', 'postcode', 'country', 'zone', 'company');
        $data['fields'] = array();
        foreach ($fields as $field) {
            $data['fields'][$field] = $this->config->get('module_simple_checkout_lite_field_' . $field);
            if (!$data['fields'][$field]) {
                $data['fields'][$field] = 'hidden';
            }
        }

        // Get step settings
        $data['show_shipping_address'] = $this->config->get('module_simple_checkout_lite_step_shipping_address');
        $data['show_shipping_method'] = $this->config->get('module_simple_checkout_lite_step_shipping_method');
        $data['show_payment_method'] = $this->config->get('module_simple_checkout_lite_step_payment_method');
        $data['show_comment'] = $this->config->get('module_simple_checkout_lite_step_comment');

        // Check if shipping is required
        $data['shipping_required'] = $this->cart->hasShipping();

        // If no shipping required, hide shipping steps
        if (!$data['shipping_required']) {
            $data['show_shipping_address'] = 0;
            $data['show_shipping_method'] = 0;
        }

        // Pre-fill customer data if logged in
        if ($this->customer->isLogged()) {
            $this->load->model('account/customer');
            $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

            $data['customer_firstname'] = $customer_info['firstname'];
            $data['customer_lastname'] = $customer_info['lastname'];
            $data['customer_email'] = $customer_info['email'];
            $data['customer_telephone'] = $customer_info['telephone'];

            // Default address values - use module default country/zone or fall back to config
            $default_country = $this->config->get('module_simple_checkout_lite_country_default');
            $default_zone = $this->config->get('module_simple_checkout_lite_zone_default');

            $data['customer_company'] = '';
            $data['customer_address_1'] = '';
            $data['customer_address_2'] = '';
            $data['customer_city'] = '';
            $data['customer_postcode'] = '';
            $data['customer_country_id'] = $default_country ? $default_country : $this->config->get('config_country_id');
            $data['customer_zone_id'] = $default_zone ? $default_zone : $this->config->get('config_zone_id');

            // Get default address if exists
            $this->load->model('account/address');
            if ($this->customer->getAddressId()) {
                $address_info = $this->model_account_address->getAddress($this->customer->getAddressId());
                if ($address_info) {
                    $data['customer_company'] = $address_info['company'];
                    $data['customer_address_1'] = $address_info['address_1'];
                    $data['customer_address_2'] = $address_info['address_2'];
                    $data['customer_city'] = $address_info['city'];
                    $data['customer_postcode'] = $address_info['postcode'];
                    $data['customer_country_id'] = $address_info['country_id'];
                    $data['customer_zone_id'] = $address_info['zone_id'];
                }
            }
        } else {
            $data['customer_firstname'] = '';
            $data['customer_lastname'] = '';
            $data['customer_email'] = '';
            $data['customer_telephone'] = '';
            $data['customer_company'] = '';
            $data['customer_address_1'] = '';
            $data['customer_address_2'] = '';
            $data['customer_city'] = '';
            $data['customer_postcode'] = '';

            // Use module default country/zone or fall back to config
            $default_country = $this->config->get('module_simple_checkout_lite_country_default');
            $default_zone = $this->config->get('module_simple_checkout_lite_zone_default');

            $data['customer_country_id'] = $default_country ? $default_country : $this->config->get('config_country_id');
            $data['customer_zone_id'] = $default_zone ? $default_zone : $this->config->get('config_zone_id');
        }

        // Countries
        $this->load->model('localisation/country');
        $data['countries'] = $this->model_localisation_country->getCountries();

        // Default payment and shipping methods
        $data['payment_default'] = $this->config->get('module_simple_checkout_lite_payment_default');
        $data['shipping_default'] = $this->config->get('module_simple_checkout_lite_shipping_default');

        // URLs for AJAX
        $data['action_save'] = $this->url->link('extension/module/simple_checkout_lite/save', '', true);
        $data['action_shipping'] = $this->url->link('extension/module/simple_checkout_lite/shipping', '', true);
        $data['action_payment'] = $this->url->link('extension/module/simple_checkout_lite/payment', '', true);
        $data['action_totals'] = $this->url->link('extension/module/simple_checkout_lite/totals', '', true);
        $data['action_confirm'] = $this->url->link('extension/module/simple_checkout_lite/confirm', '', true);
        $data['action_set_shipping'] = $this->url->link('extension/module/simple_checkout_lite/setShipping', '', true);
        $data['action_set_payment'] = $this->url->link('extension/module/simple_checkout_lite/setPayment', '', true);
        $data['action_zone'] = $this->url->link('extension/module/simple_checkout_lite/zone', '', true);

        // Language strings
        $data['text_checkout'] = $this->language->get('text_checkout');
        $data['text_customer_info'] = $this->language->get('text_customer_info');
        $data['text_shipping_address'] = $this->language->get('text_shipping_address');
        $data['text_shipping_method'] = $this->language->get('text_shipping_method');
        $data['text_payment_method'] = $this->language->get('text_payment_method');
        $data['text_comments'] = $this->language->get('text_comments');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/agree', 'information_id=' . $this->config->get('config_checkout_id'), true));

        $data['entry_firstname'] = $this->language->get('entry_firstname');
        $data['entry_lastname'] = $this->language->get('entry_lastname');
        $data['entry_email'] = $this->language->get('entry_email');
        $data['entry_telephone'] = $this->language->get('entry_telephone');
        $data['entry_company'] = $this->language->get('entry_company');
        $data['entry_address_1'] = $this->language->get('entry_address_1');
        $data['entry_address_2'] = $this->language->get('entry_address_2');
        $data['entry_city'] = $this->language->get('entry_city');
        $data['entry_postcode'] = $this->language->get('entry_postcode');
        $data['entry_country'] = $this->language->get('entry_country');
        $data['entry_zone'] = $this->language->get('entry_zone');
        $data['entry_comment'] = $this->language->get('entry_comment');
        $data['entry_same_address'] = $this->language->get('entry_same_address');

        $data['button_confirm'] = $this->language->get('button_confirm');

        $data['agree'] = $this->config->get('config_checkout_id');

        // Cart products for display
        $this->load->model('tool/image');
        $data['products'] = array();

        foreach ($this->cart->getProducts() as $product) {
            if ($product['image']) {
                $image = $this->model_tool_image->resize($product['image'], 60, 60);
            } else {
                $image = $this->model_tool_image->resize('placeholder.png', 60, 60);
            }

            $option_data = array();
            foreach ($product['option'] as $option) {
                $option_data[] = array(
                    'name'  => $option['name'],
                    'value' => $option['value']
                );
            }

            $data['products'][] = array(
                'cart_id'   => $product['cart_id'],
                'product_id' => $product['product_id'],
                'name'      => $product['name'],
                'model'     => $product['model'],
                'option'    => $option_data,
                'quantity'  => $product['quantity'],
                'price'     => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']),
                'total'     => $this->currency->format($this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax')) * $product['quantity'], $this->session->data['currency']),
                'image'     => $image,
                'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
            );
        }

        // No sidebars and no content modules for checkout - clean layout
        $data['column_left'] = '';
        $data['column_right'] = '';
        $data['content_top'] = '';
        $data['content_bottom'] = '';
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/module/simple_checkout_lite', $data));
    }

    /**
     * Get zones by country (AJAX)
     */
    public function zone() {
        $json = array();

        $this->load->model('localisation/zone');

        $country_id = isset($this->request->get['country_id']) ? (int)$this->request->get['country_id'] : 0;

        $results = $this->model_localisation_zone->getZonesByCountryId($country_id);

        foreach ($results as $result) {
            $json[] = array(
                'zone_id' => $result['zone_id'],
                'name'    => $result['name']
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Save customer data to session (AJAX)
     */
    public function save() {
        $json = array();

        $this->load->language('checkout/checkout');
        $this->load->language('extension/module/simple_checkout_lite');

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            // Get POST values with defaults
            $firstname = isset($this->request->post['firstname']) ? trim($this->request->post['firstname']) : '';
            $lastname = isset($this->request->post['lastname']) ? trim($this->request->post['lastname']) : '';
            $email = isset($this->request->post['email']) ? trim($this->request->post['email']) : '';
            $telephone = isset($this->request->post['telephone']) ? trim($this->request->post['telephone']) : '';
            $address_1 = isset($this->request->post['address_1']) ? trim($this->request->post['address_1']) : '';
            $city = isset($this->request->post['city']) ? trim($this->request->post['city']) : '';
            $postcode = isset($this->request->post['postcode']) ? trim($this->request->post['postcode']) : '';
            $country_id = isset($this->request->post['country_id']) ? (int)$this->request->post['country_id'] : 0;
            $zone_id = isset($this->request->post['zone_id']) ? (int)$this->request->post['zone_id'] : 0;

            // If country field is hidden and no value posted, use module default
            $field_country_config = $this->config->get('module_simple_checkout_lite_field_country');
            if ($field_country_config == 'hidden' && !$country_id) {
                $country_id = (int)$this->config->get('module_simple_checkout_lite_country_default');
                if (!$country_id) {
                    $country_id = (int)$this->config->get('config_country_id');
                }
            }

            // If zone field is hidden and no value posted, use module default
            $field_zone_config = $this->config->get('module_simple_checkout_lite_field_zone');
            if ($field_zone_config == 'hidden' && !$zone_id) {
                $zone_id = (int)$this->config->get('module_simple_checkout_lite_zone_default');
                if (!$zone_id) {
                    $zone_id = (int)$this->config->get('config_zone_id');
                }
            }

            // Validate fields
            $field_config = $this->config->get('module_simple_checkout_lite_field_firstname');
            if ($field_config == 'required' && (utf8_strlen($firstname) < 1 || utf8_strlen($firstname) > 32)) {
                $json['error']['firstname'] = $this->language->get('error_firstname');
            }

            $field_config = $this->config->get('module_simple_checkout_lite_field_lastname');
            if ($field_config == 'required' && (utf8_strlen($lastname) < 1 || utf8_strlen($lastname) > 32)) {
                $json['error']['lastname'] = $this->language->get('error_lastname');
            }

            $field_config = $this->config->get('module_simple_checkout_lite_field_email');
            if ($field_config == 'required' && (utf8_strlen($email) > 96 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $json['error']['email'] = $this->language->get('error_email');
            }

            $field_config = $this->config->get('module_simple_checkout_lite_field_telephone');
            if ($field_config == 'required' && (utf8_strlen($telephone) < 3 || utf8_strlen($telephone) > 32)) {
                $json['error']['telephone'] = $this->language->get('error_telephone');
            }

            $field_config = $this->config->get('module_simple_checkout_lite_field_address_1');
            if ($field_config == 'required' && (utf8_strlen($address_1) < 3 || utf8_strlen($address_1) > 128)) {
                $json['error']['address_1'] = $this->language->get('error_address_1');
            }

            $field_config = $this->config->get('module_simple_checkout_lite_field_city');
            if ($field_config == 'required' && (utf8_strlen($city) < 2 || utf8_strlen($city) > 128)) {
                $json['error']['city'] = $this->language->get('error_city');
            }

            $field_config = $this->config->get('module_simple_checkout_lite_field_postcode');
            if ($field_config == 'required' && (utf8_strlen($postcode) < 2 || utf8_strlen($postcode) > 10)) {
                $json['error']['postcode'] = $this->language->get('error_postcode');
            }

            $field_config = $this->config->get('module_simple_checkout_lite_field_country');
            if ($field_config == 'required') {
                $this->load->model('localisation/country');
                $country_info = $this->model_localisation_country->getCountry($country_id);
                if (!$country_info) {
                    $json['error']['country'] = $this->language->get('error_country');
                }
            }

            $field_config = $this->config->get('module_simple_checkout_lite_field_zone');
            if ($field_config == 'required' && $country_id > 0) {
                $this->load->model('localisation/zone');
                $zone_info = $this->model_localisation_zone->getZone($zone_id);
                if (!$zone_info) {
                    $json['error']['zone'] = $this->language->get('error_zone');
                }
            }

            if (!isset($json['error'])) {
                // Save to session as guest
                $this->session->data['guest'] = array(
                    'customer_group_id' => $this->config->get('config_customer_group_id'),
                    'firstname'         => $firstname,
                    'lastname'          => $lastname,
                    'email'             => $email,
                    'telephone'         => $telephone,
                    'custom_field'      => array()
                );

                $company = isset($this->request->post['company']) ? trim($this->request->post['company']) : '';
                $address_2 = isset($this->request->post['address_2']) ? trim($this->request->post['address_2']) : '';

                $this->session->data['payment_address'] = array(
                    'firstname'         => $firstname,
                    'lastname'          => $lastname,
                    'company'           => $company,
                    'address_1'         => $address_1,
                    'address_2'         => $address_2,
                    'postcode'          => $postcode,
                    'city'              => $city,
                    'country_id'        => $country_id,
                    'zone_id'           => $zone_id,
                    'custom_field'      => array()
                );

                // Get country and zone names
                if ($country_id > 0) {
                    $this->load->model('localisation/country');
                    $country_info = $this->model_localisation_country->getCountry($country_id);
                    if ($country_info) {
                        $this->session->data['payment_address']['country'] = $country_info['name'];
                        $this->session->data['payment_address']['iso_code_2'] = $country_info['iso_code_2'];
                        $this->session->data['payment_address']['iso_code_3'] = $country_info['iso_code_3'];
                        $this->session->data['payment_address']['address_format'] = $country_info['address_format'];
                    }
                }

                if ($zone_id > 0) {
                    $this->load->model('localisation/zone');
                    $zone_info = $this->model_localisation_zone->getZone($zone_id);
                    if ($zone_info) {
                        $this->session->data['payment_address']['zone'] = $zone_info['name'];
                        $this->session->data['payment_address']['zone_code'] = $zone_info['code'];
                    }
                }

                // Copy to shipping address if same
                if (!isset($this->request->post['shipping_address']) || $this->request->post['shipping_address'] == '1') {
                    $this->session->data['shipping_address'] = $this->session->data['payment_address'];
                }

                $json['success'] = true;
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get shipping methods (AJAX)
     */
    public function shipping() {
        $json = array();

        $this->load->language('checkout/checkout');

        if (!$this->cart->hasShipping()) {
            $json['error'] = $this->language->get('error_no_shipping');
        }

        if (!isset($this->session->data['shipping_address'])) {
            $json['error'] = $this->language->get('error_address');
        }

        if (!isset($json['error'])) {
            $this->load->model('setting/extension');

            $quote_data = array();

            $results = $this->model_setting_extension->getExtensions('shipping');

            foreach ($results as $result) {
                if ($this->config->get('shipping_' . $result['code'] . '_status')) {
                    $this->load->model('extension/shipping/' . $result['code']);

                    $quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);

                    if ($quote) {
                        $quote_data[$result['code']] = array(
                            'title'      => $quote['title'],
                            'quote'      => $quote['quote'],
                            'sort_order' => $quote['sort_order'],
                            'error'      => $quote['error']
                        );
                    }
                }
            }

            $sort_order = array();

            foreach ($quote_data as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $quote_data);

            $this->session->data['shipping_methods'] = $quote_data;

            $json['shipping_methods'] = $quote_data;

            // Auto-select default or first shipping method
            $default_shipping = $this->config->get('module_simple_checkout_lite_shipping_default');
            if ($default_shipping && isset($quote_data[$default_shipping])) {
                foreach ($quote_data[$default_shipping]['quote'] as $key => $quote) {
                    $this->session->data['shipping_method'] = $quote;
                    $json['shipping_selected'] = $default_shipping . '.' . $key;
                    break;
                }
            } elseif ($quote_data) {
                foreach ($quote_data as $code => $shipping) {
                    foreach ($shipping['quote'] as $key => $quote) {
                        $this->session->data['shipping_method'] = $quote;
                        $json['shipping_selected'] = $code . '.' . $key;
                        break 2;
                    }
                }
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get payment methods (AJAX)
     */
    public function payment() {
        $json = array();

        try {
            $this->load->language('checkout/checkout');

            if (!isset($this->session->data['payment_address'])) {
                $json['error'] = $this->language->get('error_address');
            }

            // Check if shipping is required and set
            if ($this->cart->hasShipping() && !isset($this->session->data['shipping_method'])) {
                $json['error'] = $this->language->get('error_shipping');
            }

            if (!isset($json['error'])) {
                // Totals
                $total_data = array(
                    'totals' => array(),
                    'taxes'  => $this->cart->getTaxes(),
                    'total'  => 0
                );

                $this->load->model('setting/extension');

                $sort_order = array();

                $results = $this->model_setting_extension->getExtensions('total');

                foreach ($results as $key => $value) {
                    $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
                }

                array_multisort($sort_order, SORT_ASC, $results);

                foreach ($results as $result) {
                    if ($this->config->get('total_' . $result['code'] . '_status')) {
                        try {
                            $this->load->model('extension/total/' . $result['code']);
                            $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
                        } catch (Exception $e) {
                            // Skip this total if error
                        }
                    }
                }

                $total = $total_data['total'];

                // Payment Methods
                $method_data = array();

                $results = $this->model_setting_extension->getExtensions('payment');

                $recurring = $this->cart->hasRecurringProducts();

                foreach ($results as $result) {
                    if ($this->config->get('payment_' . $result['code'] . '_status')) {
                        try {
                            $this->load->model('extension/payment/' . $result['code']);

                            $method = $this->{'model_extension_payment_' . $result['code']}->getMethod($this->session->data['payment_address'], $total);

                            if ($method) {
                                if ($recurring) {
                                    if (property_exists($this->{'model_extension_payment_' . $result['code']}, 'recurringPayments') && $this->{'model_extension_payment_' . $result['code']}->recurringPayments()) {
                                        $method_data[$result['code']] = $method;
                                    }
                                } else {
                                    $method_data[$result['code']] = $method;
                                }
                            }
                        } catch (Exception $e) {
                            // Skip this payment method if error
                        }
                    }
                }

                if ($method_data) {
                    $sort_order = array();

                    foreach ($method_data as $key => $value) {
                        $sort_order[$key] = $value['sort_order'];
                    }

                    array_multisort($sort_order, SORT_ASC, $method_data);
                }

                $this->session->data['payment_methods'] = $method_data;

                $json['payment_methods'] = $method_data;

                // Auto-select default or first payment method
                $default_payment = $this->config->get('module_simple_checkout_lite_payment_default');
                if ($default_payment && isset($method_data[$default_payment])) {
                    $this->session->data['payment_method'] = $method_data[$default_payment];
                    $json['payment_selected'] = $default_payment;
                } elseif ($method_data) {
                    foreach ($method_data as $code => $method) {
                        $this->session->data['payment_method'] = $method;
                        $json['payment_selected'] = $code;
                        break;
                    }
                }

                // Get totals HTML
                $json['totals'] = $this->getTotalsHtml();
            }
        } catch (Exception $e) {
            $json['error'] = 'Error: ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Get totals only (AJAX) - used when payment methods are hidden
     */
    public function totals() {
        $json = array();

        try {
            $json['totals'] = $this->getTotalsHtml();
        } catch (Exception $e) {
            $json['error'] = 'Error: ' . $e->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Set shipping method (AJAX)
     */
    public function setShipping() {
        $json = array();

        $this->load->language('checkout/checkout');

        if (isset($this->request->post['shipping_method'])) {
            $shipping = explode('.', $this->request->post['shipping_method']);

            if (isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
                $this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];
                $json['success'] = true;
            } else {
                $json['error'] = $this->language->get('error_shipping');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Set payment method (AJAX)
     */
    public function setPayment() {
        $json = array();

        $this->load->language('checkout/checkout');

        if (isset($this->request->post['payment_method'])) {
            if (isset($this->session->data['payment_methods'][$this->request->post['payment_method']])) {
                $this->session->data['payment_method'] = $this->session->data['payment_methods'][$this->request->post['payment_method']];
                $json['success'] = true;
            } else {
                $json['error'] = $this->language->get('error_payment');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Confirm order (AJAX)
     */
    public function confirm() {
        $json = array();

        $this->load->language('checkout/checkout');
        $this->load->language('extension/module/simple_checkout_lite');

        // Validate cart
        if (!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) {
            $json['redirect'] = $this->url->link('checkout/cart');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Validate minimum quantity
        $products = $this->cart->getProducts();
        foreach ($products as $product) {
            $product_total = 0;
            foreach ($products as $product_2) {
                if ($product_2['product_id'] == $product['product_id']) {
                    $product_total += $product_2['quantity'];
                }
            }
            if ($product['minimum'] > $product_total) {
                $json['redirect'] = $this->url->link('checkout/cart');
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
                return;
            }
        }

        // Validate shipping
        if ($this->cart->hasShipping()) {
            if (!isset($this->session->data['shipping_address'])) {
                $json['error'] = $this->language->get('error_address');
            }

            // Only validate shipping method if step is enabled
            if ($this->config->get('module_simple_checkout_lite_step_shipping_method')) {
                if (!isset($this->session->data['shipping_method'])) {
                    $json['error'] = $this->language->get('error_shipping');
                }
            } else {
                // Auto-select first shipping method if step is disabled
                if (!isset($this->session->data['shipping_method'])) {
                    $this->autoSelectShippingMethod();
                }
            }
        }

        // Validate payment (only if payment step is enabled)
        if ($this->config->get('module_simple_checkout_lite_step_payment_method')) {
            if (!isset($this->session->data['payment_method'])) {
                $json['error'] = $this->language->get('error_payment');
            }
        }

        // Validate guest/payment_address
        if (!$this->customer->isLogged() && !isset($this->session->data['guest'])) {
            $json['error'] = $this->language->get('error_guest');
        }

        if (!isset($this->session->data['payment_address'])) {
            $json['error'] = $this->language->get('error_address');
        }

        // Validate agree
        if ($this->config->get('config_checkout_id')) {
            if (!isset($this->request->post['agree']) || !$this->request->post['agree']) {
                $json['error'] = $this->language->get('error_agree');
            }
        }

        // Save comment
        if (isset($this->request->post['comment'])) {
            $this->session->data['comment'] = strip_tags($this->request->post['comment']);
        }

        if (!isset($json['error'])) {
            $order_data = array();

            $total_data = array(
                'totals' => array(),
                'taxes'  => $this->cart->getTaxes(),
                'total'  => 0
            );

            $this->load->model('setting/extension');

            $sort_order = array();

            $results = $this->model_setting_extension->getExtensions('total');

            foreach ($results as $key => $value) {
                $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
            }

            array_multisort($sort_order, SORT_ASC, $results);

            foreach ($results as $result) {
                if ($this->config->get('total_' . $result['code'] . '_status')) {
                    try {
                        $this->load->model('extension/total/' . $result['code']);
                        $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
                    } catch (Exception $e) {
                        // Skip this total if error
                    }
                }
            }

            $totals = $total_data['totals'];
            $total = $total_data['total'];

            $sort_order = array();

            foreach ($totals as $key => $value) {
                $sort_order[$key] = $value['sort_order'];
            }

            array_multisort($sort_order, SORT_ASC, $totals);

            $order_data['totals'] = $totals;

            $order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
            $order_data['store_id'] = $this->config->get('config_store_id');
            $order_data['store_name'] = $this->config->get('config_name');
            $order_data['store_url'] = $this->config->get('config_url');

            if ($this->customer->isLogged()) {
                $this->load->model('account/customer');
                $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

                $order_data['customer_id'] = $this->customer->getId();
                $order_data['customer_group_id'] = $customer_info['customer_group_id'];
                $order_data['firstname'] = $customer_info['firstname'];
                $order_data['lastname'] = $customer_info['lastname'];
                $order_data['email'] = $customer_info['email'];
                $order_data['telephone'] = $customer_info['telephone'];
                $order_data['custom_field'] = json_decode($customer_info['custom_field'], true);
            } else {
                $order_data['customer_id'] = 0;
                $order_data['customer_group_id'] = $this->session->data['guest']['customer_group_id'];
                $order_data['firstname'] = $this->session->data['guest']['firstname'];
                $order_data['lastname'] = $this->session->data['guest']['lastname'];
                $order_data['email'] = $this->session->data['guest']['email'];
                $order_data['telephone'] = $this->session->data['guest']['telephone'];
                $order_data['custom_field'] = $this->session->data['guest']['custom_field'];
            }

            $order_data['payment_firstname'] = $this->session->data['payment_address']['firstname'];
            $order_data['payment_lastname'] = $this->session->data['payment_address']['lastname'];
            $order_data['payment_company'] = $this->session->data['payment_address']['company'];
            $order_data['payment_address_1'] = $this->session->data['payment_address']['address_1'];
            $order_data['payment_address_2'] = $this->session->data['payment_address']['address_2'];
            $order_data['payment_city'] = $this->session->data['payment_address']['city'];
            $order_data['payment_postcode'] = $this->session->data['payment_address']['postcode'];
            $order_data['payment_zone'] = isset($this->session->data['payment_address']['zone']) ? $this->session->data['payment_address']['zone'] : '';
            $order_data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
            $order_data['payment_country'] = isset($this->session->data['payment_address']['country']) ? $this->session->data['payment_address']['country'] : '';
            $order_data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
            $order_data['payment_address_format'] = isset($this->session->data['payment_address']['address_format']) ? $this->session->data['payment_address']['address_format'] : '';
            $order_data['payment_custom_field'] = isset($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array();

            if (isset($this->session->data['payment_method']['title'])) {
                $order_data['payment_method'] = $this->session->data['payment_method']['title'];
            } else {
                $order_data['payment_method'] = '';
            }

            if (isset($this->session->data['payment_method']['code'])) {
                $order_data['payment_code'] = $this->session->data['payment_method']['code'];
            } else {
                $order_data['payment_code'] = '';
            }

            if ($this->cart->hasShipping()) {
                $order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
                $order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
                $order_data['shipping_company'] = $this->session->data['shipping_address']['company'];
                $order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
                $order_data['shipping_address_2'] = $this->session->data['shipping_address']['address_2'];
                $order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
                $order_data['shipping_postcode'] = $this->session->data['shipping_address']['postcode'];
                $order_data['shipping_zone'] = isset($this->session->data['shipping_address']['zone']) ? $this->session->data['shipping_address']['zone'] : '';
                $order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
                $order_data['shipping_country'] = isset($this->session->data['shipping_address']['country']) ? $this->session->data['shipping_address']['country'] : '';
                $order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
                $order_data['shipping_address_format'] = isset($this->session->data['shipping_address']['address_format']) ? $this->session->data['shipping_address']['address_format'] : '';
                $order_data['shipping_custom_field'] = isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array();

                if (isset($this->session->data['shipping_method']['title'])) {
                    $order_data['shipping_method'] = $this->session->data['shipping_method']['title'];
                } else {
                    $order_data['shipping_method'] = '';
                }

                if (isset($this->session->data['shipping_method']['code'])) {
                    $order_data['shipping_code'] = $this->session->data['shipping_method']['code'];
                } else {
                    $order_data['shipping_code'] = '';
                }
            } else {
                $order_data['shipping_firstname'] = '';
                $order_data['shipping_lastname'] = '';
                $order_data['shipping_company'] = '';
                $order_data['shipping_address_1'] = '';
                $order_data['shipping_address_2'] = '';
                $order_data['shipping_city'] = '';
                $order_data['shipping_postcode'] = '';
                $order_data['shipping_zone'] = '';
                $order_data['shipping_zone_id'] = 0;
                $order_data['shipping_country'] = '';
                $order_data['shipping_country_id'] = 0;
                $order_data['shipping_address_format'] = '';
                $order_data['shipping_custom_field'] = array();
                $order_data['shipping_method'] = '';
                $order_data['shipping_code'] = '';
            }

            $order_data['products'] = array();

            foreach ($this->cart->getProducts() as $product) {
                $option_data = array();

                foreach ($product['option'] as $option) {
                    $option_data[] = array(
                        'product_option_id'       => $option['product_option_id'],
                        'product_option_value_id' => $option['product_option_value_id'],
                        'option_id'               => $option['option_id'],
                        'option_value_id'         => $option['option_value_id'],
                        'name'                    => $option['name'],
                        'value'                   => $option['value'],
                        'type'                    => $option['type']
                    );
                }

                $order_data['products'][] = array(
                    'product_id' => $product['product_id'],
                    'name'       => $product['name'],
                    'model'      => $product['model'],
                    'option'     => $option_data,
                    'download'   => $product['download'],
                    'quantity'   => $product['quantity'],
                    'subtract'   => $product['subtract'],
                    'price'      => $product['price'],
                    'total'      => $product['total'],
                    'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
                    'reward'     => $product['reward']
                );
            }

            // Gift Voucher
            $order_data['vouchers'] = array();

            if (!empty($this->session->data['vouchers'])) {
                foreach ($this->session->data['vouchers'] as $voucher) {
                    $order_data['vouchers'][] = array(
                        'description'      => $voucher['description'],
                        'code'             => token(10),
                        'to_name'          => $voucher['to_name'],
                        'to_email'         => $voucher['to_email'],
                        'from_name'        => $voucher['from_name'],
                        'from_email'       => $voucher['from_email'],
                        'voucher_theme_id' => $voucher['voucher_theme_id'],
                        'message'          => $voucher['message'],
                        'amount'           => $voucher['amount']
                    );
                }
            }

            $order_data['comment'] = isset($this->session->data['comment']) ? $this->session->data['comment'] : '';
            $order_data['total'] = $total;

            if (isset($this->request->cookie['tracking'])) {
                $order_data['tracking'] = $this->request->cookie['tracking'];

                $subtotal = $this->cart->getSubTotal();

                $this->load->model('checkout/marketing');

                $marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);

                if ($marketing_info) {
                    $order_data['marketing_id'] = $marketing_info['marketing_id'];
                } else {
                    $order_data['marketing_id'] = 0;
                }

                $this->load->model('checkout/affiliate');

                $affiliate_info = $this->model_checkout_affiliate->getAffiliateByTracking($this->request->cookie['tracking']);

                if ($affiliate_info) {
                    $order_data['affiliate_id'] = $affiliate_info['customer_id'];
                    $order_data['commission'] = ($subtotal / 100) * $affiliate_info['commission'];
                } else {
                    $order_data['affiliate_id'] = 0;
                    $order_data['commission'] = 0;
                }
            } else {
                $order_data['tracking'] = '';
                $order_data['marketing_id'] = 0;
                $order_data['affiliate_id'] = 0;
                $order_data['commission'] = 0;
            }

            $order_data['language_id'] = $this->config->get('config_language_id');
            $order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
            $order_data['currency_code'] = $this->session->data['currency'];
            $order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
            $order_data['ip'] = $this->request->server['REMOTE_ADDR'];

            if (!empty($this->request->server['HTTP_X_FORWARDED_FOR'])) {
                $order_data['forwarded_ip'] = $this->request->server['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($this->request->server['HTTP_CLIENT_IP'])) {
                $order_data['forwarded_ip'] = $this->request->server['HTTP_CLIENT_IP'];
            } else {
                $order_data['forwarded_ip'] = '';
            }

            if (isset($this->request->server['HTTP_USER_AGENT'])) {
                $order_data['user_agent'] = $this->request->server['HTTP_USER_AGENT'];
            } else {
                $order_data['user_agent'] = '';
            }

            if (isset($this->request->server['HTTP_ACCEPT_LANGUAGE'])) {
                $order_data['accept_language'] = $this->request->server['HTTP_ACCEPT_LANGUAGE'];
            } else {
                $order_data['accept_language'] = '';
            }

            $this->load->model('checkout/order');

            $order_id = $this->model_checkout_order->addOrder($order_data);

            $this->session->data['order_id'] = $order_id;

            // If payment step is disabled, confirm order directly
            if (!$this->config->get('module_simple_checkout_lite_step_payment_method') || !isset($this->session->data['payment_method'])) {
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('config_order_status_id'));
                $json['redirect'] = $this->url->link('checkout/success', '', true);
            } else {
                // Go to payment page
                $json['redirect'] = $this->url->link('extension/module/simple_checkout_lite/pay', '', true);
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Payment processing page
     */
    public function pay() {
        if (!isset($this->session->data['order_id'])) {
            $this->response->redirect($this->url->link('checkout/cart', '', true));
            return;
        }

        if (isset($this->session->data['payment_method']['code'])) {
            $code = $this->session->data['payment_method']['code'];

            // For free_checkout and similar simple methods - confirm directly
            $simple_payments = array('free_checkout', 'cod', 'cheque', 'bank_transfer');

            if (in_array($code, $simple_payments)) {
                $this->load->model('checkout/order');
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('config_order_status_id'));

                $this->response->redirect($this->url->link('checkout/success', '', true));
                return;
            }

            // Try to get payment form for other methods
            $this->load->language('extension/payment/' . $code);

            $data['payment'] = $this->load->controller('extension/payment/' . $code);

            if ($data['payment'] && trim(strip_tags($data['payment']))) {
                $this->load->language('checkout/checkout');

                $data['heading_title'] = $this->language->get('text_checkout');

                $data['breadcrumbs'] = array();
                $data['breadcrumbs'][] = array(
                    'text' => $this->language->get('text_home'),
                    'href' => $this->url->link('common/home')
                );
                $data['breadcrumbs'][] = array(
                    'text' => $this->language->get('heading_title'),
                    'href' => $this->url->link('checkout/checkout', '', true)
                );

                $data['column_left'] = '';
                $data['column_right'] = '';
                $data['content_top'] = $this->load->controller('common/content_top');
                $data['content_bottom'] = $this->load->controller('common/content_bottom');
                $data['footer'] = $this->load->controller('common/footer');
                $data['header'] = $this->load->controller('common/header');

                $this->response->setOutput($this->load->view('extension/module/simple_checkout_lite_pay', $data));
            } else {
                // No payment form, confirm order directly
                $this->load->model('checkout/order');
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('config_order_status_id'));

                $this->response->redirect($this->url->link('checkout/success', '', true));
            }
        } else {
            $this->load->model('checkout/order');
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('config_order_status_id'));

            $this->response->redirect($this->url->link('checkout/success', '', true));
        }
    }

    /**
     * Auto-select first available shipping method
     */
    private function autoSelectShippingMethod() {
        if (isset($this->session->data['shipping_methods']) && $this->session->data['shipping_methods']) {
            foreach ($this->session->data['shipping_methods'] as $code => $shipping) {
                if (isset($shipping['quote']) && $shipping['quote']) {
                    foreach ($shipping['quote'] as $quote) {
                        $this->session->data['shipping_method'] = $quote;
                        return;
                    }
                }
            }
        }

        // If no shipping methods in session, try to load them
        if (isset($this->session->data['shipping_address'])) {
            $this->load->model('setting/extension');
            $results = $this->model_setting_extension->getExtensions('shipping');

            foreach ($results as $result) {
                if ($this->config->get('shipping_' . $result['code'] . '_status')) {
                    $this->load->model('extension/shipping/' . $result['code']);
                    $quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);

                    if ($quote && isset($quote['quote'])) {
                        foreach ($quote['quote'] as $method) {
                            $this->session->data['shipping_method'] = $method;
                            return;
                        }
                    }
                }
            }
        }
    }

    /**
     * Get totals HTML (AJAX helper)
     */
    private function getTotalsHtml() {
        $total_data = array(
            'totals' => array(),
            'taxes'  => $this->cart->getTaxes(),
            'total'  => 0
        );

        // Make sure currency is set
        if (!isset($this->session->data['currency'])) {
            $this->session->data['currency'] = $this->config->get('config_currency');
        }

        $this->load->model('setting/extension');

        $sort_order = array();

        $results = $this->model_setting_extension->getExtensions('total');

        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                try {
                    $this->load->model('extension/total/' . $result['code']);
                    $this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
                } catch (Exception $e) {
                    // Skip
                }
            }
        }

        $totals = $total_data['totals'];

        // If no totals from extensions, calculate basic totals
        if (empty($totals)) {
            $sub_total = $this->cart->getSubTotal();
            $shipping_cost = 0;

            $totals[] = array(
                'code'       => 'sub_total',
                'title'      => $this->language->get('text_sub_total'),
                'value'      => $sub_total,
                'sort_order' => 1
            );

            if (isset($this->session->data['shipping_method'])) {
                $shipping_cost = (float)$this->session->data['shipping_method']['cost'];

                // Add shipping tax if applicable
                if (isset($this->session->data['shipping_method']['tax_class_id']) && $this->session->data['shipping_method']['tax_class_id']) {
                    $shipping_cost = $this->tax->calculate($shipping_cost, $this->session->data['shipping_method']['tax_class_id'], $this->config->get('config_tax'));
                }

                $totals[] = array(
                    'code'       => 'shipping',
                    'title'      => $this->session->data['shipping_method']['title'],
                    'value'      => $shipping_cost,
                    'sort_order' => 3
                );
            }

            $grand_total = $sub_total + $shipping_cost;

            $totals[] = array(
                'code'       => 'total',
                'title'      => $this->language->get('text_total'),
                'value'      => $grand_total,
                'sort_order' => 9
            );
        }

        $sort_order = array();

        foreach ($totals as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }

        array_multisort($sort_order, SORT_ASC, $totals);

        $data = array();
        foreach ($totals as $total_row) {
            $data[] = array(
                'title' => $total_row['title'],
                'text'  => $this->currency->format($total_row['value'], $this->session->data['currency'])
            );
        }

        return $data;
    }
}
