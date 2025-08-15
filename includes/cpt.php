
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function RSV_register_cpts(){
    register_post_type('rsv_accomm',[
        'label'=>__('Accommodation','reeserva'),
        'labels'=>['name'=>__('Accommodations','reeserva'),'singular_name'=>__('Accommodation','reeserva')],
        'public'=>true,'has_archive'=>true,'menu_icon'=>'dashicons-building','supports'=>['title','editor','thumbnail'],
    ]);
    register_post_type('rsv_booking',[
        'label'=>__('Booking','reeserva'),
        'labels'=>['name'=>__('Bookings','reeserva'),'singular_name'=>__('Booking','reeserva')],
        'public'=>false,'show_ui'=>true,'menu_icon'=>'dashicons-tickets','supports'=>['title','editor'],
    ]);
    register_post_status('confirmed', [
        'label'                     => _x('Confirmed', 'post status', 'reeserva'),
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Confirmed <span class="count">(%s)</span>', 'Confirmed <span class="count">(%s)</span>', 'reeserva')
    ]);
    register_post_status('pending', [
        'label'                     => _x('Pending', 'post status', 'reeserva'),
        'public'                    => true,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'reeserva')
    ]);
    register_post_type('rsv_season',[
        'label'=>__('Season','reeserva'),
        'public'=>false,'show_ui'=>false,'supports'=>['title'],
    ]);
    register_post_type('rsv_rate',[
        'label'=>__('Rate','reeserva'),
        'public'=>false,'show_ui'=>false,'supports'=>['title'],
    ]);
}
add_action('init','RSV_register_cpts');

// Meta box for Accommodation
add_action('add_meta_boxes', function(){
    add_meta_box('rsv_accomm_meta', __('Accommodation details','reeserva'),'rsv_render_accomm_meta','rsv_accomm','normal','high');
});
function rsv_render_accomm_meta($post){
    $gallery = (array) get_post_meta($post->ID,'rsv_gallery',true) ?: [];
    $max_guests = (int) get_post_meta($post->ID,'rsv_max_guests',true);
    $amenities = (array) get_post_meta($post->ID,'rsv_amenities',true) ?: [];
    $checkin = esc_attr( get_post_meta($post->ID,'rsv_checkin',true) );
    $checkout= esc_attr( get_post_meta($post->ID,'rsv_checkout',true) );
    ?>
    <style>.ehb-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.ehb-card{background:#fff;border:1px solid #e3e3e3;border-radius:10px;padding:16px}</style>
    <div class="ehb-grid">
      <div class="ehb-card">
        <h3><?php esc_html_e('Basics','reeserva');?></h3>
        <p><label><?php esc_html_e('Max guests','reeserva');?> <input type="number" name="rsv_max_guests" value="<?php echo esc_attr($max_guests);?>" min="1"></label></p>
        <p><label><?php esc_html_e('Check-in time','reeserva');?> <input type="time" name="rsv_checkin" value="<?php echo $checkin;?>"></label></p>
        <p><label><?php esc_html_e('Check-out time','reeserva');?> <input type="time" name="rsv_checkout" value="<?php echo $checkout;?>"></label></p>
      </div>
      <div class="ehb-card">
        <h3><?php esc_html_e('Amenities','reeserva');?></h3>
        <?php foreach(rsv_default_amenities() as $k=>$label): ?>
          <label style="display:inline-block;margin-right:12px"><input type="checkbox" name="rsv_amenities[]" value="<?php echo esc_attr($k);?>" <?php checked(in_array($k,$amenities));?>> <?php echo esc_html($label);?></label>
        <?php endforeach; ?>
      </div>
      <div class="ehb-card">
        <h3><?php esc_html_e('Gallery','reeserva');?></h3>
        <p class="description"><?php esc_html_e('Use the Featured Image for the main photo. Paste additional image URLs (one per line) for the gallery.','reeserva');?></p>
        <textarea name="rsv_gallery" rows="4" style="width:100%"><?php echo esc_textarea(implode("\n",$gallery));?></textarea>
      </div>
      <div class="ehb-card">
        <h3><?php esc_html_e('iCal Sync','reeserva');?></h3>
          <p class="desc"><?php esc_html_e('Paste external calendar URLs (one per line).','reeserva');?></p>
          <textarea name="rsv_ical_sources" rows="3" placeholder="https://calendar.airbnb.com/ical/....ics"><?php echo esc_textarea( implode("\n",(array) get_post_meta($post->ID,'rsv_ical_sources',true) ?: [] ) ); ?></textarea>
          <p class="desc"><?php printf( esc_html__('Export feed URL: %s','reeserva'), esc_url( add_query_arg(['rsv_ics'=>$post->ID,'key'=>get_option('rsv_ics_key') ?: 'set-after-save'], home_url('/') ) ) ); ?></p>
      </div>
    </div>
    <?php
}
add_action('save_post_rsv_accomm', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    update_post_meta($post_id,'rsv_max_guests', intval($_POST['rsv_max_guests'] ?? 0));
    update_post_meta($post_id,'rsv_checkin', sanitize_text_field($_POST['rsv_checkin'] ?? ''));
    update_post_meta($post_id,'rsv_checkout', sanitize_text_field($_POST['rsv_checkout'] ?? ''));
    $amen = array_map('sanitize_text_field', (array) ($_POST['rsv_amenities'] ?? []));
    update_post_meta($post_id,'rsv_amenities',$amen);
    if(isset($_POST['rsv_gallery'])){
        $lines = array_filter(array_map('trim', explode("\n", wp_kses_post($_POST['rsv_gallery']) )));
        update_post_meta($post_id,'rsv_gallery',$lines);
    }
    if(isset($_POST['rsv_ical_sources'])){
        $lines = array_filter(array_map('trim', explode("\n", wp_kses_post($_POST['rsv_ical_sources']) )));
        update_post_meta($post_id,'rsv_ical_sources',$lines);
    }
});

