<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
}
class EO_Create_Email_List {

	private $orderArray;
	private $earlyOrderDate;
	private $lastOrderDate;
	
	public function __construct($orderArray) {
		$this->orderArray = $orderArray;
		$this->update_options();
	}

	private function update_options() {
		$this->earlyOrderDate = get_option('early_order_date');
		$this->lastOrderDate = get_option('last_order_date');	
	}

	public function write_txt_attachment() {
		//Get email addresses from orders and print each one separated by tabs
		$orderArray = $this->orderArray;
		$emailList = array();
		foreach($orderArray as $orderInfo) {
			$emailList[] = trim($orderInfo['cemail']);		
		}
		$emailtxtFile = implode("\t", $emailList);
		$filename = 'email_'.date('M-d-y',$this->earlyOrderDate).'_'.date('M-d-y',$this->lastOrderDate).'.txt';
		header("Content-Encoding: ".get_bloginfo('charset')."");
		header("Content-Type: text/plain");
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		echo $emailtxtFile;
		exit();
	}
}
