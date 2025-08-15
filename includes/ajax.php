
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Booked days (red blocks) per accommodation
add_action('wp_ajax_rsv_get_booked','rsv_get_booked');
function rsv_get_booked(){
    check_ajax_referer('rsv_get_booked','nonce');
    $type_id = intval($_GET['type_id'] ?? 0);
    if(!$type_id){ wp_send_json_success([]); }
    $posts = get_posts([ 'post_type'=>'rsv_booking','post_status'=>['confirmed','publish'],'numberposts'=>-1,
        'meta_query'=>[['key'=>'rsv_booking_accomm','value'=>$type_id,'compare'=>'=']] ]);
    $events=[];
    foreach($posts as $post){
        $ci = get_post_meta($post->ID,'rsv_check_in',true);
        $co = get_post_meta($post->ID,'rsv_check_out',true);
        if(!$ci || !$co) continue;
        $events[] = [
            'title' => get_post_meta($post->ID,'rsv_guest_name',true) ?: __('Booking','reeserva'),
            'start' => $ci,
            'end'   => $co,
        ];
    }
    wp_send_json_success($events);
}

// Load prices (green chips)
add_action('wp_ajax_rsv_load_prices','rsv_load_prices');
function rsv_load_prices(){
    check_ajax_referer('rsv_load_prices','nonce');
    $type_id = intval($_GET['type_id'] ?? 0);
    $events = [];
    $rates = get_posts(['post_type'=>'rsv_rate','post_status'=>'publish','numberposts'=>-1]);
    foreach($rates as $r){
        if(intval(get_post_meta($r->ID,'rsv_accomm_id',true)) !== $type_id) continue;
        $sps = get_post_meta($r->ID,'rsv_season_prices',true);
        if(!is_array($sps)) continue;
        foreach($sps as $e){
            $sid = intval($e['season'] ?? 0); if(!$sid) continue;
            $sd = get_post_meta($sid,'rsv_start_date',true);
            $ed = get_post_meta($sid,'rsv_end_date',true);
            if(!$sd||!$ed) continue;
            $price = floatval( ($e['price']['prices'][0] ?? 0) );
            $has_vars = !empty($e['price']['enable_variations']);
            $events[] = [
                'title' => '€'.number_format($price,0),
                'start' => $sd,
                'end'   => (new DateTime($ed))->modify('+1 day')->format('Y-m-d'),
                'allDay'=> true,
                'backgroundColor' => '#e6f7ed',
                'borderColor'     => '#e6f7ed',
                'isPrice'         => true,
            ];
        }
    }
    wp_send_json($events);
}

// Get prices for a single day
add_action('wp_ajax_rsv_day_prices','rsv_day_prices');
function rsv_day_prices(){
    check_ajax_referer('rsv_day_prices','nonce');
    $day = sanitize_text_field($_GET['start'] ?? '');
    $type_id = intval($_GET['type_id'] ?? 0);
    if(!$day||!$type_id){ wp_send_json(['status'=>'error','message'=>'missing']); }
    $rates = get_posts(['post_type'=>'rsv_rate','post_status'=>'publish','numberposts'=>-1,
        'meta_query'=>[['key'=>'rsv_accomm_id','value'=>$type_id,'compare'=>'=']]]);
    $found=[];
    foreach($rates as $r){
        $sps = get_post_meta($r->ID,'rsv_season_prices',true);
        if(!is_array($sps)) continue;
        foreach($sps as $e){
            $sid=intval($e['season'] ?? 0); if(!$sid) continue;
            $sd = get_post_meta($sid,'rsv_start_date',true);
            $ed = get_post_meta($sid,'rsv_end_date',true);
            if($sd && $ed && $day >= $sd && $day <= $ed){
                $found[] = $e;
            }
        }
    }
    if(empty($found)){
        if(empty($rates)) wp_send_json(['status'=>'none']);
        $bp = floatval(get_post_meta($rates[0]->ID,'rsv_base_price',true) ?: 0);
        wp_send_json(['status'=>'single','periods'=>[1],'prices'=>[$bp],'variations'=>[]]);
    }
    // Simplify: if multiple matches but identical, return single
    $periods = []; $prices=[]; $variations=[];
    foreach($found as $e){
        $periods[] = implode(',', (array)($e['price']['periods'] ?? [1]));
        $prices[]  = implode(',', (array)($e['price']['prices']  ?? [0]));
        $variations[] = wp_json_encode( (array)($e['price']['variations'] ?? []) );
    }
    $up = array_unique($periods); $ur = array_unique($prices); $uv = array_unique($variations);
    if( count($up)===1 && count($ur)===1 && count($uv)===1 ){
        wp_send_json(['status'=>'single','periods'=>array_map('intval', explode(',', $up[0])),
            'prices'=>array_map('floatval', explode(',', $ur[0])),
            'variations'=> json_decode($uv[0], true) ]);
    }
    wp_send_json(['status'=>'multiple']);
}

