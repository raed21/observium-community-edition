<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage web
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2023 Observium Limited
 *
 */

function build_table($array, $options = []) {

  // start table
  $html = '<table class="table table-condensed table-striped">';

  // header row
  if (isset($options['columns'])) {
    $html .= get_table_header($options['columns']);
    unset($options['columns']);
  } else {
    $html .= '<thead><tr>';
    foreach ($array[0] as $key => $value) {
      $html .= '<th>' . escape_html(nicecase($key)) . '</th>';
    }
    $html .= '</tr></thead>';
  }

   // data rows
   foreach($array as $key => $value) {
      $html .= '<tr>';
      foreach($value as $key2 => $value2) {
        // Entry defaults
        $escape = TRUE;
        $style  = '';
        $class  = '';

        if (isset($options[$key2])) {
          $options2 = $options[$key2];
          if (is_array($options2)) {
            $type = $options2['type'];
            // Allow extra options
            if (isset($options2['escape'])) {
              $escape = $options2['escape'];
            }
            if (isset($options2['style'])) {
              $style = $options2['style'];
            }
            if (isset($options2['class'])) {
              $class = $options2['class'];
            }
          } else {
            $type = $options2;
          }
        } else {
          $type = NULL;
        }
        switch ($type) {
          case 'unixtime':
            $value2 = format_unixtime($value2);
            break;
          case 'device':
            $value2 = generate_device_link($value2);
            break;
          case 'json':
            $json = safe_json_encode(safe_json_decode($value2), JSON_PRETTY_PRINT);
            $value2 = '<pre>'.$json.'</pre>';
            break;
          default:
            if ($escape) {
              $value2 = escape_html($value2);
            }
        }
        if ($style || $class) {
          $value2 = '<span class="'.$class.'" style="'.$style.'">'.$value2.'</span>';
        }
        $html .= '<td>' . $value2 . '</td>';
      }
      $html .= '</tr>';
   }

   // finish table and return it

   $html .= '</table>';
   return $html;
}

function build_table_row($array, $options = [])
{
  // Calculate max columns
  $cols = 0;
  foreach ($array as $entry)
  {
    $cols = max(safe_count($entry), $cols);
  }

  // start table
  $html = '<table class="table table-condensed table-striped">';
  // header row
  /*
  $html .= '<thead><tr>';
  foreach($array[0] as $key => $value)
  {
    $html .= '<th>' . $key . '</th>';
  }
  $html .= '</tr></thead>';
  */

  // data rows
  foreach($array as $key => $entry)
  {
    $html .= '<tr>';
    $html .= '<td><strong>' . $key . '</strong></td>';
    $values = array_values((array)$entry);

    for ($i = 0; $i <= $cols; $i++)
    {
      $value = $values[$i];
      switch ($options[$key])
      {
        case 'unixtime':
          $value = format_unixtime($value);
          break;
        case 'device':
          $value = generate_device_link($value);
          break;
      }
      $html .= '<td>' . $value . '</td>';
    }
    $html .= '</tr>';
  }

  // finish table and return it

  $html .= '</table>';
  return $html;
}

/**
 * Print refresh meta header
 * 
 * This function print refresh meta header and return status for current page
 * with refresh time, list and allowed refresh times.
 * Uses variables $vars['refresh'], $_SESSION['refresh'], $config['page_refresh']
 *
 * @global string $GLOBALS['config']['page_refresh']
 * @global string $_SESSION['refresh']
 * @param array $vars
 * @return array $return
 */
