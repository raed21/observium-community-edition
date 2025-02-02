<?php
/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage graphs
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2022 Observium Limited
 *
 */

$i = 0;

foreach ((array) $vars['id'] as $ifid) {
  $port = dbFetchRow("SELECT * FROM `ports` AS I, devices AS D WHERE I.port_id = ? AND I.device_id = D.device_id", array($ifid));
  $rrdfile = get_port_rrdfilename($port, NULL, TRUE);
  if (rrd_is_file($rrdfile, TRUE)) {
    //humanize_port($port);
    $rrd_list[$i]['filename']  = $rrdfile;
    $rrd_list[$i]['descr']     = $port['hostname'] . " " . $port['ifDescr'];
    $rrd_list[$i]['descr_in']  = $port['hostname'];
    $rrd_list[$i]['descr_out'] = $port['port_label_short'];
    $i++;
  }
}

$units = 'b';
$total_units='B';
$colours_in='greens';
$multiplier = "8";
$colours_out = 'blues';

$ds_in  = "INOCTETS";
$ds_out = "OUTOCTETS";

include($config['html_dir']."/includes/graphs/generic_multi_bits_separated.inc.php");

// EOF
