
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('rsv_booking_confirmed', function($booking_id, $data){
    $s = rsv_get_email_settings();
    $headers = ['From: '.sanitize_text_field($s['from_name']).' <'.sanitize_email($s['from_email']).'>'];
    $ac = get_the_title($data['accomm'] ?? get_post_meta($booking_id,'rsv_booking_accomm',true));
    $repl = [
        '{guest_name}'   => sanitize_text_field($data['name'] ?? get_post_meta($booking_id,'rsv_guest_name',true)),
        '{accommodation}'=> $ac,
        '{check_in}'     => sanitize_text_field($data['ci'] ?? get_post_meta($booking_id,'rsv_check_in',true)),
        '{check_out}'    => sanitize_text_field($data['co'] ?? get_post_meta($booking_id,'rsv_check_out',true)),
        '{booking_id}'   => $booking_id,
    ];
    if(!empty($s['guest_enabled'])){
        $to = sanitize_email($data['email'] ?? get_post_meta($booking_id,'rsv_guest_email',true));
        $sub = strtr($s['guest_subject'],$repl);
        $body= wpautop( strtr($s['guest_body'],$repl) . "\n\n".$s['footer'] );
        wp_mail($to,$sub,$body,$headers);
    }
    if(!empty($s['admin_enabled'])){
        $to = get_option('admin_email');
        $sub = strtr($s['admin_subject'],$repl);
        $body= wpautop( strtr($s['admin_body'],$repl) . "\n\n".$s['footer'] );
        wp_mail($to,$sub,$body,$headers);
    }
}, 10, 2);