function print_refresh($vars)
{
  if (!$_SESSION['authenticated'])
  {
    // Do not print refresh header if not authenticated session, common use in logon page
    return array('allowed' => FALSE);
  }

  $refresh_array = $GLOBALS['config']['wui']['refresh_times']; // Allowed refresh times
  $refresh_time  = 300;                               // Default page reload time (5 minutes)
  if (isset($vars['refresh']))
  {
    if (is_numeric($vars['refresh']) && in_array($vars['refresh'], $refresh_array))
    {
      $refresh_time = (int)$vars['refresh'];
      // Store for SESSION
      session_set_var('page_refresh', $refresh_time);
    }
    // Unset refresh var after
    unset($GLOBALS['vars']['refresh']);
  }
  else if (isset($_SESSION['page_refresh']) && in_array($_SESSION['page_refresh'], $refresh_array))
  {
    $refresh_time = (int)$_SESSION['page_refresh'];
  }
  else if (is_numeric($GLOBALS['config']['page_refresh']) && in_array($GLOBALS['config']['page_refresh'], $refresh_array))
  {
    $refresh_time = (int)$GLOBALS['config']['page_refresh'];
  }

  // List vars where page refresh full disabled - FIXME move to definitions!
  $refresh_disabled = $GLOBALS['config']['wui']['refresh_disabled'];

  $refresh_allowed = TRUE;
  foreach ($refresh_disabled as $var_test)
  {
    $var_count = safe_count($var_test);
    foreach ($var_test as $key => $value)
    {
      if (isset($vars[$key]) && $vars[$key] == $value) { $var_count--; }
    }
    if ($var_count === 0)
    {
      $refresh_allowed = FALSE;
      break;
    }
  }

  $return = array('allowed' => $refresh_allowed,
                  'current' => $refresh_time,
                  'list'    => $refresh_array);

  if ($refresh_allowed && $refresh_time)
  {
    register_html_resource('meta', array('http-equiv' => 'refresh', 'content' => $refresh_time));
    //echo('  <meta http-equiv="refresh" content="'.$refresh_time.'" />' . "\n");
    $return['nexttime'] = time() + $refresh_time; // Add unixtime for next refresh
  }

  return $return;
}

/**
 * This generates an HTML <thead> element based on the contents of the $header array, modified by the current request $vars
 *
 * @param array $header Array with table header definition including columns and classes.
 * @param array $vars Array with current selected column ID and/or variables for generate column link
 * @return string $string
 */

function generate_table_header($header = [], $vars = []) {

  // Store current $vars sort variables
  $sort       = $vars['sort'];
  $sort_order = strtolower($vars['sort_order']);
  if (!in_array($sort_order, [ 'asc', 'desc', 'reset' ])) {
    $sort_order = 'asc';
  }

  // Reset current $vars sort variables
  unset($vars['sort'], $vars['sort_order']);

  $output = '<thead'.(isset($header['class']) ? ' class="'.$header['class'].'"' : '').
                     (isset($header['style']) ? ' style="'.$header['style'].'"' : '').
                     (isset($vars['show_header']) && !$vars['show_header'] ? 'style="line-height: 0; visibility: collapse;"' : '') . '>';

  $output .= '    <tr>' . PHP_EOL;

  //r($header);

  // Loop each column generating a <th> element
  foreach($header as $id => $col) {

     //if (in_array($id, ['class', 'group', 'style'])) { continue; } // skip html metadata

     if ($id === 'class' ||  $id === 'style') { continue; } // skip html data

     $fields = []; // Empty array for fields

     if (empty($col) || !is_array($col)) { $col = [ $id => $col ]; } // If col is not an array, make it one

     if ($id === 'state-marker') { $col['class'] = 'state-marker'; }  // Hard code handling of state-marker

     // Loop each field and generate an <a> element
     foreach ($col as $field_id => $field) {

        if ($field_id === 'class' ||  $field_id === 'style' || $field_id === 'subfields') { continue; } // skip html data

        $header_field = generate_table_header_field($field_id, $field, $vars, $sort, $sort_order);

        if (!safe_empty($header_field)) {
          $fields[] = $header_field;
        }

     }
     $output .= '      <th'.( isset($col['class']) ? ' class="'.$col['class'].'"' : '').'>';
     $output .= implode(' / ', $fields);
     $output .= '</th>' . PHP_EOL;
  }

  $output .= '    </tr>' . PHP_EOL;
  $output .= '  </thead>' . PHP_EOL;

  return $output;


}

