
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Provide ICS feed: /?rsv_ics=ID&key=XYZ
add_action('init', function(){
    add_rewrite_tag('%rsv_ics%','([^&]+)');
});
add_action('template_redirect', function(){
    $id = intval(get_query_var('rsv_ics'));
    if (!$id) return;
    $key = sanitize_text_field($_GET['key'] ?? '');
    $sitekey = get_option('rsv_ics_key');
    if (!$sitekey){ $sitekey = wp_generate_password(20,false,false); update_option('rsv_ics_key',$sitekey); }
    if ($key !== $sitekey){ status_header(403); exit; }
    $bookings = get_posts(['post_type'=>'rsv_booking','post_status'=>['publish','confirmed'],'numberposts'=>-1,
        'meta_query'=>[['key'=>'rsv_booking_accomm','value'=>$id,'compare'=>'=']]]);

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="accommodation-'.$id.'.ics"');
    echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Reeserva//EN\r\n";
    foreach($bookings as $b){
        $ci = get_post_meta($b->ID,'rsv_check_in',true);
        $co = get_post_meta($b->ID,'rsv_check_out',true);
        $uid = 'rsv-'.md5($b->ID.'|'.$ci.'|'.$co).'@'.parse_url(home_url(), PHP_URL_HOST);
        $dtstart = gmdate('Ymd\THis\Z', strtotime($ci));
        $dtend   = gmdate('Ymd\THis\Z', strtotime($co));
        $sum = addslashes(get_the_title($b->ID));
        echo "BEGIN:VEVENT\r\nUID:$uid\r\nDTSTART:$dtstart\r\nDTEND:$dtend\r\nSUMMARY:$sum\r\nEND:VEVENT\r\n";
    }
    echo "END:VCALENDAR";
    exit;
});

// Import ICS sources stored on accommodation
function rsv_parse_ics($text){
    $events=[]; $lines=preg_split('/\r?\n/', $text);
    $cur=null;
    foreach($lines as $ln){
        $ln=trim($ln);
        if($ln==='BEGIN:VEVENT'){ $cur=[]; }
        elseif($ln==='END:VEVENT'){ if($cur) $events[]=$cur; $cur=null; }
        elseif($cur!==null){
            if(strpos($ln,':')!==false){
                list($k,$v)=explode(':',$ln,2);
                $k=strtoupper(trim(preg_replace('/;.*$/','',$k))); $v=trim($v);
                $cur[$k]=$v;
            }
        }
    }
    return $events;
}

add_action('wp_ajax_rsv_ical_sync','rsv_ical_sync');
function rsv_ical_sync(){
    if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'forbidden'],403);
    $id = intval($_POST['type_id'] ?? 0);
    if(!$id) wp_send_json_error(['message'=>'missing id']);
    $urls = (array) get_post_meta($id,'rsv_ical_sources', true);
    $urls = array_filter(array_map('trim', is_array($urls)?$urls:explode("\n",$urls)));
    $added=0;
    foreach($urls as $u){
        $resp = wp_remote_get($u, ['timeout'=>20]);
        if(is_wp_error($resp)) continue;
        $body = wp_remote_retrieve_body($resp);
        $events = rsv_parse_ics($body);
        foreach($events as $ev){
            $uid = $ev['UID'] ?? md5( ($ev['DTSTART'] ?? '').'|'.($ev['DTEND'] ?? '') );
            $ci = isset($ev['DTSTART']) ? gmdate('Y-m-d', strtotime($ev['DTSTART'])) : '';
            $co = isset($ev['DTEND'])   ? gmdate('Y-m-d', strtotime($ev['DTEND']))   : '';
            if(!$ci||!$co) continue;
            $found = get_posts(['post_type'=>'rsv_booking','post_status'=>['publish','confirmed'],'numberposts'=>1,
                'meta_query'=>[['key'=>'rsv_ical_uid','value'=>$uid,'compare'=>'=']]]);
            if($found) continue;
            $bid = wp_insert_post(['post_type'=>'rsv_booking','post_status'=>'confirmed','post_title'=>'External iCal']);
            update_post_meta($bid,'rsv_booking_accomm',$id);
            update_post_meta($bid,'rsv_check_in',$ci);
            update_post_meta($bid,'rsv_check_out',$co);
            update_post_meta($bid,'rsv_ical_uid',$uid);
            $added++;
        }
    }
    wp_send_json_success(['added'=>$added]);
}
