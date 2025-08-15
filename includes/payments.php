
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action('wp_ajax_nopriv_rsv_stripe_checkout','rsv_stripe_checkout');
add_action('wp_ajax_rsv_stripe_checkout','rsv_stripe_checkout');
function rsv_stripe_checkout(){
    $p = rsv_get_payment_settings();
    if (empty($p['stripe_enabled'])) wp_send_json_error(['message'=>'Stripe disabled']);
    $accomm_id = intval($_POST['accomm'] ?? 0);
    $ci = sanitize_text_field($_POST['ci'] ?? '');
    $co = sanitize_text_field($_POST['co'] ?? '');
    $fname = sanitize_text_field($_POST['first_name'] ?? '');
    $lname = sanitize_text_field($_POST['last_name'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $guests = max(1, intval($_POST['guests'] ?? 1));
    $notes  = sanitize_text_field($_POST['notes'] ?? '');
    $name  = trim($fname.' '.$lname);
    $email = sanitize_email($_POST['email'] ?? '');
    if(!$accomm_id||!$ci||!$co||!$fname||!$lname||!$email||!$phone) wp_send_json_error(['message'=>'Missing fields']);

    $total = rsv_quote_total($accomm_id,$ci,$co,$guests);
    $amount = max(0, round($total*100)); // cents
    if ($amount < 50) $amount = 50;

    $success = add_query_arg([
        'rsv_stripe'=>'return','session_id'=>'{CHECKOUT_SESSION_ID}',
        'accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,
        'first_name'=>rawurlencode($fname),'last_name'=>rawurlencode($lname),
        'email'=>rawurlencode($email),'phone'=>rawurlencode($phone),'guests'=>$guests,
        'notes'=>rawurlencode($notes)
    ], rsv_checkout_url());
    $cancel  = add_query_arg(['accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,'guests'=>$guests], rsv_checkout_url());

    $body = [
        'mode' => 'payment',
        'success_url' => $success,
        'cancel_url'  => $cancel,
        'customer_email' => $email,
        'line_items' => [[
            'quantity' => 1,
            'price_data' => [
                'currency' => $p['currency'],
                'unit_amount' => $amount,
                'product_data' => [
                    'name' => sprintf('%s (%s → %s)', get_the_title($accomm_id), $ci, $co),
                ]
            ],
        ]],
        'metadata' => [
            'accomm_id'=>$accomm_id,'ci'=>$ci,'co'=>$co,
            'guest_first_name'=>$fname,'guest_last_name'=>$lname,
            'guest_phone'=>$phone,'guest_email'=>$email,'guests'=>$guests,
            'notes'=>$notes,
        ]
    ];
    $headers = [
        'Authorization' => 'Bearer '.$p['stripe_sk'],
        'Content-Type'  => 'application/x-www-form-urlencoded'
    ];
    $resp = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
        'headers'=>$headers,'body'=>http_build_query($body),'timeout'=>20
    ]);
    if (is_wp_error($resp)) wp_send_json_error(['message'=>$resp->get_error_message()]);
    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code>=200 && $code<300 && !empty($json['url'])){
        wp_send_json_success(['url'=>$json['url']]);
    }
    wp_send_json_error(['message'=> 'Stripe error', 'details'=>$json]);
}

function rsv_stripe_retrieve_session($session_id){
    $p = rsv_get_payment_settings();
    if (empty($p['stripe_sk'])) return null;
    $resp = wp_remote_get('https://api.stripe.com/v1/checkout/sessions/'.urlencode($session_id), [
        'headers'=>['Authorization'=>'Bearer '.$p['stripe_sk']],
        'timeout'=>15
    ]);
    if (is_wp_error($resp)) return null;
    $code = wp_remote_retrieve_response_code($resp);
    if ($code<200 || $code>=300) return null;
    return json_decode(wp_remote_retrieve_body($resp), true);
}