function generate_table_header_field($field_id, $field, $vars, $sort, $sort_order) {

  if (empty($field)) {                                  // No label, generate empty column header.
    $return = '';
  } elseif ( is_numeric($field_id) && !is_array($field)) {   // Label without id, generate simple column header
    $return = $field;
  } else {
    if (!is_array($field)) { $field = [ 'label' => $field ]; }
    if (!isset($field['label'])) { $field['label'] = $field[0]; }

    if ($sort == $field_id) {
      $field['label'] = '<span class="text-primary" style="font-style: italic">'.$field['label'].'</span>';
      if ($sort_order === 'asc') {
        $new_vars = [ 'sort' => $field_id, 'sort_order' => 'desc' ];
        $field['caret'] = '&nbsp;<i class="text-primary small glyphicon glyphicon-arrow-up"></i>';
      } else {
        $new_vars = [ 'sort' => NULL, 'sort_order' => NULL ];
        $field['caret'] = '&nbsp;<i class="text-primary small glyphicon glyphicon-arrow-down"></i>';
      }
    } else {
      $new_vars = [ 'sort' => $field_id ];
    }
    $return = '<a href="'.generate_url($vars, $new_vars).'">'.$field['label'].$field['caret'].'</a>';

    // Generate slash separated links for subfields
    if (isset($field['subfields'])) {
      $subfields = [];
      foreach($field['subfields'] as $subfield_id => $subfield) {
        //r($subfield); r($subfield_id);
        $subfields[] = generate_table_header_field($subfield_id, $subfield, $vars, $sort, $sort_order);
      }
      $return .= ' ['.implode(" / ", $subfields).']';
    }
  }

  return $return;

}

/**
 * Helper function for generate table header with sort links
 * This used in other print_* functions
 *
 * @param array $cols Array with column IDs, names and column styles
 * @param array $vars Array with current selected column ID and/or variables for generate column link
 * @return string $string
 */
function get_table_header($cols, $vars = array()) {
  // Always clean sort vars
  $sort       = $vars['sort'];
  $sort_order = strtolower($vars['sort_order']);
  if (!in_array($sort_order, array('asc', 'desc', 'reset'))) {
    $sort_order = 'asc';
  }
  unset($vars['sort'], $vars['sort_order']);

  if (isset($vars['show_header']) && !$vars['show_header']) {
    // Do not show any table header if show_header == FALSE
    $string  = '  <thead style="line-height: 0; visibility: collapse;">' . PHP_EOL;
  } else {
    $string  = '  <thead>' . PHP_EOL;
  }
  $string .= '    <tr>' . PHP_EOL;
  foreach ($cols as $id => $col) {
    if (is_array($col)) {
      $name  = $col[0];
      $style = ' '.$col[1]; // Column styles/classes
    } else {
      $name  = $col;
      $style = '';
    }
    $string .= '      <th'.$style.'>';
    if ($name == NULL) {
      $string .= '';         // Column without Name and without Sort
    } elseif (is_intnum($id) || str_contains($id, "!")) {
      $string .= $name;      // Column without Sort
    } elseif (!empty($vars) || $sort) {
      // Sort order cycle: asc -> desc -> reset
      if ($sort == $id) {
        switch ($sort_order) {
          case 'desc':
            $name .= '&nbsp;&nbsp;<i class="small glyphicon glyphicon-triangle-top"></i>';
            $sort_array = array();
            //$vars['sort_order'] = 'reset';
            break;
          case 'reset':
            //unset($vars['sort'], $vars['sort_order']);
            $sort_array = array();
            break;
          default:
            // ASC
            $name .= '&nbsp;&nbsp;<i class="small glyphicon glyphicon-triangle-bottom"></i>';
            $sort_array = array('sort' => $id, 'sort_order' => 'desc');
            //$vars['sort_order'] = 'desc';
        }
      } else{
        $sort_array = array('sort' => $id);
      }
      $string .= '<a href="'. generate_url($vars, $sort_array).'">'.$name.'</a>'; // Column now sorted (selected)
    } else {
      $string .= $name;      // Sorting is not available (if vars empty or FALSE)
    }
    $string .= '</th>' . PHP_EOL;
  }
  $string .= '    </tr>' . PHP_EOL;
  $string .= '  </thead>' . PHP_EOL;

  return $string;
}

