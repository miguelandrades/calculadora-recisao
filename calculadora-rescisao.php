<?php
/**
 * Plugin Name: Calculadora de Rescisão Trabalhista
 * Description: Simulador de cálculo de rescisão CLT
 * Version: 1.0.0
 * Author: Miguel Andrade
 */

if (!defined('ABSPATH')) exit;

define('CR_PATH', plugin_dir_path(__FILE__));
define('CR_URL', plugin_dir_url(__FILE__));
define('CR_VERSION', '1.0.0');

require_once CR_PATH . 'includes/class-loader.php';

function cr_init(){
    CR_Loader::init();
}
add_action('plugins_loaded', 'cr_init');