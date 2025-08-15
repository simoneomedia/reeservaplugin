
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function rsv_admin_enqueue(){
    wp_enqueue_style('rsv-admin', RSV_URL.'assets/css/admin.css', [], RSV_VER);
    wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js', [], '6.1.11', true);
    wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css', [], '6.1.11');
    wp_enqueue_script('rsv-admin-calendar', RSV_URL.'assets/js/admin-calendar.js', ['fullcalendar'], RSV_VER, true);
    wp_localize_script('rsv-admin-calendar','RSV_ADMIN',[
        'ajax'=> admin_url('admin-ajax.php'),
        'nonceBooked'=> wp_create_nonce('rsv_get_booked'),
        'nonceLoad'  => wp_create_nonce('rsv_load_prices'),
        'nonceDay'   => wp_create_nonce('rsv_day_prices'),
        'nonceUpdate'=> wp_create_nonce('rsv_update_price'),
    ]);
}
add_action('admin_enqueue_scripts','rsv_admin_enqueue');

function rsv_render_calendar(){
    $types = get_posts(['post_type'=>'rsv_accomm','post_status'=>'publish','numberposts'=>-1]);
    $current = !empty($types) ? $types[0]->ID : 0;
    ?>
    <div class="wrap">
      <h1>Reeserva — <?php esc_html_e('Calendar','reeserva');?></h1>
      <div class="booking-calendar-controls">
        <label><?php esc_html_e('Accommodation','reeserva');?>:
          <select id="accommodation-select">
            <?php foreach($types as $t): ?>
              <option value="<?php echo esc_attr($t->ID);?>" <?php selected($t->ID,$current);?>><?php echo esc_html(get_the_title($t));?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button class="button" id="rsv-sync-ical">Sync iCal now</button>
        <span id="rsv-sync-result" style="color:#555"></span>
      </div>
      <div id="calendar"></div>

      <div class="booking-summary">
        <h3><?php esc_html_e('Reserved Dates','reeserva')?></h3>
        <table class="booking-summary-table">
          <thead><tr><th><?php esc_html_e('Date','reeserva')?></th></tr></thead>
          <tbody id="summary-body"></tbody>
        </table>
      </div>

      <!-- Modal -->
      <div id="price-modal" class="modal-overlay" aria-modal="true">
        <div class="modal-content">
          <button class="modal-close" aria-label="<?php esc_attr_e('Close','reeserva')?>">×</button>
          <h2><?php esc_html_e('Set Prices','reeserva')?></h2>
          <div id="selected-dates"></div>
          <div id="periods-box"></div>
          <div id="price-warning"></div>
          <div class="grid-2">
            <div>
              <h4><?php esc_html_e('Base prices per nights','reeserva');?></h4>
              <div id="base-periods"></div>
              <button type="button" id="add-period" class="button"><?php esc_html_e('Add nights tier','reeserva');?></button>
            </div>
            <div>
              <h4><?php esc_html_e('Variations (per tier)','reeserva');?></h4>
              <div id="variations-container"></div>
              <button type="button" id="add-variation" class="button button-secondary"><?php esc_html_e('Add variation','reeserva');?></button>
            </div>
          </div>
          <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
            <button type="button" id="save-price" class="button button-primary"><?php esc_html_e('Save','reeserva');?></button>
            <button type="button" id="create-admin-booking" class="button"><?php esc_html_e('Create Reservation','reeserva')?></button>
          </div>
        </div>
      </div>
    </div>
    <?php
}
