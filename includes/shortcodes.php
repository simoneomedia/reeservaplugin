
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode('rsv_search', function(){
    $types = get_posts(['post_type'=>'rsv_accomm','post_status'=>'publish','numberposts'=>-1]);
    $action = esc_url( rsv_checkout_url() );
    ob_start(); ?>
    <form class="ehb-search-form" method="get" action="<?php echo $action; ?>" style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0">
      <input type="hidden" name="step" value="1">
      <label><?php esc_html_e('Accommodation','reeserva'); ?>
        <select name="accomm" required>
          <?php foreach($types as $t): ?><option value="<?php echo esc_attr($t->ID); ?>"><?php echo esc_html(get_the_title($t)); ?></option><?php endforeach; ?>
        </select>
      </label>
      <label><?php esc_html_e('Check-in','reeserva'); ?> <input type="date" name="ci" required></label>
      <label><?php esc_html_e('Check-out','reeserva'); ?> <input type="date" name="co" required></label>
      <button type="submit"><?php esc_html_e('Search','reeserva'); ?></button>
    </form>
    <?php return ob_get_clean();
});

add_shortcode('rsv_checkout', function(){
    wp_enqueue_style('rsv-checkout', RSV_URL.'assets/css/checkout.css', [], RSV_VER);
    $step = max(1, intval($_GET['step'] ?? ($_POST['step'] ?? 1)));
    $accomm_id = intval($_GET['accomm'] ?? ($_POST['accomm'] ?? 0));
    $ci = sanitize_text_field($_GET['ci'] ?? ($_POST['ci'] ?? ''));
    $co = sanitize_text_field($_GET['co'] ?? ($_POST['co'] ?? ''));

    // Handle Stripe return
    if (isset($_GET['rsv_stripe']) && $_GET['rsv_stripe']==='return' && !empty($_GET['session_id'])){
        $session = rsv_stripe_retrieve_session(sanitize_text_field($_GET['session_id']));
        echo '<div class="ehb-wizard"><div class="steps"><div class="step active">1</div><div class="line active"></div><div class="step active">2</div><div class="line active"></div><div class="step active">3</div></div>';
        echo '<div class="card">';
        if($session && ($session['payment_status'] ?? '') === 'paid'){
            $accomm_id = intval($_GET['accomm'] ?? 0);
            $ci = sanitize_text_field($_GET['ci'] ?? '');
            $co = sanitize_text_field($_GET['co'] ?? '');
            $name = sanitize_text_field($_GET['name'] ?? '');
            $email = sanitize_email($_GET['email'] ?? '');
            $notes='';
            // Create booking if not exists with same session id
            $exists = get_posts(['post_type'=>'rsv_booking','post_status'=>['confirmed','publish'],'numberposts'=>1,
                'meta_query'=>[['key'=>'rsv_stripe_session','value'=>sanitize_text_field($_GET['session_id']),'compare'=>'=']]]);
            if(!$exists){
                $bid = wp_insert_post(['post_type'=>'rsv_booking','post_status'=>'confirmed','post_title'=>sprintf(__('Booking: %s','reeserva'), $name),'post_content'=>$notes]);
                if(!is_wp_error($bid) && $bid){
                    update_post_meta($bid,'rsv_booking_accomm',$accomm_id);
                    update_post_meta($bid,'rsv_check_in',$ci);
                    update_post_meta($bid,'rsv_check_out',$co);
                    update_post_meta($bid,'rsv_guest_name',$name);
                    update_post_meta($bid,'rsv_guest_email',$email);
                    update_post_meta($bid,'rsv_payment_status','paid');
                    update_post_meta($bid,'rsv_stripe_session',sanitize_text_field($_GET['session_id']));
                    do_action('rsv_booking_confirmed', $bid, ['accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,'name'=>$name,'email'=>$email]);
                    echo '<div class="confirm"><div class="badge">✔</div><h3>'.esc_html__('Booking confirmed','reeserva').'</h3>';
                    echo '<p><strong>'.esc_html__('Reference','reeserva').':</strong> '.intval($bid).'</p>';
                    echo '<a class="btn-secondary" href="'.esc_url( get_permalink($accomm_id) ).'">'.esc_html__('Back to listing','reeserva').'</a></div>';
                } else {
                    echo '<p class="error">'.esc_html__('Could not create booking, but payment succeeded. Please contact support.','reeserva').'</p>';
                }
            } else {
                $b=$exists[0];
                echo '<div class="confirm"><div class="badge">✔</div><h3>'.esc_html__('Booking confirmed','reeserva').'</h3>';
                echo '<p><strong>'.esc_html__('Reference','reeserva').':</strong> '.intval($b->ID).'</p>';
                echo '<a class="btn-secondary" href="'.esc_url( get_permalink($accomm_id) ).'">'.esc_html__('Back to listing','reeserva').'</a></div>';
            }
        } else {
            echo '<p class="error">'.esc_html__('Payment not verified. If you were charged, contact support.','reeserva').'</p>';
        }
        echo '</div></div>';
        return '';
    }

    $is_available = function($accomm,$a,$b){
        $bookings = get_posts(['post_type'=>'rsv_booking','numberposts'=>-1,'post_status'=>['publish','confirmed','pending'],
            'meta_query'=>[['key'=>'rsv_booking_accomm','value'=>$accomm,'compare'=>'=']] ]);
        foreach($bookings as $bk){
            $bci=get_post_meta($bk->ID,'rsv_check_in',true);
            $bco=get_post_meta($bk->ID,'rsv_check_out',true);
            if(rsv_date_range_overlaps($a,$b,$bci,$bco)) return false;
        }
        return true;
    };

    ob_start();
    echo '<div class="ehb-wizard">';
    echo '<div class="steps"><div class="step '.($step>=1?'active':'').'">1</div><div class="line '.($step>=2?'active':'').'"></div><div class="step '.($step>=2?'active':'').'">2</div><div class="line '.($step>=3?'active':'').'"></div><div class="step '.($step>=3?'active':'').'">3</div></div>';

    if ($step === 1) {
        echo '<div class="card"><h2>'.esc_html__('Your stay','reeserva').'</h2>';
        $types = get_posts(['post_type'=>'rsv_accomm','post_status'=>'publish','numberposts'=>-1]);
        echo '<form method="get" class="form-grid">';
        echo '<input type="hidden" name="step" value="2">';
        echo '<label>'.esc_html__('Accommodation','reeserva').'<select name="accomm" required>';
        foreach($types as $t){ printf('<option value="%d" %s>%s</option>', $t->ID, selected($accomm_id,$t->ID,false), esc_html(get_the_title($t))); }
        echo '</select></label>';
        echo '<label>'.esc_html__('Check-in','reeserva').'<input type="date" name="ci" value="'.esc_attr($ci).'" required></label>';
        echo '<label>'.esc_html__('Check-out','reeserva').'<input type="date" name="co" value="'.esc_attr($co).'" required></label>';
        echo '<button class="btn-primary" type="submit">'.esc_html__('Continue','reeserva').'</button></form></div></div>';
        return ob_get_clean();
    }

    if (!$accomm_id || !$ci || !$co) { echo '<div class="card"><p>'.esc_html__('Missing data. Please start again.','reeserva').'</p></div></div>'; return ob_get_clean(); }

    if ($step === 2) {
        echo '<div class="card"><h2>'.esc_html__('Guest details','reeserva').'</h2>';
        echo '<div class="summary">'.esc_html(get_the_title($accomm_id)).' • '.esc_html($ci).' → '.esc_html($co).'</div>';
        if (!$is_available($accomm_id,$ci,$co)) { echo '<p class="error">'.esc_html__('Sorry, these dates are no longer available.','reeserva').'</p></div></div>'; return ob_get_clean(); }
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="step" value="3"><input type="hidden" name="accomm" value="'.esc_attr($accomm_id).'"><input type="hidden" name="ci" value="'.esc_attr($ci).'"><input type="hidden" name="co" value="'.esc_attr($co).'">';
        echo '<label>'.esc_html__('Full name','reeserva').'<input type="text" name="name" required></label>';
        echo '<label>'.esc_html__('Email','reeserva').'<input type="email" name="email" required></label>';
        echo '<label>'.esc_html__('Notes (optional)','reeserva').'<textarea name="notes" rows="3"></textarea></label>';
        echo '<button class="btn-primary" type="submit">'.esc_html__('Review & pay','reeserva').'</button></form></div></div>';
        return ob_get_clean();
    }

    if ($step === 3) {
        $name  = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        echo '<div class="card"><h2>'.esc_html__('Review & payment','reeserva').'</h2>';
        echo '<div class="summary">'.esc_html(get_the_title($accomm_id)).' • '.esc_html($ci).' → '.esc_html($co).'</div>';
        $total = rsv_quote_total($accomm_id,$ci,$co);
        echo '<ul class="review"><li><strong>'.esc_html__('Guest','reeserva').':</strong> '.esc_html($name).'</li><li><strong>'.esc_html__('Email','reeserva').':</strong> '.esc_html($email).'</li><li><strong>'.esc_html__('Total','reeserva').':</strong> €'.esc_html(number_format($total,2)).'</li></ul>';

        $p = rsv_get_payment_settings();
        if ($p['stripe_enabled']){
            // Stripe flow
            echo '<button id="rsv-pay" class="btn-primary">'.esc_html__('Pay with card','reeserva').'</button>';
            echo '<script src="https://js.stripe.com/v3/"></script>';
            echo '<script>
            document.getElementById("rsv-pay").addEventListener("click", function(){
                var fd=new FormData(); fd.append("action","rsv_stripe_checkout");
                fd.append("accomm","'.esc_js($accomm_id).'"); fd.append("ci","'.esc_js($ci).'"); fd.append("co","'.esc_js($co).'");
                fd.append("name","'.esc_js($name).'"); fd.append("email","'.esc_js($email).'");
                fetch("'.esc_js(admin_url('admin-ajax.php')).'", {method:"POST", body:fd, credentials:"same-origin"})
                .then(r=>r.json()).then(function(res){
                    if(res && res.success && res.data && res.data.url){ window.location = res.data.url; }
                    else{ alert("Stripe error: "+(res && (res.data && res.data.message || res.message) || "unknown")); }
                }).catch(function(){ alert("Network error"); });
            });
            </script>';
            echo '<p style="margin-top:8px"><a class="btn-secondary" href="'.esc_url( add_query_arg(['step'=>2,'accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co], rsv_checkout_url()) ).'">'.esc_html__('Back','reeserva').'</a></p>';
        } else {
            // No Stripe: confirm directly
            if (!$name || !$email) { echo '<p class="error">'.esc_html__('Please go back and fill your details.','reeserva').'</p></div></div>'; return ob_get_clean(); }
            $bid = wp_insert_post(['post_type'=>'rsv_booking','post_status'=>'confirmed','post_title'=>sprintf(__('Booking: %s','reeserva'), $name),'post_content'=>$notes]);
            if (!is_wp_error($bid) && $bid) {
                update_post_meta($bid,'rsv_booking_accomm', $accomm_id);
                update_post_meta($bid,'rsv_check_in',      $ci);
                update_post_meta($bid,'rsv_check_out',     $co);
                update_post_meta($bid,'rsv_guest_name',    $name);
                update_post_meta($bid,'rsv_guest_email',   $email);
                update_post_meta($bid,'rsv_payment_status','confirmed');
                do_action('rsv_booking_confirmed', $bid, ['accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,'name'=>$name,'email'=>$email,'total'=>$total]);
                echo '<div class="confirm"><div class="badge">✔</div><h3>'.esc_html__('Booking confirmed','reeserva').'</h3>';
                echo '<p><strong>'.esc_html__('Reference','reeserva').':</strong> '.intval($bid).'</p>';
                echo '<a class="btn-secondary" href="'.esc_url( get_permalink($accomm_id) ).'">'.esc_html__('Back to listing','reeserva').'</a></div>';
            } else {
                echo '<p class="error">'.esc_html__('Could not create booking. Please try again.','reeserva').'</p>';
            }
        }
        echo '</div></div>'; return ob_get_clean();
    }
    echo '</div>'; return ob_get_clean();
});
