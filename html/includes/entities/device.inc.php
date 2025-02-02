<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage web
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2022 Observium Limited
 *
 */

/**
 * Build devices where array
 *
 * This function returns an array of "WHERE" statements from a $vars array.
 * The returned array can be imploded and used on the devices table.
 * Originally extracted from the /devices/ page
 *
 * @param array $vars
 * @return array
 */
function build_devices_where_array($vars) {
  $where_array = array();
  foreach ($vars as $var => $value) {
    if (!safe_empty($value)) {
      switch ($var) {
        case 'group':
        case 'group_id':
          $values = get_group_entities($value);
          $where_array[$var] = generate_query_values_and($values, 'device_id');
          break;
        case 'device':
        case 'device_id':
          $where_array[$var] = generate_query_values_and($value, 'device_id');
          break;
        case 'hostname':
        case 'sysname':
        case 'sysContact':
        case 'sysDescr':
        case 'serial':
        case 'purpose':
          $condition = str_contains_array($value, [ '*', '?' ]) ? 'LIKE' : '%LIKE%';
          $where_array[$var] = generate_query_values_and($value, $var, $condition);
          break;
        case 'location_text':
          $condition = str_contains_array($value, [ '*', '?' ]) ? 'LIKE' : '%LIKE%';
          $where_array[$var] = generate_query_values_and($value, 'devices.location', $condition);
          break;
        case 'location':
          $where_array[$var] = generate_query_values_and($value, 'devices.location');
          break;
        case 'location_lat':
        case 'location_lon':
        case 'location_country':
        case 'location_state':
        case 'location_county':
        case 'location_city':
          if ($GLOBALS['config']['geocoding']['enable'])
          {
            $where_array[$var] = generate_query_values_and($value, 'devices_locations.' . $var);
          }
          break;
        case 'os':
        case 'version':
        case 'hardware':
        case 'vendor':
        case 'features':
        case 'type':
        case 'status':
        case 'status_type':
        case 'distro':
        case 'ignore':
        case 'disabled':
          $where_array[$var] = generate_query_values_and($value, $var);
          break;
        case 'graph':
          $where_array[$var] = generate_query_values_and(devices_with_graph($value), "devices.device_id");
     }
    }
  }

  return $where_array;
}

function devices_with_graph($graph)
{

  $devices = array();

  $sql = "SELECT `device_id` FROM `device_graphs` WHERE `graph` = ? AND `enabled` = '1'";
  foreach(dbFetchRows($sql, array($graph)) AS $entry)
  {
    $devices[$entry['device_id']] = $entry['device_id'];
  }

  return $devices;

}

function build_devices_sort($vars)
{
  $order = '';
  switch ($vars['sort'])
  {
    case 'uptime':
    case 'location':
    case 'version':
    case 'features':
    case 'type':
    case 'os':
    case 'sysName':
    case 'device_id':
      $order = ' ORDER BY `devices`.`'.$vars['sort'].'`';
      if ($vars['sort_order'] == "desc") { $order .= " DESC";}
      break;

    case 'domain':
      // Special order hostnames in Domain Order
      // SELECT `hostname`,
      //        SUBSTRING_INDEX(SUBSTRING_INDEX(`hostname`,'.',-3),'.',1) AS `leftmost`,
      //        SUBSTRING_INDEX(SUBSTRING_INDEX(`hostname`,'.',-2),'.',1) AS `middle`,
      //        SUBSTRING_INDEX(`hostname`,'.',-1) AS `rightmost`
      // FROM `devices` ORDER by `middle`, `rightmost`, `leftmost`;
      if ($vars['sort_order'] == "desc")
      {
        $order = ' ORDER BY `middle` DESC, `rightmost` DESC, `leftmost` DESC';
      } else {
        $order = ' ORDER BY `middle`, `rightmost`, `leftmost`';
      }
      break;

    case 'hostname':
    default:
      $order = ' ORDER BY `devices`.`hostname`';
      if ($vars['sort_order'] == "desc") { $order .= " DESC"; }
      break;
  }
  return $order;
}

