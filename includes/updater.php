
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists('Reeserva_GitHub_Updater') ){
class Reeserva_GitHub_Updater {
  private $file; private $owner; private $repo; private $branch; private $slug;
  function __construct($file, $args){
    $this->file = $file; $this->owner=$args['owner']; $this->repo=$args['repo']; $this->branch=$args['branch'];
    $this->slug = dirname(plugin_basename($this->file));
    add_filter('pre_set_site_transient_update_plugins', [$this,'check']);
    add_filter('plugins_api', [$this,'api'], 10, 3);
    add_filter('upgrader_source_selection', [$this,'rename_source'], 10, 4);
  }
  function check($transient){
    if(empty($transient->checked)) return $transient;
    $request = wp_remote_get('https://api.github.com/repos/'.$this->owner.'/'.$this->repo.'/releases/latest',[ 'headers'=>['Accept'=>'application/vnd.github+json'] ]);
    if(is_wp_error($request)) return $transient;
    $rel = json_decode(wp_remote_retrieve_body($request), true);
    if(!isset($rel['tag_name'])) return $transient;
    $current = RSV_VER;
    $remote  = ltrim($rel['tag_name'],'v');
    if(version_compare($remote, $current, '>')){
      $obj = new stdClass();
      $obj->slug = plugin_basename($this->file);
      $obj->new_version = $remote;
      $obj->url = 'https://github.com/'.$this->owner.'/'.$this->repo;
      $obj->package = $rel['assets'][0]['browser_download_url'] ?? $rel['zipball_url'];
      $transient->response[ plugin_basename($this->file) ] = $obj;
    }
    return $transient;
  }
  function api($res, $action, $args){
    return $res;
  }
  function rename_source($source, $remote_source, $upgrader, $hook_extra){
    $prefix  = $this->owner.'-'.$this->repo.'-';
    $base    = basename($source);
    if(strpos($base, $prefix) === 0){
      $desired = trailingslashit($remote_source).$this->slug;
      if($source !== $desired && is_dir($source) && !is_dir($desired)){
        rename($source, $desired);
        return $desired;
      }
    }
    return $source;
  }
}}
