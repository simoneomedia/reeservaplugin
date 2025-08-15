
<?php
/**
 * Plugin Name: Reeserva Booking Suite
 * Description: Airbnb‑style booking system with a single “Accommodation” post type. Calendar price editor with periods & per‑period variations, multi‑step checkout with Stripe, iCal import/export, email notifications, and frontend admin shortcodes.
 * Version: 1.7.0
 * Author: simoneomedia
 * Text Domain: reeservaplugin
 * Update URI: https://github.com/simoneomedia/reeservaplugin
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define('RSV_VER','1.5.0');
define('RSV_PATH', plugin_dir_path(__FILE__));
define('RSV_URL',  plugin_dir_url(__FILE__));
//test
require_once RSV_PATH.'includes/helpers.php';
require_once RSV_PATH.'includes/cpt.php';
require_once RSV_PATH.'includes/admin-pages.php';
require_once RSV_PATH.'includes/ajax.php';
require_once RSV_PATH.'includes/emails.php';
require_once RSV_PATH.'includes/settings.php';
require_once RSV_PATH.'includes/shortcodes.php';
require_once RSV_PATH.'includes/frontend-admin.php';
require_once RSV_PATH.'includes/demo-data.php';
require_once RSV_PATH.'includes/updater.php'; // GitHub self-updater
require_once RSV_PATH.'includes/payments.php'; // Stripe
require_once RSV_PATH.'includes/ical.php';     // iCal sync

register_activation_hook(__FILE__, function(){
    RSV_register_cpts();
    // Create checkout page if missing
    $page_id = get_option('rsv_checkout_page_id');
    if ( ! $page_id || ! get_post($page_id) ) {
        $page_id = wp_insert_post([
            'post_title'   => 'Booking Checkout',
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => '[rsv_checkout]'
        ]);
        if ( ! is_wp_error($page_id) ) update_option('rsv_checkout_page_id', $page_id);
    }
    rsv_seed_demo_data();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(){ flush_rewrite_rules(); });

// Auto-inject the checkout on the configured page even if the shortcode is missing.
add_filter('the_content', function($content){
    if ( is_admin() ) return $content;
    $pid = (int) get_option('rsv_checkout_page_id');
    if ( $pid && is_page($pid) && strpos($content, '[rsv_checkout]') === false ) {
        $content .= do_shortcode('[rsv_checkout]');
    }
    return $content;
});

// Admin menu
add_action('admin_menu', function(){
    add_menu_page('Reeserva','Reeserva','manage_options','rsv_dashboard','rsv_render_calendar','dashicons-calendar-alt',58);
    add_submenu_page('rsv_dashboard', __('Calendar','reeserva'), __('Calendar','reeserva'), 'manage_options', 'rsv_dashboard','rsv_render_calendar');
    add_submenu_page('rsv_dashboard', __('Email & Payments','reeserva'), __('Email & Payments','reeserva'),'manage_options','rsv_settings','rsv_render_email_settings');
});

// Initialize GitHub updater
add_action('init', function(){
    if ( class_exists('Reeserva_GitHub_Updater') ) {
        new Reeserva_GitHub_Updater(__FILE__, [
            'owner'  => 'simoneomedia',
            'repo'   => 'reeservaplugin',
            'branch' => 'main',
            'channel'=> 'stable',
        ]);
    }
});
