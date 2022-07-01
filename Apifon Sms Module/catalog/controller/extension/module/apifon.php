<?php

class ControllerExtensionModuleApifon extends Controller {

    public function eventModelCheckoutOrderStatus($route, $args){
        if (isset($args[1])) {
            $order_status_id = $args[1];
            if ($order_status_id == 1) {
                $type = 'pending';
            } else if ($order_status_id == 2) {
                $type = 'processing';
            } else if ($order_status_id == 3) {
                $type = 'shipped';
            } else if ($order_status_id == 5) {
                $type = 'complete';
            } else if ($order_status_id == 7) {
                $type = 'cancelled';
            } else if ($order_status_id == 10) {
                $type = 'failed';
            } else if ($order_status_id == 11) {
                $type = 'refunded';
            } else {
                $type = null;
            }
        }
        if (isset($args[0])) {
            $order_id = $args[0];

            $order_info = $this->model_checkout_order->getOrder($order_id);


            if($type!=null){

                $settings = $this->db->query("SELECT * FROM " . DB_PREFIX . "apifon_setting WHERE type = '".$type."'");
                if ($settings->row['status'] == 0) {
                    $this->load->model('setting/setting');
                
                        
                        $recipient = $order_info['telephone'];
                        $message = $settings->row['message'];
                        $amount = $order_info['total'];
                        $orderID = $order_info['order_id'];
                        $costumer_name = $order_info['firstname'] . ' ' . $order_info['lastname']; 
                        


                        $voucher = false;
                        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "eltacourier WHERE order_id = '" . (int)$orderID . "'")->row;                     
            
                        if($query){
                            $voucher = $query['tracking_id'];                            
                        } 

                        if ($voucher != false) {
                            if (strpos($message, '[VOUCHER]') !== false) {
                                $message = str_replace("[VOUCHER]", $voucher, $message);
                            }
                        }
                        
                        if (strpos($message, '[USER_NAME]') !== false) {
                            $message = str_replace("[USER_NAME]", $costumer_name, $message);
                        }
                        if (strpos($message, '[AMOUNT]') !== false) {
                            $message = str_replace("[AMOUNT]", $amount, $message);
                        }
                        if (strpos($message, '[ORDER_ID]') !== false) {
                            $message = str_replace("[ORDER_ID]", $orderID, $message);
                        }
                        $body =' {
                            "message": {
                                "text": "'.$message.'",
                                "sender_id": "TETRALUX ST"
                            },
                            "subscribers": [{
                                    "number": "30'.$recipient.'"
                                }
                            ]
                        }';

                        $token = "";
                        $secretKey = "";
                        $endpoint = "/services/sms/send";
                        $url = "https://ars.apifon.com" . $endpoint;

                        $dateTime = new \DateTime();
                        $dateTime->setTimezone(new \DateTimeZone('GMT'));
                        $requestDate = $dateTime->format('D, d M Y H:i:s T');

                        $message = "POST"."\n"
                        . $endpoint . "\n"
                        . $body . "\n"
                        . $requestDate;

                        $signature = base64_encode(hash_hmac('SHA256', $message, $secretKey, true));

                        $header = array();
                        $header[] = "Content-type: application/json";
                        $header[] = "Authorization: ApifonWS " . $token . ":" . $signature;
                        $header[] = "X-ApifonWS-Date: " . $requestDate;

                        $curl = curl_init($url);
                        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($curl, CURLOPT_HEADER, false);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
                        curl_setopt($curl, CURLOPT_POST, true);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

                        $response = curl_exec($curl);
                    
                }
            }
        }

    }

}
