<?php

if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly
}

require_once('class-create-order-table.php');
require_once('class-create-email-list.php');

class EO_Export_Orders_Admin {
	
	public function init() {
		$this->add_menus_settings();
		$this->add_hooks();

	}
	private function add_menus_settings() {
		add_action('admin_menu', array($this, 'add_main_menu_page'));
		add_action('admin_init', array($this, 'add_settings'));
	}
	private function add_hooks() {
		add_action('admin_init', array($this, 'process_create_order_table'));
		add_action('admin_init', array($this, 'create_email_list'));
		add_action('admin_enqueue_scripts', array($this, 'export_click'));
	}
	public function add_main_menu_page() {
		add_menu_page(
			'Export Orders',
			'Export Orders',
			'publish_pages',
			'export_orders',
			array($this, 'export_orders_html')
		);

	}
	public function add_settings() {
       		add_settings_section(
			'export_orders_section',
			'Settings',
			array($this, 'settings_section_callback_function'),
			'export_orders'
       		);
		add_settings_field(
			'early_order_date',
			'Earliest Order Date',
			array($this, 'early_order_date_callback'),
			'export_orders',
			'export_orders_section'
		);
		add_settings_field(
			'last_order_date',
			'Latest Order Date',
			array($this, 'last_order_date_callback'),
			'export_orders',
			'export_orders_section'
		);
		add_settings_field(
			'order_combo',
			'Order Combo (i.e. Entree, Paleo) (Optional)',
			array($this, 'order_combo_callback'),
			'export_orders',
			'export_orders_section'
		);
		add_settings_field(
			'add_size_to_tag',
			'Add Sizes to Tag (Optional)',
			array($this, 'add_size_to_tag_callback'),
			'export_orders',
			'export_orders_section'
		);
		add_settings_field(
			'modify_tags',
			'Modify Tags (Optional)',
			array($this, 'modify_tags_callback'),
			'export_orders',
			'export_orders_section'
	        );
		register_setting('export_orders','early_order_date','strtotime');
		register_setting('export_orders','last_order_date','strtotime');
		register_setting('export_orders','order_combo',array($this,'convert_json_to_array'));
		register_setting('export_orders','add_size_to_tag',array($this,'convert_json_to_array'));
		register_setting('export_orders','modify_tags',array($this,'convert_json_to_array'));
	}
	public function convert_json_to_array($jsonstring) {
		return json_decode($jsonstring,true);
	}
	public function settings_section_callback_function() {
		echo '<p>WPPizza orders to display in order table</p>';
	}


	public function early_order_date_callback() {
		if(get_option('early_order_date') === false) {
			$htmlValue = date('Y-m-d\TH:i:s', strtotime('-5 days'));
		} else {
			$htmlValue = date('Y-m-d\TH:i:s', get_option('early_order_date'));
		}
		echo '<input name="early_order_date" id="early_order_date" type="datetime-local" value="'.$htmlValue.'">';
	}

	public function last_order_date_callback() {
		if(get_option('last_order_date') === false) {
			$htmlValue = date('Y-m-d\TH:i:s', strtotime('now'));
		} else {
			$htmlValue = date('Y-m-d\TH:i:s', get_option('last_order_date'));
		}
		echo '<input name="last_order_date" id="last_order_date" type="datetime-local" value="'.$htmlValue.'">';
	}

	public function order_combo_callback() {
		if(get_option('order_combo') === false) {
			$htmlValue = '';	
		} else {
			$htmlValue = json_encode(get_option('order_combo'));
		}
		echo '<input name="order_combo" id="order_combo" type="text" value="'.htmlspecialchars($htmlValue).'" /> ex. - {"Entree":"Rice-1,Dal-1"}';
	}

	public function add_size_to_tag_callback() {
		if(get_option('add_size_to_tag') === false) {
			$htmlValue = '';	
		} else {
			$htmlValue = json_encode(get_option('add_size_to_tag'));
		}
		echo '<input name="add_size_to_tag" id="add_size_to_tag" type="text" value="'.htmlspecialchars($htmlValue).'" /> ex. - {"12":"12","16":"16"}';
	}

	public function modify_tags_callback() {
		if(get_option('modify_tags') === false) {
			$htmlValue = '';	
		} else {
			$htmlValue = json_encode(get_option('modify_tags'));
		}
		echo '<input name="modify_tags" id="modify_tags" type="text" value="'.htmlspecialchars($htmlValue).'" /> ex. - {"Basmati Rice":"Rice","Mango Lassi":"Lassi"}';
	}
	
	public function export_orders_html() {
		?>
		<div class="wrap">
			<h1><?= esc_html(get_admin_page_title()); ?></h1> 
			<form method="post" action="options.php">
				<?php
				settings_fields('export_orders');
				do_settings_sections('export_orders');
				submit_button();
				?>
			</form>
			<input type="button" class="button" value="<?php echo 'Download Table' ?>" id="table_export" />
			<input type="button" class="button" value="<?php echo 'Download Email List' ?>" id="email_export" />
		</div><?php
	}

	//When table export button clicked, write html file containing orders
	public function process_create_order_table() {
		if(isset($_GET['page']) && $_GET['page']=='export_orders' && isset($_GET['table_export'])){
			$orderTable = new EO_Create_Order_Table();
			$orderTable->main_process();
			$orderTable->write_html_attachment();
		}
	}

	//When email export button clicked, write txt file containing email addresses
	public function create_email_list() {
		if(isset($_GET['page']) && $_GET['page']=='export_orders' && isset($_GET['email_export'])){
			$basicProcess = new EO_Create_Order_Table();
			$orderArray = $basicProcess->basic_process_for_emails();
			$emailList = new EO_Create_Email_List($orderArray);
			$emailList->write_txt_attachment();
		}
	}

	public function export_click() {
		wp_enqueue_script('click_export',plugin_dir_url(dirname( __FILE__)).'js/export.js',array('jquery'));
	}
}