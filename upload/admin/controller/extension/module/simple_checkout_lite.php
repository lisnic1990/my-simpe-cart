<?php
/**
 * Simple Checkout Lite - Admin Controller
 * Модуль упрощенного оформления заказа для ocStore 3.0.3.7
 */
class ControllerExtensionModuleSimpleCheckoutLite extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/module/simple_checkout_lite');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_simple_checkout_lite', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        // Errors
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/simple_checkout_lite', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/simple_checkout_lite', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // Module Status
        if (isset($this->request->post['module_simple_checkout_lite_status'])) {
            $data['module_simple_checkout_lite_status'] = $this->request->post['module_simple_checkout_lite_status'];
        } else {
            $data['module_simple_checkout_lite_status'] = $this->config->get('module_simple_checkout_lite_status');
        }

        // Fields Configuration
        $fields = array('firstname', 'lastname', 'email', 'telephone', 'address_1', 'address_2', 'city', 'postcode', 'country', 'zone', 'company');

        foreach ($fields as $field) {
            $key = 'module_simple_checkout_lite_field_' . $field;
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $data[$key] = $this->config->get($key);
                if ($data[$key] === null) {
                    // Default values
                    if (in_array($field, array('firstname', 'email', 'telephone'))) {
                        $data[$key] = 'required';
                    } elseif (in_array($field, array('lastname', 'address_1', 'city', 'country', 'zone'))) {
                        $data[$key] = 'visible';
                    } else {
                        $data[$key] = 'hidden';
                    }
                }
            }
        }

        // Steps Configuration
        $steps = array('shipping_address', 'shipping_method', 'payment_method', 'comment');

        foreach ($steps as $step) {
            $key = 'module_simple_checkout_lite_step_' . $step;
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $data[$key] = $this->config->get($key);
                if ($data[$key] === null) {
                    $data[$key] = 1; // Show by default
                }
            }
        }

        // Guest Checkout
        if (isset($this->request->post['module_simple_checkout_lite_guest'])) {
            $data['module_simple_checkout_lite_guest'] = $this->request->post['module_simple_checkout_lite_guest'];
        } else {
            $data['module_simple_checkout_lite_guest'] = $this->config->get('module_simple_checkout_lite_guest');
            if ($data['module_simple_checkout_lite_guest'] === null) {
                $data['module_simple_checkout_lite_guest'] = 1;
            }
        }

        // Default Payment Method
        if (isset($this->request->post['module_simple_checkout_lite_payment_default'])) {
            $data['module_simple_checkout_lite_payment_default'] = $this->request->post['module_simple_checkout_lite_payment_default'];
        } else {
            $data['module_simple_checkout_lite_payment_default'] = $this->config->get('module_simple_checkout_lite_payment_default');
        }

        // Default Shipping Method
        if (isset($this->request->post['module_simple_checkout_lite_shipping_default'])) {
            $data['module_simple_checkout_lite_shipping_default'] = $this->request->post['module_simple_checkout_lite_shipping_default'];
        } else {
            $data['module_simple_checkout_lite_shipping_default'] = $this->config->get('module_simple_checkout_lite_shipping_default');
        }

        // Default Country
        if (isset($this->request->post['module_simple_checkout_lite_country_default'])) {
            $data['module_simple_checkout_lite_country_default'] = $this->request->post['module_simple_checkout_lite_country_default'];
        } else {
            $data['module_simple_checkout_lite_country_default'] = $this->config->get('module_simple_checkout_lite_country_default');
            if ($data['module_simple_checkout_lite_country_default'] === null) {
                $data['module_simple_checkout_lite_country_default'] = $this->config->get('config_country_id');
            }
        }

        // Default Zone
        if (isset($this->request->post['module_simple_checkout_lite_zone_default'])) {
            $data['module_simple_checkout_lite_zone_default'] = $this->request->post['module_simple_checkout_lite_zone_default'];
        } else {
            $data['module_simple_checkout_lite_zone_default'] = $this->config->get('module_simple_checkout_lite_zone_default');
            if ($data['module_simple_checkout_lite_zone_default'] === null) {
                $data['module_simple_checkout_lite_zone_default'] = $this->config->get('config_zone_id');
            }
        }

        // Get countries list
        $this->load->model('localisation/country');
        $data['countries'] = $this->model_localisation_country->getCountries();

        // Get available payment methods
        $this->load->model('setting/extension');
        $data['payment_methods'] = array();
        $extensions = $this->model_setting_extension->getInstalled('payment');
        foreach ($extensions as $code) {
            if ($this->config->get('payment_' . $code . '_status')) {
                $this->load->language('extension/payment/' . $code, 'payment_' . $code);
                $data['payment_methods'][] = array(
                    'code' => $code,
                    'title' => $this->language->get('payment_' . $code)->get('heading_title')
                );
            }
        }

        // Get available shipping methods
        $data['shipping_methods'] = array();
        $extensions = $this->model_setting_extension->getInstalled('shipping');
        foreach ($extensions as $code) {
            if ($this->config->get('shipping_' . $code . '_status')) {
                $this->load->language('extension/shipping/' . $code, 'shipping_' . $code);
                $data['shipping_methods'][] = array(
                    'code' => $code,
                    'title' => $this->language->get('shipping_' . $code)->get('heading_title')
                );
            }
        }

        // Language strings
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_yes'] = $this->language->get('text_yes');
        $data['text_no'] = $this->language->get('text_no');
        $data['text_required'] = $this->language->get('text_required');
        $data['text_visible'] = $this->language->get('text_visible');
        $data['text_hidden'] = $this->language->get('text_hidden');

        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_guest'] = $this->language->get('entry_guest');
        $data['entry_payment_default'] = $this->language->get('entry_payment_default');
        $data['entry_shipping_default'] = $this->language->get('entry_shipping_default');
        $data['entry_country_default'] = $this->language->get('entry_country_default');
        $data['entry_zone_default'] = $this->language->get('entry_zone_default');
        $data['text_select'] = $this->language->get('text_select');
        $data['text_none'] = $this->language->get('text_none');
        $data['text_country_help'] = $this->language->get('text_country_help');

        $data['tab_general'] = $this->language->get('tab_general');
        $data['tab_fields'] = $this->language->get('tab_fields');
        $data['tab_steps'] = $this->language->get('tab_steps');

        $data['entry_field_firstname'] = $this->language->get('entry_field_firstname');
        $data['entry_field_lastname'] = $this->language->get('entry_field_lastname');
        $data['entry_field_email'] = $this->language->get('entry_field_email');
        $data['entry_field_telephone'] = $this->language->get('entry_field_telephone');
        $data['entry_field_address_1'] = $this->language->get('entry_field_address_1');
        $data['entry_field_address_2'] = $this->language->get('entry_field_address_2');
        $data['entry_field_city'] = $this->language->get('entry_field_city');
        $data['entry_field_postcode'] = $this->language->get('entry_field_postcode');
        $data['entry_field_country'] = $this->language->get('entry_field_country');
        $data['entry_field_zone'] = $this->language->get('entry_field_zone');
        $data['entry_field_company'] = $this->language->get('entry_field_company');

        $data['entry_step_shipping_address'] = $this->language->get('entry_step_shipping_address');
        $data['entry_step_shipping_method'] = $this->language->get('entry_step_shipping_method');
        $data['entry_step_payment_method'] = $this->language->get('entry_step_payment_method');
        $data['entry_step_comment'] = $this->language->get('entry_step_comment');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        $data['user_token'] = $this->session->data['user_token'];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/simple_checkout_lite', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/simple_checkout_lite')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    public function install() {
        $this->load->model('setting/setting');

        // Default settings
        $defaults = array(
            'module_simple_checkout_lite_status' => 0,
            'module_simple_checkout_lite_guest' => 1,
            'module_simple_checkout_lite_field_firstname' => 'required',
            'module_simple_checkout_lite_field_lastname' => 'visible',
            'module_simple_checkout_lite_field_email' => 'required',
            'module_simple_checkout_lite_field_telephone' => 'required',
            'module_simple_checkout_lite_field_address_1' => 'visible',
            'module_simple_checkout_lite_field_address_2' => 'hidden',
            'module_simple_checkout_lite_field_city' => 'visible',
            'module_simple_checkout_lite_field_postcode' => 'hidden',
            'module_simple_checkout_lite_field_country' => 'visible',
            'module_simple_checkout_lite_field_zone' => 'visible',
            'module_simple_checkout_lite_field_company' => 'hidden',
            'module_simple_checkout_lite_step_shipping_address' => 1,
            'module_simple_checkout_lite_step_shipping_method' => 1,
            'module_simple_checkout_lite_step_payment_method' => 1,
            'module_simple_checkout_lite_step_comment' => 1,
        );

        $this->model_setting_setting->editSetting('module_simple_checkout_lite', $defaults);
    }

    public function uninstall() {
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_simple_checkout_lite');
    }

    /**
     * AJAX: Get zones by country_id
     */
    public function zone() {
        $json = array();

        $this->load->model('localisation/zone');

        $country_id = isset($this->request->get['country_id']) ? (int)$this->request->get['country_id'] : 0;

        $results = $this->model_localisation_zone->getZonesByCountryId($country_id);

        foreach ($results as $result) {
            $json[] = array(
                'zone_id' => $result['zone_id'],
                'name'    => $result['name'],
                'code'    => $result['code']
            );
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
