
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode('rsv_search', function(){
    $ci = sanitize_text_field($_GET['ci'] ?? '');
    $co = sanitize_text_field($_GET['co'] ?? '');
    $guests = max(1, intval($_GET['guests'] ?? 1));
    ob_start();
    ?>
    <form class="ehb-search-form" method="get" action="" style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0">
      <label><?php esc_html_e('Check-in','reeserva'); ?> <input type="date" name="ci" value="<?php echo esc_attr($ci); ?>" required></label>
      <label><?php esc_html_e('Check-out','reeserva'); ?> <input type="date" name="co" value="<?php echo esc_attr($co); ?>" required></label>
      <label><?php esc_html_e('Guests','reeserva'); ?> <input type="number" name="guests" min="1" value="<?php echo esc_attr($guests); ?>" required></label>
      <button type="submit"><?php esc_html_e('Search','reeserva'); ?></button>
    </form>
    <?php
    if($ci && $co && $guests){
        $types = get_posts(['post_type'=>'rsv_accomm','post_status'=>'publish','numberposts'=>-1]);
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
        echo '<div class="rsv-results" style="display:grid;gap:20px;margin-top:20px">';
        $found=false;
        foreach($types as $t){
            $cap = (int) get_post_meta($t->ID,'rsv_max_guests',true);
            if($cap < $guests) continue;
            if(!$is_available($t->ID,$ci,$co)) continue;
            $total = rsv_quote_total($t->ID,$ci,$co,$guests);
            $url = add_query_arg(['accomm'=>$t->ID,'ci'=>$ci,'co'=>$co,'guests'=>$guests], rsv_checkout_url());
            echo '<div class="rsv-result"><h4>'.esc_html(get_the_title($t)).'</h4><p>€'.esc_html(number_format($total,2)).'</p>';
            echo '<a class="btn-primary" href="'.esc_url($url).'">'.esc_html__('Book','reeserva').'</a></div>';
            $found=true;
        }
        if(!$found) echo '<p>'.esc_html__('No accommodations available for these dates.','reeserva').'</p>';
        echo '</div>';
    }
    return ob_get_clean();
});

