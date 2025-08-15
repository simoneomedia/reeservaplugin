
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function rsv_default_email_settings(){
    return [
        'from_name'        => get_bloginfo('name'),
        'from_email'       => get_option('admin_email'),
        'guest_enabled'    => 1,
        'admin_enabled'    => 1,
        'guest_subject'    => __('Booking confirmation','reeserva'),
        'guest_body'       => __('Hi {guest_name}, your booking at {accommodation} is confirmed for {check_in} → {check_out}.','reeserva'),
        'admin_subject'    => __('New booking','reeserva'),
        'admin_body'       => __('New booking for {accommodation}: {guest_name}, {check_in} → {check_out}.','reeserva'),
        'footer'           => '',
    ];
}
function rsv_get_email_settings(){
    $saved = get_option('rsv_email_settings', []);
    return wp_parse_args($saved, rsv_default_email_settings());
}
function rsv_get_payment_settings(){
    $defaults = [
        'stripe_enabled' => 0,
        'currency'       => 'eur',
        'stripe_pk'      => '',
        'stripe_sk'      => '',
        'test_mode'      => 1,
    ];
    $s = get_option('rsv_payment_settings', []);
    return wp_parse_args($s, $defaults);
}

function rsv_render_email_settings(){
    if (!current_user_can('manage_options')) return;
    $s = rsv_get_email_settings();
    if (isset($_POST['rsv_email_save'])){
        check_admin_referer('rsv_email_settings');
        $s['from_name']     = sanitize_text_field($_POST['from_name'] ?? '');
        $s['from_email']    = sanitize_email($_POST['from_email'] ?? '');
        $s['guest_enabled'] = isset($_POST['guest_enabled']) ? 1 : 0;
        $s['admin_enabled'] = isset($_POST['admin_enabled']) ? 1 : 0;
        $s['guest_subject'] = sanitize_text_field($_POST['guest_subject'] ?? '');
        $s['guest_body']    = wp_kses_post($_POST['guest_body'] ?? '');
        $s['admin_subject'] = sanitize_text_field($_POST['admin_subject'] ?? '');
        $s['admin_body']    = wp_kses_post($_POST['admin_body'] ?? '');
        $s['footer']        = wp_kses_post($_POST['footer'] ?? '');
        update_option('rsv_email_settings',$s);
        echo '<div class="updated"><p>'.esc_html__('Email settings saved.','reeserva').'</p></div>';
    }

    $p = rsv_get_payment_settings();
    if (isset($_POST['rsv_payment_save'])){
        check_admin_referer('rsv_payment_settings');
        $p['stripe_enabled'] = isset($_POST['stripe_enabled']) ? 1 : 0;
        $p['currency']       = sanitize_text_field($_POST['currency'] ?? 'eur');
        $p['stripe_pk']      = sanitize_text_field($_POST['stripe_pk'] ?? '');
        $p['stripe_sk']      = sanitize_text_field($_POST['stripe_sk'] ?? '');
        $p['test_mode']      = isset($_POST['test_mode']) ? 1 : 0;
        update_option('rsv_payment_settings',$p);
        echo '<div class="updated"><p>'.esc_html__('Payment settings saved.','reeserva').'</p></div>';
    }

    ?>
    <div class="wrap"><h1><?php esc_html_e('Email & Payments','reeserva');?></h1>
      <h2 class="nav-tab-wrapper">
        <a href="#emails" class="nav-tab nav-tab-active" onclick="rsvTab(event,'emails')"><?php esc_html_e('Emails','reeserva');?></a>
        <a href="#payments" class="nav-tab" onclick="rsvTab(event,'payments')"><?php esc_html_e('Payments','reeserva');?></a>
      </h2>

      <div id="tab-emails">
        <form method="post">
          <?php wp_nonce_field('rsv_email_settings'); ?>
          <h2><?php esc_html_e('General','reeserva');?></h2>
          <table class="form-table"><tbody>
            <tr><th><?php esc_html_e('From name','reeserva');?></th><td><input type="text" name="from_name" value="<?php echo esc_attr($s['from_name']);?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('From email','reeserva');?></th><td><input type="email" name="from_email" value="<?php echo esc_attr($s['from_email']);?>" class="regular-text"></td></tr>
          </tbody></table>
          <h2><?php esc_html_e('Guest email','reeserva');?></h2>
          <p><label><input type="checkbox" name="guest_enabled" <?php checked($s['guest_enabled']);?>> <?php esc_html_e('Enable guest confirmation email','reeserva');?></label></p>
          <table class="form-table"><tbody>
            <tr><th><?php esc_html_e('Subject','reeserva');?></th><td><input type="text" name="guest_subject" value="<?php echo esc_attr($s['guest_subject']);?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('Body','reeserva');?></th><td><textarea name="guest_body" rows="6" class="large-text code"><?php echo esc_textarea($s['guest_body']);?></textarea></td></tr>
          </tbody></table>
          <h2><?php esc_html_e('Admin email','reeserva');?></h2>
          <p><label><input type="checkbox" name="admin_enabled" <?php checked($s['admin_enabled']);?>> <?php esc_html_e('Enable admin notification email','reeserva');?></label></p>
          <table class="form-table"><tbody>
            <tr><th><?php esc_html_e('Subject','reeserva');?></th><td><input type="text" name="admin_subject" value="<?php echo esc_attr($s['admin_subject']);?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('Body','reeserva');?></th><td><textarea name="admin_body" rows="6" class="large-text code"><?php echo esc_textarea($s['admin_body']);?></textarea></td></tr>
          </tbody></table>
          <h2><?php esc_html_e('Footer','reeserva');?></h2>
          <p><textarea name="footer" rows="3" class="large-text code"><?php echo esc_textarea($s['footer']);?></textarea></p>
          <p><button type="submit" name="rsv_email_save" class="button button-primary"><?php esc_html_e('Save email settings','reeserva');?></button></p>
        </form>
      </div>

      <div id="tab-payments" style="display:none">
        <form method="post">
          <?php wp_nonce_field('rsv_payment_settings'); ?>
          <h2><?php esc_html_e('Stripe','reeserva');?></h2>
          <p><label><input type="checkbox" name="stripe_enabled" <?php checked($p['stripe_enabled']);?>> <?php esc_html_e('Enable Stripe payments','reeserva');?></label></p>
          <table class="form-table"><tbody>
            <tr><th><?php esc_html_e('Currency','reeserva');?></th><td>
              <select name="currency">
                <?php foreach(['eur'=>'EUR','usd'=>'USD','gbp'=>'GBP'] as $k=>$v): ?>
                  <option value="<?php echo esc_attr($k);?>" <?php selected($p['currency'],$k);?>><?php echo esc_html($v);?></option>
                <?php endforeach; ?>
              </select>
            </td></tr>
            <tr><th><?php esc_html_e('Publishable key','reeserva');?></th><td><input type="text" name="stripe_pk" value="<?php echo esc_attr($p['stripe_pk']);?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('Secret key','reeserva');?></th><td><input type="text" name="stripe_sk" value="<?php echo esc_attr($p['stripe_sk']);?>" class="regular-text"></td></tr>
            <tr><th><?php esc_html_e('Test mode','reeserva');?></th><td><label><input type="checkbox" name="test_mode" <?php checked($p['test_mode']);?>> <?php esc_html_e('Use test keys','reeserva');?></label></td></tr>
          </tbody></table>
          <p><button type="submit" name="rsv_payment_save" class="button button-primary"><?php esc_html_e('Save payment settings','reeserva');?></button></p>
          <p class="description"><?php esc_html_e('Tip: Create a Release tag (v1.x) on GitHub to ship updates.','reeserva');?></p>
        </form>
      </div>
    </div>
    <script>
    function rsvTab(e,id){e.preventDefault();document.querySelectorAll('.nav-tab').forEach(t=>t.classList.remove('nav-tab-active'));
      e.target.classList.add('nav-tab-active');document.getElementById('tab-emails').style.display=id==='emails'?'block':'none';
      document.getElementById('tab-payments').style.display=id==='payments'?'block':'none';}
    </script>
    <?php
}