function print_button_group($data, $return = FALSE) {
  $data = [
    'id'    => 'rule_actions',
    'name'  => 'Rule actions',
    'size'  => 'xs', // xs, sm, lg
    'class' => ''
  ];
  $data['rows'][0][] = '';

  $button_group_start = '      <div';
  if ($data['id']) {
    $button_group_start .= ' id="' . $data['id'] . '"';
  }
  if ($data['name']) {
    $button_group_start .= ' aria-label="' . $data['name'] . '"';
  }

  $button_group_class = 'btn-group';
  if ($data['size']) {
    $button_group_class .= ' btn-group-' . $data['size'];
  }
  if ($data['class']) {
    $button_group_class .= ' ' . $data['class'];
  }
  $button_group_start .= ' class="' . $button_group_class . '"';
  $button_group_start .= ' role="group">' . PHP_EOL;

  /*
      <div class="btn-group btn-group-xs" role="group" aria-label="Rule actions">
        <a class="btn btn-default" role="group" title="Edit" href="#modal-edit_syslog_rule_'.$la['la_id'].'" data-toggle="modal"><i class="icon-cog text-muted"></i></a>
        <a class="btn btn-danger"  role="group" title="Delete" href="#modal-delete_syslog_rule_'.$la['la_id'].'" data-toggle="modal"><i class="icon-trash"></i></a>
      </div>
   */
  $button_group_end = '      </div>' . PHP_EOL;

  if ($return) {
    return $button_group_start . $button_group . $button_group_end;
  }
  echo $button_group_start . $button_group . $button_group_end;
}

function print_error_permission($text = NULL, $escape = TRUE)
{
  if (empty($text))
  {
    $text = 'You have insufficient permissions to view this page.';
  }
  else if ($escape)
  {
    $text = escape_html($text);
  }
  echo('<div style="margin:auto; text-align: center; margin-top: 50px; max-width:600px">');
  print_error('<h4>Permission error</h4>' . PHP_EOL . $text);
  echo('</div>');
}

/**
 * Generate html with label group
 *
 * @param array $params List of button items
 * @param array $opt Array with group params and styles
 * @param bool $escape Escape or not Item text
 *
 * @return string Generated html
 */
function get_label_group($params = [], $opt = [], $escape = TRUE) {
  $html = '<span class="label-group"';
  if ($opt['style']) {
    $html .= ' style="' . $opt['style'] . '"';
  }
  $html .= '>' . PHP_EOL;

  //$items_count = count($params);
  $html_params = [];
  foreach ($params as $param_id => $param)
  {
    // If param is just string, convert to simple group
    if (is_string($param))
    {
      $param = ['text' => $param, 'event' => 'default'];
    }

    //$html_param = '<span id="'.$param_id.'"'; // open span
    $html_param = '<div id="'.$param_id.'"'; // open div, I use div for fix display label group in navbar
    // Item style
    if ($param['style']) {
      $html_param .= ' style="' . $param['style'] . '"';
    }
    // Item class
    if ($param['event']) {
      $class = 'label label-'.$param['event'];
    } else {
      $class = '';
    }
    if ($param['class']) {
      $class .= ' '.$param['class'];
    }
    if ($class) {
      $html_param .= ' class="' . $class . '"';
    } else {
      // Default
      $html_param .= ' class="label label-default"';
    }
    // Icons?
    // any custom data attribs?
    $html_param .= '>';
    // Item text
    if ($param['text']) {
      $html_param .= (bool)$escape ? escape_html($param['text']) : $param['text'];
    }
    //$html_param .= '</span>'; // close span
    $html_param .= '</div>'; // close div

    $html_params[] = $html_param;
  }

  // Return single label (without group), since label group for single item is incorrect
  if (safe_count($params) === 1) {
    return array_shift($html_params);
  }

  $html .= implode('', $html_params) . PHP_EOL;
  $html .= '</span>'. PHP_EOL;

  return $html;
}

