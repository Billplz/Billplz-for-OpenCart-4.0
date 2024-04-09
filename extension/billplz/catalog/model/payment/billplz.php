<?php

namespace Opencart\Catalog\Model\Extension\Billplz\Payment;

use Opencart\System\Engine\Model;
use Opencart\System\Library\Log;

class Billplz extends Model
{
    public function getMethods(array $payment_address = array())
    {
        $this->load->language('extension/billplz/payment/billplz');
        $this->load->config('extension/billplz/billplz/checkout');

        $supported_currencies = $this->config->get('billplz_supported_currencies');

        $minimum_total = (float) $this->config->get('payment_billplz_total');
        $total = $this->cart->getTotal();

        if (!in_array(strtoupper($this->session->data['currency']), $supported_currencies)) {
            $status = false;
        } elseif ($minimum_total > 0 && $minimum_total > $total) {
            $status = false;
        } elseif ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_billplz_geo_zone_id')) {
            $status = true;
        } else {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get('payment_billplz_geo_zone_id') . "' AND country_id = '" . (int) $payment_address['country_id'] . "' AND (zone_id = '" . (int) $payment_address['zone_id'] . "' OR zone_id = '0')");

            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = array();

        if ($status) {
            $option_data['billplz'] = [
                'code' => 'billplz.billplz',
                'name' => $this->language->get('text_title')
            ];

            $method_data = array(
                'code'       => 'billplz',
                'name'       => $this->language->get('heading_title'),
                'option'     => $option_data,
                'sort_order' => $this->config->get('payment_billplz_sort_order'),
            );
        }

        return $method_data;
    }

    public function insertBill($order_id, $slug)
    {
        $query = $this->db->query("INSERT INTO `" . DB_PREFIX . "billplz_bill` (`order_id`, `slug`) VALUES ('$order_id', '$slug')");

        if ($query) {
            return true;
        }

        return false;
    }  

    public function getBill($slug)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "billplz_bill` WHERE `slug` = '" . $slug . "' LIMIT 1");

        if ($query->num_rows) {
            return $query->rows[0];
        }

        return false;
    }  

    public function markBillPaid($order_id, $slug)
    {
        $query = $this->db->query("UPDATE `" . DB_PREFIX . "billplz_bill` SET `paid` = '1' WHERE `order_id` = '$order_id' AND `slug` = '$slug' AND `paid` = '0'");

        if ($query) {
            return true;
        }

        return false;
    }

    public function logger($message, array $data = array())
    {
        $log = new Log('billplz.log');

        if ($data) {
            $message .= ' ' . json_encode($data);
        }

        $log->write($message);
    }
}