// DOCME needs phpdoc block
function print_device_header($device, $args = array()) {
  global $config;

  if (!is_array($device)) { print_error("Invalid device passed to print_device_header()!"); }

  $div_class = 'box box-solid';
  if (!safe_empty($args['div-class'])) {
    $div_class .= " " . $args['div-class'];
  }

  echo '<div class="'.$div_class.'">
  <table class=" table table-hover table-condensed '.$args['class'].'" style="margin-bottom: 10px; min-height: 70px; border-radius: 2px;">';
  echo '
              <tr class="'.$device['html_row_class'].' vertical-align">
               <td class="state-marker"></td>
               <td style="width: 70px; text-align: center;">'.get_device_icon($device).'</td>
               <td><span style="font-size: 20px;">' . generate_device_link($device) . '</span>
               <br /><a href="'.generate_location_url($device['location']).'">' . escape_html($device['location']) . '</a></td>
               ';


  if (device_permitted($device) && !$args['no_graphs']) {

    echo '<td>';

    // Only show graphs for device_permitted(), don't show device graphs to users who can only see a single entity.

    if (isset($config['os'][$device['os']]['graphs'])) {
      $graphs = $config['os'][$device['os']]['graphs'];
    } elseif (isset($device['os_group'], $config['os'][$device['os_group']]['graphs'])) {
      $graphs = $config['os'][$device['os_group']]['graphs'];
    } else {
      // Default group
      $graphs = $config['os_group']['default']['graphs'];
    }

    $graph_array = [];
    //$graph_array['height'] = "100";
    //$graph_array['width']  = "310";
    $graph_array['to']     = get_time();
    $graph_array['device'] = $device['device_id'];
    $graph_array['type']   = "device_bits";
    $graph_array['from']   = get_time('day');
    $graph_array['legend'] = "no";

    $graph_array['height'] = "45";
    $graph_array['width']  = "150";
    $graph_array['style']  = array('width: 150px !important'); // Fix for FF issue on HiDPI screen
    $graph_array['bg']     = "FFFFFF00";

    // Preprocess device graphs array
    $graphs_enabled = [];
    foreach ($device['graphs'] as $graph) {
      $graphs_enabled[] = $graph['graph'];
    }

    foreach ($graphs as $entry) {
      if ($entry && in_array(str_replace('device_', '', $entry), $graphs_enabled, TRUE)) {
        $graph_array['type'] = $entry;

        if (preg_match(OBS_PATTERN_GRAPH_TYPE, $entry, $graphtype)) {
          $type = $graphtype['type'];
          $subtype = $graphtype['subtype'];

          $text = $config['graph_types'][$type][$subtype]['descr'];
        } else {
          $text = nicecase($entry); // Fallback to the type itself as a string, should not happen!
        }

        echo '<div class="pull-right" style="padding: 2px; margin: 0;">';
        //echo generate_graph_tag($graph_array);
        echo generate_graph_popup($graph_array);
        echo '<div style="padding: 0px; font-weight: bold; font-size: 7pt; text-align: center;">'.$text.'</div>';
        echo '</div>';
      }
    }

  echo '    </td>';

  } // Only show graphs for device_permitted()

  echo('
   </tr>
 </table>
</div>');
}

function print_device_row($device, $vars = array('view' => 'basic'), $link_vars = array())
{
  global $config, $cache;

  if (!is_array($device)) { print_error("Invalid device passed to print_device_row()!"); }

  if (!is_array($vars)) { $vars = array('view' => $vars); } // For compatibility

  humanize_device($device);

  $tags = array(
      'html_row_class'  => $device['html_row_class'],
      'device_id'     => $device['device_id'],
      'device_link'   => generate_device_link($device, NULL, $link_vars),
      'device_url'    => generate_device_url($device, $link_vars),
      'hardware'      => escape_html($device['hardware']),
      'features'      => escape_html($device['features']),
      'os_text'       => $device['os_text'],
      'version'       => escape_html($device['version']),
      //'sysName'       => escape_html($device['sysName']),
      'device_uptime' => deviceUptime($device, 'short'),
      'location'      => escape_html(truncate($device['location'], 40, ''))
  );

  switch (strtolower($config['web_device_name'])) {
    case 'sysname':
    case 'purpose':
    case 'descr':
    case 'description':
      $tags['sysName'] = escape_html($device['hostname']);
      if (!safe_empty($device['sysName'])) {
        $tags['sysName'] .= ' / ' . escape_html($device['sysName']);
      }
      break;
    default:
      $tags['sysName'] = escape_html($device['sysName']);
  }

  switch ($vars['view'])
  {
    case 'detail':
    case 'details':
    $table_cols = 7;
    $tags['device_image']  = get_device_icon($device);
      $tags['ports_count']   = dbFetchCell("SELECT COUNT(*) FROM `ports` WHERE `device_id` = ? AND `deleted` = ?", array($device['device_id'], 0));
      //$tags['sensors_count'] = dbFetchCell("SELECT COUNT(*) FROM `sensors` WHERE `device_id` = ? AND `sensor_deleted` = ?", array($device['device_id'], 0));
      //$tags['sensors_count'] += dbFetchCell("SELECT COUNT(*) FROM `status` WHERE `device_id` = ? AND `status_deleted` = ?", array($device['device_id'], 0));
      $tags['sensors_count']  = $cache['sensors']['devices'][$device['device_id']]['count'];
      $tags['sensors_count'] += $cache['statuses']['devices'][$device['device_id']]['count'];
      $hostbox = '
  <tr class="'.$tags['html_row_class'].'" onclick="openLink(\''.$tags['device_url'].'\')" style="cursor: pointer;">
    <td class="state-marker"></td>
    <td class="text-center vertical-align" style="width: 64px; text-align: center;">'.$tags['device_image'].'</td>
    <td style="width: 300px;"><span class="entity-title">'.$tags['device_link'].'</span><br />'.$tags['location'].'</td>
    <td class="text-nowrap" style="width: 55px;">';
      if ($tags['ports_count'])
      {
        $hostbox .= '<i class="'.$config['icon']['port'].'"></i> <span class="label">'.$tags['ports_count'].'</span>';
      }
      $hostbox .= '<br />';
      if ($tags['sensors_count'])
      {
        $hostbox .= '<i class="'.$config['icon']['sensor'].'"></i> ';
        $sensor_items = [];
        // Ok
        if ($event_count = $cache['sensors']['devices'][$device['device_id']]['ok'] + $cache['statuses']['devices'][$device['device_id']]['ok'])
        {
          $sensor_items[] = ['event' => 'success', 'text' => $event_count];
        }
        // Warning
        if ($event_count = $cache['sensors']['devices'][$device['device_id']]['warning'] + $cache['statuses']['devices'][$device['device_id']]['warning'])
        {
          $sensor_items[] = ['event' => 'warning', 'text' => $event_count];
        }
        // Alert
        if ($event_count = $cache['sensors']['devices'][$device['device_id']]['alert'] + $cache['statuses']['devices'][$device['device_id']]['alert'])
        {
          $sensor_items[] = ['event' => 'danger', 'text' => $event_count];
        }
        // Ignored
        if ($event_count = $cache['sensors']['devices'][$device['device_id']]['ignored'] + $cache['statuses']['devices'][$device['device_id']]['ignored'])
        {
          $sensor_items[] = ['event' => 'default', 'text' => $event_count];
        }
        $hostbox .= get_label_group($sensor_items);

        //'<span class="label">'.$tags['sensors_count'].'</span>';
      }
      $hostbox .= '</td>
    <td>'.$tags['os_text'].' '.$tags['version']. (!empty($tags['features']) ? ' ('.$tags['features'].')' : '').'<br />
        '.$tags['hardware'].'</td>
    <td>'.$tags['device_uptime'].'<br />'.$tags['sysName'].'</td>
  </tr>';
      break;
    case 'perf':
      if ($_SESSION['userlevel'] >= "10")
      {
        $tags['device_image']  = get_device_icon($device);
        $graph_array = array(
            'type'   => 'device_poller_perf',
            'device' => $device['device_id'],
            'operation' => 'poll',
            'legend'    => 'no',
            'width'  => 600,
            'height' => 90,
            'from'   => $config['time']['week'],
            'to'     => $config['time']['now'],
        );

        $hostbox = '
  <tr class="'.$tags['html_row_class'].'" onclick="openLink(\''.generate_device_url($device, ['tab' => 'perf']).'\')" style="cursor: pointer;">
    <td class="state-marker"></td>
    <td class="vertical-align" style="width: 64px; text-align: center;">'.$tags['device_image'].'</td>
    <td class="vertical-align" style="width: 300px;"><span class="entity-title">' . $tags['device_link'] . '</span><br />'.$tags['location'].'</td>
    <td><div class="pull-right" style="height: 130px; padding: 2px; margin: 0;">' . generate_graph_tag($graph_array) . '</div></td>
  </tr>';
      }
      break;
    case 'status':
      $tags['device_image']  = get_device_icon($device);

      // Graphs
      $graph_array = array();
      $graph_array['height'] = "100";
      $graph_array['width']  = "310";
      $graph_array['to']     = $config['time']['now'];
      $graph_array['device'] = $device['device_id'];
      $graph_array['type']   = "device_bits";
      $graph_array['from']   = $config['time']['day'];
      $graph_array['legend'] = "no";
      $graph_array['height'] = "45";
      $graph_array['width']  = "175";
      $graph_array['bg']     = "FFFFFF00";

      if (isset($config['os'][$device['os']]['graphs']))
      {
        $graphs = $config['os'][$device['os']]['graphs'];
      }
      else if (isset($device['os_group']) && isset($config['os'][$device['os_group']]['graphs']))
      {
        $graphs = $config['os'][$device['os_group']]['graphs'];
      } else {
        // Default group
        $graphs = $config['os_group']['default']['graphs'];
      }

      // Preprocess device graphs array
      $graphs_enabled = [];
      foreach ($device['graphs'] as $graph)
      {
        $graphs_enabled[] = $graph['graph'];
      }

      foreach ($graphs as $entry)
      {
        list(,$graph_subtype) = explode("_", $entry, 2);

        if ($entry && in_array(str_replace("device_", "", $entry), $graphs_enabled))
        {
          $graph_array['type'] = $entry;
          if(isset($config['graph_types']['device'][$graph_subtype]))
          {
            $title = $config['graph_types']['device'][$graph_subtype]['descr'];
          } else {
            $title = nicecase(str_replace("_", " ", $graph_subtype));
          }
          $tags['graphs'][] = '<div class="pull-right" style="margin: 5px; margin-bottom: 0px;">'. generate_graph_popup($graph_array) .'<br /><div style="text-align: center; padding: 0px; font-size: 7pt; font-weight: bold;">'.$title.'</div></div>';
        }
      }

      $hostbox = '
  <tr class="'.$tags['html_row_class'].'" onclick="openLink(\''.$tags['device_url'].'\')" style="cursor: pointer;">
    <td class="state-marker"></td>
    <td class="vertical-align" style="width: 64px; text-align: center;">'.$tags['device_image'].'</td>
    <td style="width: 300px;"><span class="entity-title">'.$tags['device_link'].'</span><br />'.$tags['location'].'</td>
    <td>';
      if ($tags['graphs'])
      {
        $hostbox .= '' . implode($tags['graphs']) . '';
      }
      $hostbox .= '</td>
  </tr>';
      break;
    default: // basic
      $table_cols = 6;
      $tags['device_image']  = get_device_icon($device);
      $tags['ports_count']   = dbFetchCell("SELECT COUNT(*) FROM `ports` WHERE `device_id` = ? AND `deleted` = 0;", array($device['device_id']));
      $tags['sensors_count'] = dbFetchCell("SELECT COUNT(*) FROM `sensors` WHERE `device_id` = ?;", array($device['device_id']));
      $tags['sensors_count'] += dbFetchCell("SELECT COUNT(*) FROM `status` WHERE `device_id` = ?;", array($device['device_id']));
      $hostbox = '
  <tr class="'.$tags['html_row_class'].'" onclick="openLink(\''.$tags['device_url'].'\')" style="cursor: pointer;">
    <td class="state-marker"></td>
    <td class="vertical-align" style="width: 64px; text-align: center;">'.$tags['device_image'].'</td>
    <td style="width: 300;"><span class="entity-title">'.$tags['device_link'].'</span><br />'.$tags['location'].'</td>
    <td>'.$tags['hardware'].' '.$tags['features'].'</td>
    <td>'.$tags['os_text'].' '.$tags['version'].'</td>
    <td>'.$tags['device_uptime'].'</td>
  </tr>';
  }


  // If we're showing graphs, generate the graph

  if ($vars['graph'])
  {
    $hostbox .= '<tr><td colspan="'.$table_cols.'">';

    $graph_array['to']     = $config['time']['now'];
    $graph_array['device']     = $device['device_id'];
    $graph_array['type']   = 'device_'.$vars['graph'];

    $hostbox .= generate_graph_row($graph_array);

    $hostbox .= '</td></tr>';

  }

  echo($hostbox);
}

/**
 * Returns icon tag (by default) or icon name for current device array
 *
 * @param array $device    Array with device info (from DB)
 * @param bool  $base_icon Return complete img tag with icon (by default) or just base icon name
 * @param bool  $dark      Prefer dark variant of icon (also set by session var)
 *
 * @return string Img tag with icon or base icon name
 */
function get_device_icon($device, $base_icon = FALSE, $dark = FALSE) {
  global $config;

  $icon = 'generic';
  $device['os'] = strtolower($device['os']);
  $model = $config['os'][$device['os']]['model'];

  if (!safe_empty($device['icon']) && is_file($config['html_dir'] . '/images/os/' . $device['icon'] . '.png')) {
    // Custom device icon from DB
    $icon  = $device['icon'];
  } elseif ($model && isset($config['model'][$model][$device['sysObjectID']]['icon']) &&
            is_file($config['html_dir'] . '/images/os/' . $config['model'][$model][$device['sysObjectID']]['icon'] . '.png')) {
    // Per model icon
    $icon  = $config['model'][$model][$device['sysObjectID']]['icon'];
  } elseif (isset($config['os'][$device['os']]['icon']) &&
            is_file($config['html_dir'] . '/images/os/' . $config['os'][$device['os']]['icon'] . '.png')) {
    // Icon defined in os definition
    $icon  = $config['os'][$device['os']]['icon'];
  } else {
    if ($device['distro']) {
      // Icon by distro name
      // Red Hat Enterprise -> redhat
      $distro = strtolower(trim(str_replace([ ' Enterprise', 'Red Hat' ], [ '', 'redhat' ], $device['distro'])));
      $distro = safename($distro);
      if (is_file($config['html_dir'] . '/images/os/' . $distro . '.png')) {
        $icon  = $distro;
      }
    }

    if ($icon === 'generic' && is_file($config['html_dir'] . '/images/os/' . $device['os'] . '.png')) {
      // Icon by OS name
      $icon  = $device['os'];
    }
  }

  // Icon by vendor name
  if ($icon === 'generic' && ($config['os'][$device['os']]['vendor'] || $device['vendor'])) {
    if ($device['vendor']) {
      $vendor = $device['vendor'];
    } else {
      $vendor = rewrite_vendor($config['os'][$device['os']]['vendor']); // Compatibility, if device not polled for long time
    }

    $vendor_safe = safename(strtolower($vendor));
    if (isset($config['vendors'][$vendor_safe]['icon'])) {
      $icon  = $config['vendors'][$vendor_safe]['icon'];
    } elseif (is_file($config['html_dir'] . '/images/os/' . $vendor_safe . '.png')) {
      $icon  = $vendor_safe;
    } elseif (isset($config['os'][$device['os']]['icons'])) {
      // Fallback to os alternative icon
      $icon  = array_values($config['os'][$device['os']]['icons'])[0];
    }
  }

  // Set dark mode by session
  if (isset($_SESSION['theme'])) {
    $dark = str_contains($_SESSION['theme'], 'dark');
  }

  // Prefer dark variant of icon in dark mode
  if ($dark && is_file($config['html_dir'] . '/images/os/' . $icon . '-dark.png')) {
    $icon .= '-dark';
  }

  if ($base_icon) {
    // return base name for os icon
    return $icon;
  }

  // return image html tag
  $base_url = rtrim($config['base_url'], '/');
  $srcset = '';
  // Now we always have 2x icon variant!
  //if (is_file($config['html_dir'] . '/images/os/' . $icon . '_2x.png')) // HiDPI image exist?
  //{
    // Detect allowed screen ratio for current browser
    $ua_info = detect_browser();

    if ($ua_info['screen_ratio'] > 1) {
      $srcset = ' srcset="' . $base_url . '/images/os/' . $icon . '_2x.png'.' 2x"';
    }
  //}

  // Image tag -- FIXME re-engineer this code to do this properly. This is messy.
  return '<img src="' . $base_url . '/images/os/' . $icon . '.png"' . $srcset . ' alt="" />';
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_device_url($device, $vars = array())
{
  return generate_url(array('page' => 'device', 'device' => $device['device_id']), $vars);
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_device_popup_header($device, $vars = []) {

  humanize_device($device);

  $device_name = device_name($device);
  if ($device['hostname'] !== $device_name) {
    $sysName = $device['hostname'];
    if (!safe_empty($device['sysName'])) {
      $sysName .= ' / ' . $device['sysName'];
    }
  } else {
    $sysName = $device['sysName'];
  }

  return generate_box_open() . '
<table class="table table-striped table-rounded table-condensed">
  <tr class="' . $device['html_row_class'] . '" style="font-size: 10pt;">
    <td class="state-marker"></td>
    <td class="vertical-align" style="width: 64px; text-align: center;">' . get_device_icon($device) . '</td>
    <td width="200px"><a href="'.generate_device_url($device).'" class="' . device_link_class($device) . '" style="font-size: 15px; font-weight: bold;">' .
         escape_html(device_name($device)) . '</a><br />' . escape_html(truncate($device['location'], 64, '')) . '</td>
    <td>' . $device['os_text'] . ' ' . escape_html($device['version']) . ' <br /> ' .
          ($device['vendor'] ? escape_html($device['vendor']).' ' : '') . escape_html($device['hardware']) . '</td>
    <td>' . deviceUptime($device, 'short') . '<br />' . escape_html($sysName) . '</td>
  </tr>
</table>
' . generate_box_close();
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_device_popup($device, $vars = []) {
  global $config;

  $content = generate_device_popup_header($device, $vars);

  if (isset($config['os'][$device['os']]['graphs'])) {
    $graphs = $config['os'][$device['os']]['graphs'];
  } elseif (isset($device['os_group'], $config['os'][$device['os_group']]['graphs'])) {
    $graphs = $config['os'][$device['os_group']]['graphs'];
  } else {
    // Default group
    $graphs = $config['os_group']['default']['graphs'];
  }

  // Preprocess device graphs array
  $graphs_enabled = [];
  foreach ($device['graphs'] as $graph) {
    if ($graph['enabled'] != '0') {
      $graphs_enabled[] = $graph['graph'];
    }
  }

  $count = 0;
  foreach ($graphs as $entry) {

    if($count == 3) { break; }

    if ($entry && in_array(str_replace('device_', '', $entry), $graphs_enabled, TRUE)) {
      // No text provided for the minigraph, fetch from array
      if (preg_match(OBS_PATTERN_GRAPH_TYPE, $entry, $graphtype)) {
        $type = $graphtype['type'];
        $subtype = $graphtype['subtype'];

        $text = $config['graph_types'][$type][$subtype]['descr'];
      } else {
        $text = nicecase($entry); // Fallback to the type itself as a string, should not happen!
      }

      // FIXME -- function!

      $graph_array = array();
      $graph_array['height'] = "100";
      $graph_array['width']  = "290";
      $graph_array['to']     = get_time();
      $graph_array['device'] = $device['device_id'];
      $graph_array['type']   = $entry;
      $graph_array['from']   = get_time('day');
      $graph_array['legend'] = "no";

      $content .= '<div style="width: 730px; white-space: nowrap;">';
      $content .= "<div class=entity-title><h4>" . $text . "</h4></div>";
      $content .= generate_graph_tag($graph_array);
      $graph_array['from']   = get_time('week');
      $content .= generate_graph_tag($graph_array);
      $content .= '</div>';

      $count++;

    }
  }

  //r($content);
  return $content;
}

// TESTME needs unit testing
// DOCME needs phpdoc block
function generate_device_link($device, $text = NULL, $vars = array(), $escape = TRUE, $short = FALSE) {

  if (is_array($device) && !($device['hostname'] && isset($device['status']))) {
    // partial device array, get full
    $device = device_by_id_cache($device['device_id']);
  } elseif (is_numeric($device)) {
    $device = device_by_id_cache($device);
  }

  if (!$device) {
    return escape_html($text);
  }
  if (!device_permitted($device['device_id'])) {
    $text = device_name($device, $short);
    return $escape ? escape_html($text) : $text;
  }

  $class = device_link_class($device);

  if (safe_empty($text)) {
    $text = device_name($device, $short);
  }

  $url = generate_device_url($device, $vars);

  if ($escape) {
    $text = escape_html($text);
  }

  return '<a href="' . $url . '" class="entity-popup ' . $class . ' text-nowrap" data-eid="' . $device['device_id'] . '" data-etype="device">' . $text . '</a>';
}

// Simple wrapper to generate_device_link() for common usage with only device_name
function generate_device_link_short($device, $vars = [], $short = TRUE) {
  // defaults - always short device name, escaped
  return generate_device_link($device, NULL, $vars, TRUE, $short);
}

function generate_device_form_values($form_filter = FALSE, $column = 'device_id', $options = array())
{
  global $cache;

  $form_items = array();
  foreach ($cache['devices']['hostname'] as $hostname => $device_id)
  {

    if (is_array($form_filter) && !in_array($device_id, $form_filter)) { continue; } // Devices only with entries

    if ($cache['devices']['id'][$device_id]['disabled'] === '1')
    {
      if (isset($options['disabled']))
      {
        // Force display disabled devices
        if (!$options['disabled']) { continue; }
      }
      elseif ($cache['devices']['id'][$device_id]['disabled'] && !$GLOBALS['config']['web_show_disabled']) { continue; }

      $form_items[$device_id]['group'] = 'DISABLED';
    }
    elseif ($cache['devices']['id'][$device_id]['status'] === '0')
    {
      if (isset($options['down']) && !$options['down']) { continue; } // Skip down

      $form_items[$device_id]['group'] = 'DOWN';
    } else {
      if (isset($options['up']) && !$options['up']) { continue; } // Skip up
      $form_items[$device_id]['group'] = 'UP';
    }
    $form_items[$device_id]['name'] = $hostname;

    if (isset($cache['devices']['id'][$device_id]['row_class'][0]))
    {
      // Set background color for non empty row_class (disabled/down/ignored)
      $form_items[$device_id]['class'] = 'bg-' . $cache['devices']['id'][$device_id]['row_class'];
    }
  }
  return $form_items;
}

// EOF