/**
 * Generate html with button group
 *
 * @param array $params List of button items
 * @param array $opt Array with group params and styles
 * @param bool $escape Escape or not Item text
 *
 * @return string Generated html
 */
function get_button_group($params = [], $opt = [], $escape = TRUE) {
  $html = '<div class="btn-group"';
  if ($opt['style']) {
    $html .= ' style="' . $opt['style'] . '"';
  }
  $html .= '>' . PHP_EOL;

  $html_params = [];
  foreach ($params as $param_id => $param)
  {
    // If param is just string, convert to simple group
    if (is_string($param))
    {
      $param = ['text' => $param, 'event' => 'default'];
    }

    $html_param = ' <div id="'.$param_id.'"'; // open span
    // Item style
    if ($param['style']) {
      $html_param .= ' style="' . $param['style'] . '"';
    }
    // Item class
    if ($param['event']) {
      $class = 'btn btn-'.$param['event'];
    } else {
      $class = '';
    }
    if ($param['size']) {
      $class .= ' btn-'.$param['size'];
    }
    if ($param['class']) {
      $class .= ' '.$param['class'];
    }
    if ($class) {
      $html_param .= ' class="' . $class . '"';
    } else {
      // Default
      $html_param .= ' class="btn btn-default"';
    }
    // Icons?
    // any custom data attribs?
    $html_param .= '>';
    // Item text
    if ($param['text']) {
      $html_param .= (bool)$escape ? escape_html($param['text']) : $param['text'];
    }
    $html_param .= '</div>'; // close span

    $html_params[] = $html_param;
  }

  $html .= implode(PHP_EOL, $html_params) . PHP_EOL;
  $html .= '</div>'. PHP_EOL;

  return $html;
}

/**
 * Generate html by Markdown formatted text.
 *
 * @param string $markdown Markdown formatted text
 * @param bool   $escape   Escape html entities
 * @param bool   $extra    Allow Extra Markdown syntax
 *
 * @return string HTML formatted text
 */
function get_markdown($markdown, $escape = TRUE, $extra = FALSE) {
  if ($extra) {
    // Allow Extra Markdown syntax
    //$parsedown = new ParsedownExtra();
    $parsedown = new ParsedownExtraPlugin();
  } else {
    $parsedown = new Parsedown();
  }
  if (str_contains_array($markdown, [ "\n", "\r" ])) {
    // Multiline texts must use text()
    $html = $parsedown
      ->setMarkupEscaped($escape) # escapes markup (HTML)
      ->setBreaksEnabled(TRUE) # enables automatic line breaks
      ->text($markdown);

    return '<div style="min-width: 150px;">'.PHP_EOL.$html.PHP_EOL.'</div>';
  }

  // Single line (used for messages, eventlogs)
  $html = $parsedown
    ->setMarkupEscaped($escape) # escapes markup (HTML)
    ->setBreaksEnabled(TRUE) # enables automatic line breaks
    ->line($markdown);

  //print_vars($html);
  return '<span style="min-width: 150px;">' . $html . '</span>';
}

/**
 * Generate icon html tag
 *
 * @param string $icon  Icon name in definitions (ie: flag) or by css class (ie: sprite-flag)
 * @param string $class Additional class(es) for changing main icon view
 * @param array  $attribs Url/link extended attributes (ie data-*, class, style)
 *
 * @return string HTML icon tag like <i class="sprite-flag"></i> or emoji style :flag-us: -> <span class="icon-emoji">&#x1F1FA;&#x1F1F8;</span>
 */
function get_icon($icon, $class = '', $attribs = []) {
  global $config;

  // Passed already html icon tag, return as is
  if (str_contains_array($icon, [ '<', '>' ])) { return $icon; }

  $icon = trim(strtolower($icon));
  if (isset($config['icon'][$icon])) {
    // Defined icons
    $icon = $config['icon'][$icon];
  } elseif (!strlen($icon)) {
    // Empty icon, return empty string
    return '';
  }

  // Emoji styled icons, ie :flag-us:
  if (preg_match('/^:[\w\-_]+:$/', $icon) || is_intnum($icon)) {
    // icon-emoji is pseudo class, for styling emoji as other icons
    return '<span class="icon-emoji">' . get_icon_emoji($icon) . '</span>';
  }

  // Append glyphicon main class if these icons used
  if (str_starts($icon, 'glyphicon-')) {
    $icon = 'glyphicon '.$icon;
  }

  if ($class) {
    // Additional classes
    $attribs['class'] = array_merge((array)$class, (array)$attribs['class']);
  }
  $attribs['class'] = array_merge([ $icon ], (array)$attribs['class']);

  return '<i ' . generate_html_attribs($attribs) . '></i>';
}

