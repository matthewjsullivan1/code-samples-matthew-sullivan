<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
}
require_once('class-create-item-tags.php');
class EO_Create_Order_Table {
	
	public $wpdb;
	private $wp_options;
	private $orderTableName;
	private $menuItems;
	private $earlyOrderDate;
	private $lastOrderDate;
	private $orderCombo;
	private $orderComboProcessed;
	private $addSizeToTag;
	private $modifyTags;
 	private $orderQueryResults;
	private $orderArray;
	private $menuItemTags;
	private $orderItemMatrix;
	private $orderItemMatrixWithTotal;
	private $customerMatrix;



	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->orderTableName = $this->wpdb->prefix ."". WPPIZZA_SLUG . "_orders";
		$this->wp_options = get_option(WPPIZZA_SLUG,0);
		$this->update_options();
	}

	private function update_options() {
		//Gets settings from database
		$this->earlyOrderDate = get_option('early_order_date');
		$this->lastOrderDate = get_option('last_order_date');	
		$this->orderCombo = get_option('order_combo');
		$this->addSizeToTag = get_option('add_size_to_tag');	
		$this->modifyTags = get_option('modify_tags');
	}
	
	public function basic_process_for_emails() {
		//Quick process of orders to get email addresses
		$this->process_order_query();
		return $this->orderArray;
	}
	
	public function main_process() {
		//Main function to create order matrix
		$this->get_menu_items();
		$this->process_order_query();
		$this->make_tags();
		$this->create_order_matrices();
	}

	private function get_menu_items() {
		//Gets menu items from WPPizza
		//Copied and slightly modified from WPPizza plugin code
		$wpTime=current_time('timestamp');
		$processOrder=array();
		$processCustomer = array();

		/************************************************************************
			get all wppizza menu items by id and size
		************************************************************************/
		$args = array('post_type' => ''.WPPIZZA_POST_TYPE.'','posts_per_page' => -1, 'orderby'=>'title' ,'order' => 'ASC');
		$getWppizzaMenuItems = new WP_Query( $args );
		wp_reset_query();
		$wppizzaMenuItems=array();
		if( $getWppizzaMenuItems->have_posts()){
			/*loop through items*/
			foreach($getWppizzaMenuItems->posts as $menuItem){
				$meta=get_post_meta($menuItem->ID, WPPIZZA_POST_TYPE, true );
				$sizes=$this->wp_options['sizes'][$meta['sizes']];
				/*loop through sizes*/
				if(is_array($sizes)){
					foreach($sizes as $sizekey=>$size){
						/*make key from id and size*/
						$miKey=$menuItem->ID.'.'.$sizekey;
						$wppizzaMenuItems[$miKey]=array('ID'=>$menuItem->ID,'name'=>$menuItem->post_title,'sizekey'=>$sizekey,'size'=>$size['lbl'], 'minprice'=>min($meta['prices']));
					}
				}
			}
		}
		$this->menuItems=$wppizzaMenuItems;
	}

	private function run_database_query(){
		//Gets orders from database within a date range
		//Copied and slightly modified from WPPizza plugin code
		$tableName = $this->orderTableName;
		$ordersQuery="SELECT id,order_date as oDate ,";
		if(defined('WPPIZZA_REPORT_NO_DB_OFFSET')){/* in case accounting for the mysql timezone offset causes issues */
			$ordersQuery.="UNIX_TIMESTAMP(order_date) ";
		}else{
			$ordersQuery.="UNIX_TIMESTAMP(order_date)-TIMESTAMPDIFF(SECOND, NOW(), UTC_TIMESTAMP()) ";	
		}
		$ordersQuery.="as order_date, order_ini, initiator, order_status, customer_ini FROM ". $tableName ." WHERE payment_status IN ('COD','COMPLETED') ";

		$firstDateReport=date('Y-m-d H:i:s', $this->earlyOrderDate);
		$lastDateReport=date('Y-m-d H:i:s', $this->lastOrderDate);

		$ordersQuery.= "AND order_date >='".$firstDateReport."'  AND order_date <= '".$lastDateReport."' ";
		$ordersQuery.='ORDER BY order_date ASC';
		$this->orderQueryResults = $this->wpdb->get_results($ordersQuery);
	}

	private function process_order_query() {
		//Process database query and writes $orderArray containing all order information 
		//Copied and slightly modified from WPPizza plugin code
		$this->run_database_query();
		$ordersQueryResults = $this->orderQueryResults;
		$processOrder = array();
		foreach($ordersQueryResults as $qKey=>$order){
			if($order->customer_ini != ''){
				$customerDetails=maybe_unserialize($order->customer_ini);/**unserialize order details**/
				$customerDetails['InputtedPickupTime'] = $customerDetails['ctel'];
				$customerDetails['PickupTime']=$this->convert_pickupTime($customerDetails['ctel']);
				$customerDetails['LowercaseName']=strtolower($customerDetails['cname']);
				$processCustomer[]=$customerDetails;
			}
			if($order->order_ini!=''){
				$orderDetails=maybe_unserialize($order->order_ini);/**unserialize order details**/
				/**eliminate some notices**/
				$orderDetails['taxes_included']=!empty($orderDetails['taxes_included']) ? $orderDetails['taxes_included'] : 0;
				/*************************************************************************************
					some collations - especially if importing from other/older db's that were still
					ISO instead of UTF may get confused by the collation and throw serialization errors
					the following *trys* to fix this , but is not 100% guaranteed to work in all cases
					99% of the time though this won't happen anyway, as it should only ever
					possibly be the case with very early versions of wppizza or if importing from early
					versions that have a different charset.
					....worth a try though regardless
				************************************************************************************/
				if(!isset($orderDetails['total'])){
					//print"".PHP_EOL.$order->id." | ". $order->oDate." | ".$orderDetails['total'];
					$orderDetails=$order->order_ini;
					/**convert currency symbols individuallly first to UTF*/
					$convCurr=iconv("ISO-8859-1","UTF-8", $reportCurrency);
					$orderDetails=str_replace($reportCurrency,$convCurr,$orderDetails);
					/**convert to ISO **/
					$encoding   = mb_detect_encoding($orderDetails);
					$orderDetails=iconv($encoding,"ISO-8859-1//IGNORE", $orderDetails);
					/**unseralize**/
					$orderDetails=maybe_unserialize($orderDetails);
					/**if we still have unrescuable errors we *could*  catch them somewhere */
					if(!isset($orderDetails['total'])){
						//$encoding   = mb_detect_encoding($order->order_ini);
						//$errors=wppizza_serialization_errors($order->order_ini);
						//file_put_contents('','.$order->id.': ['.$encoding.'] '.print_r($order->order_ini,true).' '.print_r($errors,true).PHP_EOL.PHP_EOL,FILE_APPEND);
					}
				}

				if(isset($orderDetails['total'])){
					/**tidy up a bit and get rid of stuff we do not need**/
					unset($orderDetails['currencyiso']);
					unset($orderDetails['currency']);
					unset($orderDetails['discount']);
					unset($orderDetails['delivery_charges']);
					unset($orderDetails['tips']);
					unset($orderDetails['selfPickup']);
					unset($orderDetails['time']);
					/**add new**/
					$orderDetails['order_date']=substr($order->oDate,0,10);
					$orderDetails['order_items_count']=0;

					/**sanitize the items**/
					$itemDetails=array();
					if(isset($orderDetails['item'])){
						foreach($orderDetails['item'] as $k=>$uniqueItems){
							//$itemDetails[$k]['postId']=$uniqueItems['postId'];
							$itemDetails[$k]['name']=$uniqueItems['name']; 
							$itemDetails[$k]['quantity']=$uniqueItems['quantity'];
							$itemDetails[$k]['price']=$uniqueItems['price']; 
							$itemDetails[$k]['pricetotal']=$uniqueItems['pricetotal'];
							/**add count of items in this order**/
							$orderDetails['order_items_count']+=$uniqueItems['quantity'];
						}
					}
					/**add relevant item info to array**/
					$orderDetails['item']=$itemDetails;
					$processOrder[]=array_merge($customerDetails, $orderDetails);
				}
			}
		}
		$this->orderArray = $this->array_orderby($processOrder, 'PickupTime', SORT_ASC, 'LowercaseName', SORT_ASC);
	}

	private function array_orderby() {
		//Sorts multidimensional arrays
                //Written by jimpoz on php.net
		$args = func_get_args();
		$data = array_shift($args);
		foreach ($args as $n => $field) {
		    if (is_string($field)) {
		        $tmp = array();
		        foreach ($data as $key => $row)
		            $tmp[$key] = $row[$field];
		        $args[$n] = $tmp;
		        }
		}
		$args[] = &$data;
		call_user_func_array('array_multisort', $args);
		return array_pop($args);
	}

	private function findfirstdigitset($inputstring){
		//Processes a user inputted pickup time 
		//findfirstdigitset('maybe 430ish') returns 430
		//Returns first set of digits only separated by spaces
		$inputarray = str_split($inputstring);
		$outputarray = array();
		$sethasbegun = False;
		foreach($inputarray as $char) {
			if (is_numeric($char) and !$sethasbegun) {
				$sethasbegun = True;
				array_push($outputarray, $char);
			} elseif (is_numeric($char) and $sethasbegun) {
				array_push($outputarray, $char);
			} elseif (!(is_numeric($char) or $char == ' ') and $sethasbegun) {
				break;
			}
		}
		return implode($outputarray);
	}

	private function convert_24hour($hour, $min = 0) {
		//Converts user inputted time to unix timestamp
		//if hour between 1 and 9, assume PM.
		if(($hour >= 1) && ($hour <= 9)) {
			$hour = $hour + 12;
		}
		return mktime($hour, $min);
	}

	private function convert_pickupTime($pickupTimestring){
		//Process user inputted pickup time
		//If user gives interval, separated by '-', return first time
		//If cannot parse input into a unix timestamp, return 00:00
		$pickupTimeunix = mktime(0, 0);
		if(explode('-',$pickupTimestring)){
			$pickupTimestring=explode('-',$pickupTimestring)[0];
		}
		$processpickupTime = str_replace(":","",$pickupTimestring);
		$processpickupTime = $this->findfirstdigitset($processpickupTime);
		if((strlen($processpickupTime) >= 1) && (strlen($processpickupTime)<= 2)) {
			$pickupHour = $processpickupTime;
			if(($pickupHour >= 1) && ($pickupHour <= 23)) {
				$pickupTimeunix = $this->convert_24hour($pickupHour);
			}
		} elseif(strlen($processpickupTime) == 3) {
			$pickupHour = substr($processpickupTime, 0 , 1);
			$pickupMin = substr($processpickupTime, 1 , 2);
			if(($pickupHour >= 1) && ($pickupHour <= 9)) {
				if(($pickupMin >= 0) && ($pickupMin <= 59)) {
					$pickupTimeunix = $this->convert_24hour($pickupHour, $pickupMin);
				}
			}
		} elseif(strlen($processpickupTime) == 4) {
			$pickupHour = substr($processpickupTime, 0 , 2);
			$pickupMin = substr($processpickupTime, 2 , 2);
			if(($pickupHour >= 1) && ($pickupHour <= 23)) {
				if(($pickupMin >= 0) && ($pickupMin <= 59)) {
					$pickupTimeunix = $this->convert_24hour($pickupHour, $pickupMin);
				}
			}
		}
		return $pickupTimeunix;
	}
	
	private function make_tags() {
		$tagCreator = new EO_Create_Item_Tags($this->menuItems, $this->orderArray, $this->modifyTags, $this->addSizeToTag);
		$this->menuItemTags = $tagCreator->create_tags();
	}

	
	private function create_combo_array() {
		//Processes array given by admin
		//{"Entree":"Rice-1,Dal-1"} returns array('Entree' => array(['tag'=> 'Rice, 'quantity' => 1], ['tag' => 'Dal', 'quantity' => 1])
		if(empty($this->orderCombo)) {
			return;
		}
		$orderCombo = $this->orderCombo;
		$orderComboProcessed = array();
		foreach($orderCombo as $sizeKey => $comboItems) {
			$itemandQuantity = explode(',', $comboItems);
			$orderComboProcessed[$sizeKey] = array();
			foreach($itemandQuantity as $item) {
				$processedItem = explode('-',$item);
				$orderComboProcessed[$sizeKey][] = array('tag' => $processedItem[0], 'quantity' => $processedItem[1]);
			}
		}
		$this->orderComboProcessed = $orderComboProcessed;
	}

	private function add_combo_items($size, $quantity, $orderKey, &$orderItems) {
		//Adds combo items to orderItems if particular size has a combo
		$itemMultiplier = 1;
		if(empty($this->orderComboProcessed)){
			return $itemMultiplier;
		}
		$combos = $this->orderComboProcessed;
		if(array_key_exists($size, $combos)) {
			foreach($combos[$size] as $comboItem) {
				$tagComboItem = $comboItem['tag'];
				if(strtolower($tagComboItem) === 'self') {
					$itemMultiplier = $comboItem['quantity'];
				} else {
					$comboQuantity = $quantity;
					if(array_key_exists($tagComboItem, $orderItems[$orderKey])) {
						$orderItems[$orderKey][$tagComboItem] += $quantity * $comboItem['quantity'];
					} else {
						$orderItems[$orderKey][$tagComboItem] = $quantity * $comboItem['quantity'];
					}	
				}
			}
		}
		return $itemMultiplier;
	}

	private function create_order_matrices() {
		//Writes order matrixes used for final html output
		$this->create_ordered_item_matrix();
		$this->create_customer_matrix();
	}

	private function create_ordered_item_matrix() { 
		//Processes orderArray to generate orderItemMatrix
		$this->create_combo_array();
		$orderArray = $this->orderArray;
		$menuItemTags =	$this->menuItemTags;
		$menuItems = $this->menuItems;
		$orderItems = array();
		//Adds all ordered items in each order to orderItems
		foreach($orderArray as $orderKey => $order) {
			$orderItems[$orderKey] = array();
			foreach($order['item'] as $key=>$orderedItem){
				$explodedID = explode('.',$key);
				$itemID=$explodedID[0].'.'.$explodedID[1];
				$itemMultiplier = $this->add_combo_items($menuItems[$itemID]['size'], $orderedItem['quantity'], $orderKey, $orderItems);
				$tagItem = $menuItemTags[$key];
				if(array_key_exists($tagItem, $orderItems[$orderKey])) {
					$orderItems[$orderKey][$tagItem] += $itemMultiplier * $orderedItem['quantity'];
				} else {
					$orderItems[$orderKey][$tagItem] = $itemMultiplier * $orderedItem['quantity'];
				}
			}
		}
		//Looks for combo items that aren't in menuItemTags
		$unsortedComboItems = array();
		foreach($orderItems as $orderID => $itemArray) {
			foreach($itemArray as $tag => $quantity) {
				if(!in_array($tag, $menuItemTags) && !in_array($tag, $unsortedComboItems)) {
					$unsortedComboItems[] = $tag;
				}
			}
		}


		//Creates orderItemMatrix
		//Tags are in same order as menuItemTags and sets value to 0 if not ordered
		$orderItemMatrix = array();
		foreach($orderItems as $orderID => $itemArray) {
			$orderItemMatrix[$orderID] = array();
			foreach($menuItemTags as $tag) {
				if(array_key_exists($tag, $itemArray)) {
					$orderItemMatrix[$orderID][$tag] = $itemArray[$tag];
				} else {
					$orderItemMatrix[$orderID][$tag] = 0;
				}
			}
			//Checks each order for combo items that aren't in menuItemTags and adds to end of each row
			if(!empty($unsortedComboItems)) { 
				foreach($unsortedComboItems as $tag) {
					if(array_key_exists($tag, $itemArray)) {
						$orderItemMatrix[$orderID][$tag] = $itemArray[$tag];
					} else {
						$orderItemMatrix[$orderID][$tag] = 0;
					}
				}
			}
		}
		$this->orderItemMatrix = $orderItemMatrix;
		$this->add_totals_orderItemMatrix();
	}

	private function add_totals_orderItemMatrix() {
		//Adds another row to orderItemMatrix containing totals for each tag
		$orderItemMatrix = $this->orderItemMatrix;
		$totalsRow = array_fill_keys(array_keys(current($orderItemMatrix)),0);
		foreach($orderItemMatrix as $itemArray) {
			foreach($itemArray as $itemTag => $itemQuantity) {
				$totalsRow[$itemTag] += $itemQuantity;
			}
		}
		$orderItemMatrix[] = $totalsRow;
		$this->orderItemMatrixWithTotal = $orderItemMatrix;
	}

	private function create_customer_matrix() {
		//Processes customer information and creates customerMatrix
		//Columns contain ('Time','Name','#','Comments','Subtotal')
		$orderArray = $this->orderArray;
		$customerTags = array('Time','Name','#','Comments','Subtotal');
		$customerMatrix = array();
		foreach($orderArray as $orderID => $orderInfo) {
			$customerMatrix[$orderID] = array();
			$customerMatrix[$orderID]['Time'] = $orderInfo['InputtedPickupTime'];
			$customerMatrix[$orderID]['Name'] = $orderInfo['cname'];
			$customerMatrix[$orderID]['#'] = $orderInfo['caddress'];		
			$customerMatrix[$orderID]['Comments'] = $orderInfo['ccustom1'];
			$customerMatrix[$orderID]['Subtotal'] = $orderInfo['total_price_items'];
		}

		$totalsRow = array_fill_keys(array_keys(current($customerMatrix)),'');
		$totalsRow['Comments'] = 'TOTALS';
		$totalsRow['Subtotal'] = 0;
		foreach($customerMatrix as $orderID => $orderInfo) {
			$totalsRow['Subtotal'] += $orderInfo['Subtotal'];
		}
		$customerMatrix[] = $totalsRow;
		$this->customerMatrix = $customerMatrix;
	}

	public function write_html_attachment() {
		//Uses matrices to output html attachment
		
		$customerMatrix = $this->customerMatrix;
		$orderItemMatrixWithTotal = $this->orderItemMatrixWithTotal;
		$customerheaderlength = count(current($customerMatrix));
		$dottedColumn = ceil(count(current($customerMatrix)) + count(current($orderItemMatrixWithTotal)) / 2);
		//Adds style information to table
		$htmlTable = <<<HTML
<head><style media="all" type ="text/css"> 
@media print {tr {page-break-inside:avoid;} thead {display: table-header-group;}}
table {width:100%; border-collapse: collapse;font-size: 0.8rem;}
table,td, th {border: 1px solid black;}
tr {border: 2px solid black;}
td {padding: 0.8em;}
td:nth-child(n+$customerheaderlength) {text-align: center;}
thead {font-size: 1.5em;}
td:nth-child(n+$customerheaderlength){font-size: 1.3em;}
td:nth-child($dottedColumn){border-style:dashed;border-width:2px;}
tr:nth-child(odd) {background-color: #DCDCDC;}
tr:last-child {font-weight: bold;}
</style></head>
HTML;

		$htmlTable .= <<<HTML
		<table>
		  <thead>
		    <tr>
		      <th>
HTML;
		$htmlTable .= implode('</th><th>',array_keys(current($customerMatrix))).'</th><th>';
		$htmlTable .= implode('</th><th>',array_keys(current($orderItemMatrixWithTotal))) .'</th>'.PHP_EOL;
		$htmlTable .= <<<HTML
		    </tr>
		  </thead>
		  <tbody>
HTML;
		foreach($customerMatrix as $orderID => $customer) { 
			$htmlTable .= '<tr>'.PHP_EOL;
			$htmlTable .= '<td>'.implode('</td><td>', $customer).'</td><td>';
			$htmlTable .= implode('</td><td>', array_map(array($this, 'remove_zeros'),$orderItemMatrixWithTotal[$orderID])).'</td>'.PHP_EOL;
			$htmlTable .= '</tr>'.PHP_EOL;
		}
		$htmlTable .= <<<HTML
		  </tbody>
		</table>
HTML;


		$filename = 'orders_'.date('M-d-y',get_option('early_order_date')).'_'.date('M-d-y',get_option('last_order_date')).'.html';
		header("Content-Encoding: ".get_bloginfo('charset')."");
		header("Content-Type: text/html");
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		echo $htmlTable;
		exit();
	}

	public function remove_zeros($itemQuantity) {
		//Used for array_map
		//If item has been ordered 0 times, then leave table element empty
		if($itemQuantity === 0) {
			return '';
		} else {
			return $itemQuantity;
		}
	}
}