// Booking details meta box
add_action('add_meta_boxes', function(){
    add_meta_box('rsv_booking_meta', __('Booking details','reeserva'),'rsv_render_booking_meta','rsv_booking','normal','high');
});

function rsv_render_booking_meta($post){
    $name = get_post_meta($post->ID,'rsv_guest_name',true);
    $email= get_post_meta($post->ID,'rsv_guest_email',true);
    $phone= get_post_meta($post->ID,'rsv_guest_phone',true);
    $ci   = get_post_meta($post->ID,'rsv_check_in',true);
    $co   = get_post_meta($post->ID,'rsv_check_out',true);
    $gu   = get_post_meta($post->ID,'rsv_total_guests',true);
    $price= get_post_meta($post->ID,'rsv_price_paid',true);
    if($price==='') $price = get_post_meta($post->ID,'rsv_booking_total',true);
    $method = get_post_meta($post->ID,'rsv_payment_method',true);
    $notes = get_post_meta($post->ID,'rsv_booking_notes',true);
    $session = get_post_meta($post->ID,'rsv_stripe_session',true);
    $intent  = get_post_meta($post->ID,'rsv_stripe_payment_intent',true);
    ?>
    <table class="form-table">
      <tbody>
        <tr><th><?php esc_html_e('Guest','reeserva');?></th><td><?php echo esc_html($name);?></td></tr>
        <tr><th><?php esc_html_e('Email','reeserva');?></th><td><?php echo esc_html($email);?></td></tr>
        <tr><th><?php esc_html_e('Phone','reeserva');?></th><td><?php echo esc_html($phone);?></td></tr>
        <tr><th><?php esc_html_e('Check-in','reeserva');?></th><td><?php echo esc_html($ci);?></td></tr>
        <tr><th><?php esc_html_e('Check-out','reeserva');?></th><td><?php echo esc_html($co);?></td></tr>
        <tr><th><?php esc_html_e('Guests','reeserva');?></th><td><?php echo intval($gu);?></td></tr>
        <tr><th><?php esc_html_e('Price paid','reeserva');?></th><td><?php echo esc_html($price);?></td></tr>
        <tr><th><?php esc_html_e('Payment method','reeserva');?></th><td><?php echo esc_html($method);?></td></tr>
        <?php if($method==='stripe'): ?>
          <tr><th><?php esc_html_e('Stripe session','reeserva');?></th><td><?php echo esc_html($session);?></td></tr>
          <?php if($intent): ?><tr><th><?php esc_html_e('Payment intent','reeserva');?></th><td><?php echo esc_html($intent);?></td></tr><?php endif; ?>
        <?php endif; ?>
        <tr><th><?php esc_html_e('Notes','reeserva');?></th><td><?php echo nl2br(esc_html($notes));?></td></tr>
      </tbody>
    </table>
    <?php
}
