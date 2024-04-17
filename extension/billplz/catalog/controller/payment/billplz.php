<?php

namespace Opencart\Catalog\Controller\Extension\Billplz\Payment;

require_once(DIR_EXTENSION . 'billplz/system/library/billplz/billplz-api.php');
require_once(DIR_EXTENSION . 'billplz/system/library/billplz/billplz-connect.php');

use Opencart\System\Engine\Controller;
use Opencart\System\Library\Extension\Billplz\Billplz\BillplzConnect;
use Opencart\System\Library\Extension\Billplz\Billplz\BillplzAPI;

class Billplz extends Controller
{
    public function index()
    {
        $this->load->language('extension/billplz/payment/billplz');

        $data = array(
            'button_confirm'  => $this->language->get('button_confirm'),
            'is_sandbox'      => $this->config->get('payment_billplz_is_sandbox'),
            'text_is_sandbox' => $this->language->get('text_is_sandbox'),
            'action'          => $this->url->link('extension/billplz/payment/billplz.checkout', 'language=' . $this->config->get('config_language'), true),
        );

        return $this->load->view('extension/billplz/payment/billplz', $data);
    }

    public function checkout()
    {
        $this->load->model('checkout/order');
        $this->load->model('localisation/order_status');
        $this->load->model('extension/billplz/payment/billplz');
        $this->load->language('extension/billplz/payment/billplz');

        $is_sandbox    = $this->config->get('payment_billplz_is_sandbox');
        $api_key       = $this->config->get('payment_billplz_api_key');
        $collection_id = $this->config->get('payment_billplz_collection_id');

        $order_id   = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $amount = $this->currency->format(
            $order_info['total'],
            $order_info['currency_code'],
            $order_info['currency_value'],
            false
        );

        $customer_names = array_map('trim', array_filter([
            $order_info['firstname'],
            $order_info['lastname']
        ]));

        $products = $this->cart->getProducts();
        $product_names = array();

        foreach ($products as $product) {
            $product_names[] = $product['name'] . ' x ' . $product['quantity'];
        }

        $bill_description = 'Order ' . $order_id . ' - ' . implode(', ', $product_names);
        $bill_description = mb_substr($bill_description, 0, 200);

        $callback_url = $this->url->link('extension/billplz/payment/billplz.callback', '', true);
        $redirect_url = $this->url->link('extension/billplz/payment/billplz.redirect', '', true);

        $parameter = array(
            'collection_id'     => trim($collection_id),
            'email'             => trim($order_info['email']),
            'mobile'            => trim($order_info['telephone']),
            'name'              => implode(' ', $customer_names),
            'amount'            => (int) ($amount * 100),
            'callback_url'      => $callback_url,
            'description'       => $bill_description,
        );

        $optional = array(
            'redirect_url'      => $redirect_url,
            'reference_1_label' => 'Order ID',
            'reference_1'       => $order_id,
        );

        if (!$parameter['email'] && !$parameter['mobile']) {
            $parameter['email'] = 'noreply@billplz.com';
        }

        if (!$parameter['name']) {
            $parameter['name'] = 'Payer Name Unavailable';
        }

        try {
            $connect = new BillplzConnect($api_key);
            $connect->setStaging($is_sandbox);

            $billplz = new BillplzAPI($connect);

            list($code, $response) = $billplz->toArray($billplz->createBill($parameter, $optional));

            $bill_id  = isset($response['id']) ? $response['id'] : '';
            $bill_url = isset($response['url']) ? $response['url'] : '';

            $error_messages = isset($response['error']['message']) ? $response['error']['message'] : '';

            if ($code === 200 && $bill_id && $bill_url) {
                $bill = $this->model_extension_billplz_payment_billplz->insertBill($order_id, $bill_id);

                if (!$bill) {
                    throw new \Exception($this->language->get('error_duplicated_bill_id'));
                }

                $billplz_pending_order_status_id = $this->config->get('payment_billplz_pending_status_id');

                $bill_details = array();
                $bill_details[] = 'Bill created.<br>';
                $bill_details[] = 'Sandbox: ' . ($is_sandbox ? 'Yes' : 'No');
                $bill_details[] = 'Bill ID: ' . $bill_id;
                $bill_details[] = 'Status: Pending';

                $order_comment = implode('<br>', $bill_details);

                $this->model_checkout_order->addHistory($order_id, $billplz_pending_order_status_id, $order_comment, false, true);

                $this->cart->clear();
                unset($this->session->data['order_id']);

                header('Location: ' . $bill_url);
            }

            if ($error_messages) {
                if (is_array($error_messages)) {
                    throw new \Exception(implode(', ', $error_messages));
                } else {
                    throw new \Exception($error_messages);
                }
            }

            throw new \Exception($this->language->get('error_unknown'));
        } catch (\Exception $e) {
            exit($e->getMessage());
        }
    }

    public function redirect()
    {
        $this->load->model('extension/billplz/payment/billplz');

        try {
            $data = BillplzConnect::getXSignature($this->config->get('payment_billplz_x_signature'));
        } catch (\Exception $e) {
            $this->model_extension_billplz_payment_billplz->logger('XSignature redirect error: ' . $e->getMessage(), $_REQUEST);
            exit($e->getMessage());
        }

        if (!$data['paid']) {
            $this->response->redirect($this->url->link('checkout/failure'));
        }

        $this->handlePaymentCompletion($data, 'redirect');

        $this->response->redirect($this->url->link('checkout/success'));        
    }

    public function callback()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new \Exception('Invalid request');
        }

        $this->load->model('extension/billplz/payment/billplz');

        try {
           $data = BillplzConnect::getXSignature($this->config->get('payment_billplz_x_signature'));
        } catch (\Exception $e) {
            $this->model_extension_billplz_payment_billplz->logger('XSignature callback error: ' . $e->getMessage(), $_REQUEST);
            exit($e->getMessage());
        }

        $this->handlePaymentCompletion($data, 'callback');

        exit('Callback success');
    }

    private function handlePaymentCompletion(array $data, $type = 'callback')
    {
        $this->load->model('extension/billplz/payment/billplz');
        $this->load->model('checkout/order');

        $bill_id    = $data['id'];
        $bill_info  = $this->model_extension_billplz_payment_billplz->getBill($bill_id);

        $order_id   = $bill_info['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $billplz_pending_order_status_id = $this->config->get('payment_billplz_pending_status_id');
        $billplz_completed_order_status_id = $this->config->get('payment_billplz_completed_status_id');

        if ($order_info['order_status_id'] == $billplz_pending_order_status_id && !$bill_info['paid']) {
            if ($this->model_extension_billplz_payment_billplz->markBillPaid($order_id, $bill_id)) {

                $is_sandbox = $this->config->get('payment_billplz_is_sandbox');

                $bill_details = array();
                $bill_details[] = 'Bill updated.<br>';
                $bill_details[] = 'Sandbox: ' . ($is_sandbox ? 'Yes' : 'No');
                $bill_details[] = 'Bill ID: ' . $bill_id;
                $bill_details[] = 'Status: Paid';
                $bill_details[] = 'Method: ' . ucwords($type);

                $order_comment = implode('<br>', $bill_details);

                $this->model_checkout_order->addHistory($order_id, $billplz_completed_order_status_id, $order_comment, false, true);
            }
        }
    }
}
