<?php

class ControllerExtensionModuleApifon extends Controller {

    protected $error = array();

    public function index() {

        $this->load->language('extension/module/apifon');

        $this->document->setTitle(strip_tags($this->language->get('heading_title')));

        $this->getList();

    }
    protected function getList() {
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];

            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }
        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_home'),
            'href'      => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('text_module'),
            'href'      => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text'      => $this->language->get('heading_title'),
            'href'      => $this->url->link('extension/module/apifon', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['user_token'] = $this->session->data['user_token'];
        $settings = $this->db->query("SELECT * FROM " . DB_PREFIX . "apifon_setting");

        foreach($settings->rows as $setting){
            $data['settings'][$setting['type']]=  $setting;
        }

        $this->load->model('setting/setting');
        $data['config']=$this->model_setting_setting->getSetting('apifon');

        $this->response->setOutput($this->load->view('extension/module/apifon', $data));


    }
    
    public function apifonAjaxCustomSMS(){

        $message = $_REQUEST['custom_msg'];
        $recipient = $_REQUEST['number'];
        $body =' {
            "message": {
                "text": "'.$message.'",
                "sender_id": "SENDER_ID_HERE"
            },
            "subscribers": [{
                    "number": "'.$recipient.'"
                }
            ]
        }';

        $token = "TOKEN_HERE";
        $secretKey = "SECRETKEY_HERE";
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
    
    public function apifonAjaxNotification() {
        if ($this->validatePermission()) {
            $this->load->language('extension/module/apifon');
            
            $this->db->query("UPDATE " . DB_PREFIX . "apifon_setting SET status = '" . $_REQUEST['send_sms_shipped'] . "', message ='" . $_REQUEST['shipped_msg'] . "' WHERE type = 'shipped'");
            $this->db->query("UPDATE " . DB_PREFIX . "apifon_setting SET status = '" . $_REQUEST['send_sms_pending'] . "', message ='" . $_REQUEST['pending_msg'] . "' WHERE type = 'pending'");
            $this->db->query("UPDATE " . DB_PREFIX . "apifon_setting SET status = '" . $_REQUEST['send_sms_processing'] . "', message ='" . $_REQUEST['processing_msg'] . "' WHERE type = 'processing'");
            $this->db->query("UPDATE " . DB_PREFIX . "apifon_setting SET status = '" . $_REQUEST['send_sms_failed'] . "', message ='" . $_REQUEST['failed_msg'] . "' WHERE type = 'failed'");
            $this->db->query("UPDATE " . DB_PREFIX . "apifon_setting SET status = '" . $_REQUEST['send_sms_complete'] . "', message ='" . $_REQUEST['complete_msg'] . "' WHERE type = 'complete'");
            $this->db->query("UPDATE " . DB_PREFIX . "apifon_setting SET status = '" . $_REQUEST['send_sms_cancelled'] . "', message ='" . $_REQUEST['cancelled_msg'] . "' WHERE type = 'cancelled'");
            $this->db->query("UPDATE " . DB_PREFIX . "apifon_setting SET status = '" . $_REQUEST['send_sms_refunded'] . "', message ='" . $_REQUEST['refunded_msg'] . "' WHERE type = 'refunded'");

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->setOutput(json_encode());
        }
    }

    protected function validatePermission() {
        if (!$this->user->hasPermission('modify', 'extension/module/apifon')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }

    public function install() {
        if (!$this->user->hasPermission('modify', 'extension/extension/module')) {
            return;
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "apifon_setting` (
		  `apifon_id` int(11) NOT NULL AUTO_INCREMENT,
		  `type` varchar(255) NOT NULL,
		  `status` int(11) NOT NULL,
		  `name` varchar(32) NOT NULL,
		  `message` varchar(255) NOT NULL,
		  PRIMARY KEY (`apifon_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=4 ;");

        $this->db->query("INSERT INTO `" . DB_PREFIX . "apifon_setting` (`apifon_id`, `type`, `status`, `name`, `message`) VALUES
			(1, 'shipped', 0, 'Send SMS on Shipped', 'Η παραγγελία [ORDER_ID] παραδόθηκε στα ΕΛΤΑ Courier, αναζητήστε την αποστολή με κωδικό [VOUCHER] εδώ  https://www.elta-courier.gr/search Ευχαριστούμε !'),
			(2, 'refunded', 1, 'Send SMS on Refunded', '[USER_NAME], συγγνώμη για την ταλαιπωρία. Είμαστε επεξεργασία του αιτήματός σας για επιστροφή χρημάτων για την παραγγελία σας [ORDER_ID].'),
			(3, 'pending', 1, 'Send SMS on Pending', 'Αγαπητέ [USER_NAME], η παραγγελία σας [ORDER_ID]  έχει υποβληθεί'),
			(4, 'processing', 1, 'Send SMS on Processing', 'Αγαπητέ [USER_NAME], σας ευχαριστούμε για την παραγγελία [ORDER_ID] .Το ποσο πληρωμή ειναι : [AMOUNT] .'),
			(5, 'failed', 1, 'Send SMS on Failed', 'Αγαπητέ [USER_NAME], λυπούμαστε αλλά η παραγγελία σας [ORDER_ID] απέτυχε!'),
			(6, 'complete', 1, 'Send SMS on Complete', 'Αγαπητέ [USER_NAME], σας ευχαριστούμε που ψωνίσατε απο μας. Η παραγγελία σας ολοκληρώθηκε !'),
			(7, 'cancelled', 1, 'Send SMS on Cancelled', 'Αγαπητέ [USER_NAME], λυπούμαστε αλλά η παραγγελία [ORDER_ID] ακυρώθηκε!');
		");

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_apifon', ['module_apifon_status'=>1]);

        $this->load->model('setting/event');

        $this->model_setting_event->addEvent('module_apifon', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/module/apifon/eventModelCheckoutOrderStatus');
    }
    public function uninstall() {
        if (!$this->user->hasPermission('modify', 'extension/extension/module')) {
            return;
        }

        $this->db->query("DROP TABLE IF EXISTS " . DB_PREFIX . "apifon_setting");

        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode('module_apifon');

        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('apifon');
        $this->model_setting_setting->deleteSetting('deleteSetting');
    }

}