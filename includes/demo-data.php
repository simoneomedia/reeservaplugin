
<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function rsv_seed_demo_data(){
    $exists = get_posts(['post_type'=>'rsv_accomm','numberposts'=>1]);
    if($exists) return;
    $id = wp_insert_post(['post_type'=>'rsv_accomm','post_status'=>'publish','post_title'=>'Demo Apartment','post_content'=>'A cozy demo listing.']);
    update_post_meta($id,'rsv_max_guests',4);
}
