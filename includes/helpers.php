
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function rsv_default_amenities(){
    return [
        'wifi'        => __('Wi‑Fi','reeserva'),
        'ac'          => __('Air conditioning','reeserva'),
        'heating'     => __('Heating','reeserva'),
        'kitchen'     => __('Kitchen','reeserva'),
        'washer'      => __('Washer','reeserva'),
        'dryer'       => __('Dryer','reeserva'),
        'tv'          => __('TV','reeserva'),
        'parking'     => __('Free parking','reeserva'),
        'pool'        => __('Pool','reeserva'),
        'pets'        => __('Pets allowed','reeserva'),
        'smoke_alarm' => __('Smoke alarm','reeserva'),
        'first_aid'   => __('First aid kit','reeserva'),
    ];
}
function rsv_get_meta($post_id, $key, $default = null){
    $v = get_post_meta($post_id, $key, true);
    return ($v === '' || $v === null) ? $default : $v;
}
function rsv_checkout_url(){
    $pid = get_option('rsv_checkout_page_id');
    return $pid ? get_permalink($pid) : home_url('/');
}
function rsv_date_range_overlaps($startA, $endA, $startB, $endB){
    return (strtotime($startA) < strtotime($endB)) && (strtotime($endA) > strtotime($startB));
}
function rsv_make_ics($args){
    $uid = uniqid('rsv_', true).'@'.parse_url(home_url(), PHP_URL_HOST);
    $dtstamp = gmdate('Ymd\THis\Z');
    $dtstart = gmdate('Ymd\THis\Z', strtotime($args['check_in']));
    $dtend   = gmdate('Ymd\THis\Z', strtotime($args['check_out']));
    $title   = addslashes($args['title'] ?? 'Booking');
    $desc    = addslashes($args['description'] ?? '');
    $loc     = addslashes($args['location'] ?? '');
    $ics  = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Reeserva//EN\r\n";
    $ics .= "BEGIN:VEVENT\r\nUID:$uid\r\nDTSTAMP:$dtstamp\r\nDTSTART:$dtstart\r\nDTEND:$dtend\r\n";
    $ics .= "SUMMARY:$title\r\nLOCATION:$loc\r\nDESCRIPTION:$desc\r\nEND:VEVENT\r\nEND:VCALENDAR";
    return $ics;
}

/** Pricing helpers **/
function rsv_pricing_map_for_day($accomm_id, $day){
    $rates = get_posts(['post_type'=>'rsv_rate','post_status'=>'publish','numberposts'=>-1,
        'meta_query'=>[['key'=>'rsv_accomm_id','value'=>$accomm_id,'compare'=>'=']]]);
    foreach($rates as $r){
        $sps = get_post_meta($r->ID,'rsv_season_prices',true);
        if(!is_array($sps)) continue;
        foreach($sps as $e){
            $sid=intval($e['season'] ?? 0); if(!$sid) continue;
            $sd = get_post_meta($sid,'rsv_start_date',true);
            $ed = get_post_meta($sid,'rsv_end_date',true);
            if($sd && $ed && $day >= $sd && $day <= $ed){
                $periods = array_map('intval',(array)($e['price']['periods'] ?? []));
                $prices  = array_map('floatval',(array)($e['price']['prices']  ?? []));
                return ['periods'=>$periods,'prices'=>$prices];
            }
        }
    }
    $bp =  floatval(get_post_meta($rates[0]->ID ?? 0,'rsv_base_price',true) ?? 0);
    return ['periods'=>[1],'prices'=>[$bp]];
}
function rsv_nightly_price_for_day($accomm_id,$day,$total_nights){
    $map = rsv_pricing_map_for_day($accomm_id,$day);
    $periods = $map['periods']; $prices = $map['prices'];
    if(empty($periods)) return floatval($prices[0] ?? 0);
    $idx = array_search($total_nights, $periods, true);
    if ($idx === false){
        // pick closest lower, else last
        $idx = 0; $best=-1;
        foreach($periods as $i=>$n){ if($n <= $total_nights && $n > $best){ $best=$n; $idx=$i; } }
        if($best === -1){ $idx = 0; }
    }
    return floatval($prices[$idx] ?? ($prices[0] ?? 0));
}
function rsv_quote_total($accomm_id,$ci,$co,$adults=2,$children=0){
    $start = new DateTime($ci); $end = new DateTime($co);
    $nights = (int)$end->diff($start)->format('%a'); if($nights<1) return 0;
    $sum=0; $cur = clone $start;
    for($i=0;$i<$nights;$i++){
        $day = $cur->format('Y-m-d');
        $sum += rsv_nightly_price_for_day($accomm_id,$day,$nights);
        $cur->modify('+1 day');
    }
    return $sum;
}