/**
 * Generate emoji icon (ie html hex entity)
 *
 * @param string $emoji Emoji name in definitions (ie: flag-us or :flag-us:)
 * @param string $type  Type of emoji for return (currently only html supported)
 * @param array  $attribs Url/link extended attributes (currently unused)
 *
 * @return string Emoji in requested type, for html ie: :flag-us: -> &#x1F1FA;&#x1F1F8;
 */
function get_icon_emoji($emoji, $type = 'html', $attribs = []) {
  global $config;

  // Emoji definitions not loaded by default!
  // Load of first request
  if (!isset($config['emoji']['zero'])) {
    include_once $config['install_dir'] . '/includes/definitions/emoji.inc.php';
  }

  $emoji_name = strtolower(trim($emoji, ": \t\n\r\0\x0B"));

  if (is_intnum($emoji)) {
    // convert int number string to emoji number
    $return = '';
    foreach (str_split($emoji) as $num) {
      switch ($num) {
        case '0': $return .= get_icon_emoji(':zero:',  $type, $attribs); break;
        case '1': $return .= get_icon_emoji(':one:',   $type, $attribs); break;
        case '2': $return .= get_icon_emoji(':two:',   $type, $attribs); break;
        case '3': $return .= get_icon_emoji(':three:', $type, $attribs); break;
        case '4': $return .= get_icon_emoji(':four:',  $type, $attribs); break;
        case '5': $return .= get_icon_emoji(':five:',  $type, $attribs); break;
        case '6': $return .= get_icon_emoji(':six:',   $type, $attribs); break;
        case '7': $return .= get_icon_emoji(':seven:', $type, $attribs); break;
        case '8': $return .= get_icon_emoji(':eight:', $type, $attribs); break;
        case '9': $return .= get_icon_emoji(':nine:',  $type, $attribs); break;
      }
    }
    return $return;
  }

  // Unknown emoji name, return original string
  if (!isset($config['emoji'][$emoji_name])) {
    return $emoji;
  }
  $type  = strtolower($type);
  $entry = $config['emoji'][$emoji_name];

  switch ($type) {
    case 'unified':
    case 'non_qualified':
      $return = escape_html($entry[$type]);
      break;
    case 'html':
    default:
      // 26A0-FE0F -> &#x26A0;&#xFE0F;
      $emoji_html = explode('-', escape_html($entry['unified'])); // escaping for prevent pass to definitions html code
      $return = '&#x' . implode(';&#x', $emoji_html) . ';';
  }

  return $return;
}

/**
 * Generate icon html tag with country flag
 *
 * @param string $country Country name or code
 *
 * @return string HTML icon tag like <i class="flag flag-us"></i>
 */
function get_icon_country($country)
{
  global $config;

  // Unificate country name
  $country = country_from_code($country);
  // Find ISO 2 country code (must be first in definitions)
  $code = strtolower(array_search($country, $config['rewrites']['countries']));
  if (empty($code))
  {
    return get_icon('location');
  }

  return '<i class="flag flag-'.$code.'"></i>';
}

function print_json_status($status, $message = '', $array = []) {
  if (!in_array($status, [ 'ok', 'failed', 'warning' ])) {
    $status = 'failed';
  }

  $return = [ 'status' => $status, 'message' => $message ];
  if (safe_count($array)) {
    if (isset($array['message']) && empty($message)) {
      // prefer not empty message
      unset($return['message']);
    }
    $return = array_merge($return, $array);
  }

  header('Content-Type: application/json');
  print safe_json_encode($return);
}

// EOF