// Update/create prices for a range
add_action('wp_ajax_rsv_update_price','rsv_update_price');
function rsv_update_price(){
    check_ajax_referer('rsv_update_price','nonce');
    $type_id    = intval($_POST['type_id'] ?? 0);
    $start_date = sanitize_text_field($_POST['start_date'] ?? '');
    $end_date   = sanitize_text_field($_POST['end_date'] ?? '');
    $periods    = json_decode(stripslashes($_POST['periods'] ?? '[]'), true);
    $base_prices= json_decode(stripslashes($_POST['base_prices'] ?? '[]'), true);
    $vars       = json_decode(stripslashes($_POST['variations'] ?? '[]'), true);
    if(!$type_id||!$start_date||!$end_date||empty($periods)||empty($base_prices)){
        wp_send_json_error(['message'=>'Missing fields']);
    }

    $season_ids = [];
    $period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day'));
    foreach($period as $day){
        $ds = $day->format('Y-m-d');
        $found = get_posts(['post_type'=>'rsv_season','post_status'=>'publish','numberposts'=>1,
            'meta_query'=>[ ['key'=>'rsv_start_date','value'=>$ds,'compare'=>'='], ['key'=>'rsv_end_date','value'=>$ds,'compare'=>'='] ] ]);
        if($found){ $sid=$found[0]->ID; }
        else {
            $sid = wp_insert_post(['post_title'=>'Season '.$ds,'post_type'=>'rsv_season','post_status'=>'publish']);
            update_post_meta($sid,'rsv_start_date',$ds);
            update_post_meta($sid,'rsv_end_date',$ds);
        }
        $season_ids[]=$sid;
        $seasonal[$sid] = [
            'season'=>(string)$sid,
            'price'=>[
                'periods'=>array_map('intval',$periods),
                'prices'=>array_map('floatval',$base_prices),
                'enable_variations'=> !empty($vars),
                'variations'=> array_map(function($v){
                    return ['adults'=>intval($v['adults'] ?? 1),'children'=>intval($v['children'] ?? 0),'prices'=>array_map('floatval', (array)($v['prices'] ?? []))];
                }, $vars),
            ],
        ];
    }

    // find or create rate
    $existing = get_posts(['post_type'=>'rsv_rate','post_status'=>'publish','numberposts'=>1,
        'meta_query'=>[['key'=>'rsv_accomm_id','value'=>$type_id,'compare'=>'=']]]);
    if($existing){ $rid=$existing[0]->ID; }
    else{
        $rid=wp_insert_post(['post_title'=>'Base Rate','post_type'=>'rsv_rate','post_status'=>'publish']);
        update_post_meta($rid,'rsv_accomm_id',$type_id);
    }

    $existing_prices = get_post_meta($rid,'rsv_season_prices', true);
    $existing_prices = is_array($existing_prices) ? $existing_prices : [];
    $indexed = [];
    foreach($existing_prices as $ent){
        if(isset($ent['season'])) $indexed[ $ent['season'] ] = $ent;
    }
    foreach($seasonal as $sid=>$ent){ $indexed[$sid] = $ent; }
    update_post_meta($rid,'rsv_season_prices', array_values($indexed));
    update_post_meta($rid,'rsv_season_ids', $season_ids);
    update_post_meta($rid,'rsv_base_price', floatval($base_prices[0] ?? 0));

    $events = array_map(function($sid) use($base_prices){
        $d = get_post_meta($sid,'rsv_start_date',true);
        return ['title'=>'€'.number_format(floatval($base_prices[0] ?? 0),0),'start'=>$d,'allDay'=>true,'backgroundColor'=>'#e6f7ed','borderColor'=>'#e6f7ed','isPrice'=>true];
    }, $season_ids);

    wp_send_json_success(['events'=>$events]);
}

// Admin new booking quicklink (optional)
add_action('wp_ajax_rsv_admin_new_booking','rsv_admin_new_booking');
function rsv_admin_new_booking(){
    if(!current_user_can('edit_posts')) wp_die('No');
    $t = intval($_GET['type_id'] ?? 0); $ci = sanitize_text_field($_GET['ci'] ?? ''); $co = sanitize_text_field($_GET['co'] ?? '');
    echo '<div style="padding:10px;font:14px/1.4 sans-serif"><h2>New booking (not implemented fully)</h2><p>Acommodation #'.intval($t).' from '.esc_html($ci).' to '.esc_html($co).'</p></div>';
    exit;
}