add_shortcode('rsv_checkout', function(){
    wp_enqueue_style('rsv-checkout', RSV_URL.'assets/css/checkout.css', [], RSV_VER);
    $accomm_id = intval($_GET['accomm'] ?? ($_POST['accomm'] ?? 0));
    $ci = sanitize_text_field($_GET['ci'] ?? ($_POST['ci'] ?? ''));
    $co = sanitize_text_field($_GET['co'] ?? ($_POST['co'] ?? ''));
    $guests = max(1, intval($_GET['guests'] ?? ($_POST['guests'] ?? 1)));
    $total = rsv_quote_total($accomm_id,$ci,$co,$guests);


    if (isset($_GET['rsv_stripe']) && $_GET['rsv_stripe']==='return' && !empty($_GET['session_id'])){
        $session = rsv_stripe_retrieve_session(sanitize_text_field($_GET['session_id']));
        echo '<div class="ehb-wizard"><div class="card">';
        if($session && ($session['payment_status'] ?? '') === 'paid'){
            $fname = sanitize_text_field($_GET['first_name'] ?? '');
            $lname = sanitize_text_field($_GET['last_name'] ?? '');
            $email = sanitize_email($_GET['email'] ?? '');
            $phone = sanitize_text_field($_GET['phone'] ?? '');
            $notes = sanitize_textarea_field($_GET['notes'] ?? '');

            $full = trim($fname.' '.$lname);
            $exists = get_posts(['post_type'=>'rsv_booking','post_status'=>['confirmed','publish'],'numberposts'=>1,
                'meta_query'=>[['key'=>'rsv_stripe_session','value'=>sanitize_text_field($_GET['session_id']),'compare'=>'=']]]);
            if(!$exists){
                $bid = wp_insert_post(['post_type'=>'rsv_booking','post_status'=>'confirmed','post_title'=>sprintf(__('Booking: %s','reeserva'), $full),'post_content'=>'']);
                if(!is_wp_error($bid) && $bid){
                    update_post_meta($bid,'rsv_booking_accomm',$accomm_id);
                    update_post_meta($bid,'rsv_check_in',$ci);
                    update_post_meta($bid,'rsv_check_out',$co);
                    update_post_meta($bid,'rsv_guest_first_name',$fname);
                    update_post_meta($bid,'rsv_guest_last_name',$lname);
                    update_post_meta($bid,'rsv_guest_name',$full);
                    update_post_meta($bid,'rsv_guest_email',$email);
                    update_post_meta($bid,'rsv_guest_phone',$phone);
                    update_post_meta($bid,'rsv_total_guests',$guests);
                    update_post_meta($bid,'rsv_booking_notes',$notes);
                    update_post_meta($bid,'rsv_booking_total',$total);
                    update_post_meta($bid,'rsv_price_paid',$total);
                    update_post_meta($bid,'rsv_payment_method','stripe');
                    update_post_meta($bid,'rsv_payment_status','paid');
                    update_post_meta($bid,'rsv_stripe_session',sanitize_text_field($_GET['session_id']));
                    if(!empty($session['payment_intent']))
                        update_post_meta($bid,'rsv_stripe_payment_intent',sanitize_text_field($session['payment_intent']));
                    update_post_meta($bid,'rsv_payment_status','paid');
                    update_post_meta($bid,'rsv_stripe_session',sanitize_text_field($_GET['session_id']));

                    do_action('rsv_booking_confirmed', $bid, ['accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,'name'=>$full,'email'=>$email]);
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

    if (!$accomm_id || !$ci || !$co){
        return '<div class="ehb-wizard"><div class="card"><p>'.esc_html__('Missing data. Please start again.','reeserva').'</p></div></div>';
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

    if (!$is_available($accomm_id,$ci,$co)){
        return '<div class="ehb-wizard"><div class="card"><p>'.esc_html__('Sorry, these dates are no longer available.','reeserva').'</p></div></div>';
    }

    $cap = (int) get_post_meta($accomm_id,'rsv_max_guests',true);
    if($guests > $cap){
        return '<div class="ehb-wizard"><div class="card"><p>'.esc_html__('Too many guests for this accommodation.','reeserva').'</p></div></div>';
    }

    $p = rsv_get_payment_settings();
    $total = rsv_quote_total($accomm_id,$ci,$co,$guests);

    if(!$p['stripe_enabled'] && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['first_name'])){
        $fname = sanitize_text_field($_POST['first_name'] ?? '');
        $lname = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        if(!$fname || !$lname || !$email || !$phone){
            echo '<div class="ehb-wizard"><div class="card"><p>'.esc_html__('Please fill all fields.','reeserva').'</p></div></div>';
            return '';
        }
        $full = trim($fname.' '.$lname);
        $bid = wp_insert_post(['post_type'=>'rsv_booking','post_status'=>'confirmed','post_title'=>sprintf(__('Booking: %s','reeserva'), $full),'post_content'=>'']);
        if(!is_wp_error($bid) && $bid){
            update_post_meta($bid,'rsv_booking_accomm',$accomm_id);
            update_post_meta($bid,'rsv_check_in',$ci);
            update_post_meta($bid,'rsv_check_out',$co);
            update_post_meta($bid,'rsv_guest_first_name',$fname);
            update_post_meta($bid,'rsv_guest_last_name',$lname);
            update_post_meta($bid,'rsv_guest_name',$full);
            update_post_meta($bid,'rsv_guest_email',$email);
            update_post_meta($bid,'rsv_guest_phone',$phone);
            update_post_meta($bid,'rsv_total_guests',$guests);
            update_post_meta($bid,'rsv_booking_notes',$notes);
            update_post_meta($bid,'rsv_booking_total',$total);
            update_post_meta($bid,'rsv_price_paid',$total);
            update_post_meta($bid,'rsv_payment_method','manual');
            update_post_meta($bid,'rsv_payment_status','confirmed');
            do_action('rsv_booking_confirmed', $bid, ['accomm'=>$accomm_id,'ci'=>$ci,'co'=>$co,'name'=>$full,'email'=>$email,'total'=>$total]);
            echo '<div class="ehb-wizard"><div class="card"><div class="confirm"><div class="badge">✔</div><h3>'.esc_html__('Booking confirmed','reeserva').'</h3>';
            echo '<p><strong>'.esc_html__('Reference','reeserva').':</strong> '.intval($bid).'</p>';
            echo '<a class="btn-secondary" href="'.esc_url( get_permalink($accomm_id) ).'">'.esc_html__('Back to listing','reeserva').'</a></div></div></div>';
        } else {
            echo '<div class="ehb-wizard"><div class="card"><p class="error">'.esc_html__('Could not create booking. Please try again.','reeserva').'</p></div></div>';
        }
        return '';
    }

    ob_start();
    echo '<div class="ehb-wizard"><div class="card"><h2>'.esc_html__('Your details','reeserva').'</h2>';
    echo '<div class="summary">'.esc_html(get_the_title($accomm_id)).' • '.esc_html($ci).' → '.esc_html($co).' • '.sprintf(_n('%d guest','%d guests',$guests,'reeserva'),$guests).'</div>';
    echo '<form method="post" class="form-grid" id="rsv-book">';
    echo '<input type="hidden" name="accomm" value="'.esc_attr($accomm_id).'"><input type="hidden" name="ci" value="'.esc_attr($ci).'"><input type="hidden" name="co" value="'.esc_attr($co).'"><input type="hidden" name="guests" value="'.esc_attr($guests).'">';
    echo '<label>'.esc_html__('First name','reeserva').'<input type="text" name="first_name" required></label>';
    echo '<label>'.esc_html__('Last name','reeserva').'<input type="text" name="last_name" required></label>';
    echo '<label>'.esc_html__('Email','reeserva').'<input type="email" name="email" required></label>';
    echo '<label>'.esc_html__('Phone','reeserva').'<input type="tel" name="phone" required></label>';
    echo '<label>'.esc_html__('Notes','reeserva').'<textarea name="notes"></textarea></label>';
    if($p['stripe_enabled']){
        echo '<button id="rsv-pay" type="button" class="btn-primary">'.esc_html__('Pay now','reeserva').'</button></form>';
        echo '<script src="https://js.stripe.com/v3/\"></script>';
        echo '<script>
        document.getElementById("rsv-pay").addEventListener("click", function(){
            var fd=new FormData(document.getElementById("rsv-book"));
            fd.append("action","rsv_stripe_checkout");
            fetch("'.esc_js(admin_url('admin-ajax.php')).'",{method:"POST",body:fd,credentials:"same-origin"})
            .then(r=>r.json()).then(function(res){
                if(res && res.success && res.data && res.data.url){ window.location=res.data.url; }
                else{ alert("Stripe error: "+(res && (res.data && res.data.message || res.message) || "unknown")); }
            }).catch(function(){ alert("Network error"); });
        });
        </script>';
    } else {
        echo '<button class="btn-primary" type="submit">'.esc_html__('Confirm booking','reeserva').'</button></form>';
    }
    echo '</div></div>';
    return ob_get_clean();
});
