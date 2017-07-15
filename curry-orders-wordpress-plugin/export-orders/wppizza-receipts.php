<?php
/*
Plugin Name: Export Orders
Description:  Export WPPizza Order Table
Author: mateosullivan
Version: 1.0
Stable tag: 1.0
*/

if ( !defined( 'ABSPATH' ) )
        exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'classes/class-export-orders-admin.php';

$exportOrder = new EO_Export_Orders_Admin();

$exportOrder->init();