<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage graphs
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2020 Observium Limited
 *
 */

$i = 0;

foreach (dbFetchRows("SELECT * FROM `processors` AS P, devices AS D WHERE D.device_id = P.device_id") as $proc)
{
  $rrd_filename = get_rrd_path($device, "processor-" . $proc['processor_type'] . "-" . $proc['processor_index'] . ".rrd");

  if (rrd_is_file($rrd_filename))
  {
    $descr = rewrite_entity_name($proc['processor_descr'], 'processor');

    $rrd_list[$i]['filename'] = $rrd_filename;
    $rrd_list[$i]['descr'] = $descr;
    $rrd_list[$i]['ds'] = "usage";
    $rrd_list[$i]['area'] = 1;
    $i++;
  }
}

$unit_text = "Load %";

$units = '%';
$total_units = '%';
$colours ='mixed';

$scale_min = "0";
$scale_max = "100";

$nototal = 1;

include($config['html_dir']."/includes/graphs/generic_multi_line.inc.php");

?>
