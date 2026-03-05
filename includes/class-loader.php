<?php

if (!defined('ABSPATH')) exit;

class CR_Loader {

    public static function init(){

        require_once CR_PATH . 'includes/class-response.php';
        require_once CR_PATH . 'includes/class-calculadora.php';
        require_once CR_PATH . 'public/class-shortcode.php';
        require_once CR_PATH . 'public/class-ajax.php';

        new CR_Shortcode();
        new CR_Ajax();
    }
}