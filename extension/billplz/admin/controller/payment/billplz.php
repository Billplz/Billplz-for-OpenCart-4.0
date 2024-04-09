<?php

namespace Opencart\Admin\Controller\Extension\Billplz\Payment;

require_once(DIR_EXTENSION . 'billplz/system/library/billplz/billplz-api.php');
require_once(DIR_EXTENSION . 'billplz/system/library/billplz/billplz-connect.php');

use Opencart\System\Engine\Controller;
use Opencart\System\Library\Extension\Billplz\Billplz\BillplzConnect;
use Opencart\System\Library\Extension\Billplz\Billplz\BillplzAPI;

class Billplz extends Controller
{
    private $errors = array();

    public function index()
    {
        $this->load->language('extension/billplz/payment/billplz');
        $this->load->model('extension/billplz/payment/billplz');
        $this->load->model('setting/setting');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
            ),
            array(
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
            ),
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/billplz/payment/billplz', 'user_token=' . $this->session->data['user_token'])
            ),
        );

        $data['save'] = $this->url->link('extension/billplz/payment/billplz.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        $data['heading_title']                       = $this->language->get('heading_title');

        $data['text_edit']                           = $this->language->get('text_edit');
        $data['text_enabled']                        = $this->language->get('text_enabled');
        $data['text_disabled']                       = $this->language->get('text_disabled');
        $data['text_all_zones']                      = $this->language->get('text_all_zones');
        $data['text_yes']                            = $this->language->get('text_yes');
        $data['text_no']                             = $this->language->get('text_no');

        $data['billplz_is_sandbox']                  = $this->language->get('billplz_is_sandbox');
        $data['billplz_api_key']                     = $this->language->get('billplz_api_key');
        $data['billplz_collection_id']               = $this->language->get('billplz_collection_id');
        $data['billplz_x_signature']                 = $this->language->get('billplz_x_signature');

        $data['entry_total']                         = $this->language->get('entry_total');
        $data['entry_completed_status']              = $this->language->get('entry_completed_status');
        $data['entry_pending_status']                = $this->language->get('entry_pending_status');
        $data['entry_geo_zone']                      = $this->language->get('entry_geo_zone');
        $data['entry_sort_order']                    = $this->language->get('entry_sort_order');
        $data['entry_status']                        = $this->language->get('entry_status');

        $data['help_is_sandbox']                     = $this->language->get('help_is_sandbox');
        $data['help_api_key']                        = $this->language->get('help_api_key');
        $data['help_collection_id']                  = $this->language->get('help_collection_id');
        $data['help_x_signature']                    = $this->language->get('help_x_signature');
        $data['help_total']                          = $this->language->get('help_total');

        $data['button_save']                         = $this->language->get('button_save');
        $data['button_cancel']                       = $this->language->get('button_cancel');

        $data['tab_api_credentials']                 = $this->language->get('tab_api_credentials');
        $data['tab_general']                         = $this->language->get('tab_general');
        $data['tab_order_status']                    = $this->language->get('tab_order_status');

        // Posted settings data
        $data['payment_billplz_is_sandbox']          = $this->config->get('payment_billplz_is_sandbox');
        $data['payment_billplz_api_key']             = $this->config->get('payment_billplz_api_key');
        $data['payment_billplz_collection_id']       = $this->config->get('payment_billplz_collection_id');
        $data['payment_billplz_x_signature']         = $this->config->get('payment_billplz_x_signature');
        $data['payment_billplz_total']               = $this->config->get('payment_billplz_total');
        $data['payment_billplz_completed_status_id'] = $this->config->get('payment_billplz_completed_status_id');
        $data['payment_billplz_pending_status_id']   = $this->config->get('payment_billplz_pending_status_id');
        $data['payment_billplz_geo_zone_id']         = $this->config->get('payment_billplz_geo_zone_id');
        $data['payment_billplz_status']              = $this->config->get('payment_billplz_status');
        $data['payment_billplz_sort_order']          = $this->config->get('payment_billplz_sort_order');

        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        // Set default order status
        foreach ($data['order_statuses'] as $order_status) {
            if ($order_status['name'] === 'Complete') {
                $data['payment_billplz_completed_status_id'] = $order_status['order_status_id'];
            }

            if ($order_status['name'] === 'Pending') {
                $data['payment_billplz_pending_status_id'] = $order_status['order_status_id'];
            }

            if ($data['payment_billplz_completed_status_id'] && $data['payment_billplz_pending_status_id']) {
                break;
            }
        }

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput(
            $this->load->view('extension/billplz/payment/billplz', $data)
        );
    }

    public function save()
    {
        $json = array();

        $this->load->language('extension/billplz/payment/billplz');

        if (!$this->user->hasPermission('modify', 'extension/billplz/payment/billplz')) {
            $this->errors['warning'] = $this->language->get('error_permission');
        }

        $this->validatePostedData();

        // If no errors, proceed to save the settings
        if (!$this->errors) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_billplz', $this->request->post);

            $json['success'] = $this->language->get('text_success');
        } else {
            $json['error'] = $this->errors;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function getPostedData($key)
    {
        if (isset($this->request->post[$key])) {
            return $this->request->post[$key];
        }

        return '';
    }

    private function validatePostedData()
    {
        $api_key       = $this->getPostedData('payment_billplz_api_key');
        $x_signature   = $this->getPostedData('payment_billplz_x_signature');
        $collection_id = $this->getPostedData('payment_billplz_collection_id');
        $is_sandbox    = $this->getPostedData('payment_billplz_is_sandbox');

        if (!$api_key) {
            $this->errors['billplz_api_key'] = $this->language->get('error_api_key');
        }

        if (!$collection_id) {
            $this->errors['billplz_collection_id'] = $this->language->get('error_collection_id');
        }

        if (!$x_signature) {
            $this->errors['billplz_x_signature'] = $this->language->get('error_x_signature');
        }

        if ($api_key && $collection_id) {
            $connect = new BillplzConnect($api_key);
            $connect->setStaging($is_sandbox);

            $billplz = new BillplzAPI($connect);

            list($code, $body) = $billplz->toArray($billplz->getCollection($collection_id));

            if ($code !== 200) {
                $this->errors['warning'] = $this->language->get('error_api_credentials');
            }
        }

        return $this->errors;
    }

    public function install()
    {
        if ($this->user->hasPermission('modify', 'extension/payment')) {
            $this->load->model('extension/billplz/payment/billplz');

            $this->model_extension_billplz_payment_billplz->install();
        }
    }

    public function uninstall()
    {
        if ($this->user->hasPermission('modify', 'extension/payment')) {
            $this->load->model('extension/billplz/payment/billplz');

            $this->model_extension_billplz_payment_billplz->uninstall();
        }
    }
}
