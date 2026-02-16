<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/


$guest_account = true;

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/neighbor/lib/neighbor_functions.php');

/*
include_once($config['base_path'] . '/plugins/neighbor/neighbor_functions.php');
include_once($config['base_path'] . '/plugins/neighbor/setup.php');
include_once($config['base_path'] . '/plugins/neighbor/includes/database.php');
include($config['base_path'] . '/plugins/neighbor/includes/arrays.php');

neighbor_initialize_rusage();

plugin_neighbor_upgrade();

delete_old_thresholds();
*/

set_default_action('summary');

switch(get_request_var('action')) {
	case 'neighbor_map':
		general_header();
		neighbor_tabs();
		display_interface_map();
		bottom_footer();
		break;
	case 'neighbor_interface':
		general_header();
		neighbor_tabs();
		display_neighbors();
		bottom_footer();
		break;
	case 'xdp':
		general_header();
		neighbor_tabs();
		show_xdp_neighbors();
		bottom_footer();
		break;
	case 'maps':
		general_header();
		neighbor_tabs();
		display_interface_map();
		bottom_footer();
		break;
	case 'ajax_hosts':
		get_allowed_ajax_hosts(true, false, 'h.id IN (SELECT host_id FROM plugin_neighbor_xdp)');
		break;
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(true, false, 'h.id IN (SELECT host_id FROM plugin_neighbor_xdp)');
		break;
	case 'hoststat':
		general_header();
		neighbor_tabs();
		hosts();
		bottom_footer();
		break;
	default:
		general_header();
		neighbor_tabs();
		display_interface_map();
		bottom_footer();

		break;
}

// Clear the Nav Cache, so that it doesn't know we came from Thold
$_SESSION['sess_nav_level_cache'] = '';

////
// CDP & LLDP Neighbors 
////

function display_neighbors() {
	
	$neighbor_type = get_request_var('neighbor_type') ? get_request_var('neighbor_type') : 'xdp';
	
	print "<div id='neighbor_toolbar'></div>\n";
	print "<div id='xdp_neighbors_holder'></div>\n";
	print "<form>";
	print "<input type='hidden' id='table' value='xdp'>";
	print "</form>";
	printf("<link rel='stylesheet' type='text/css' href='%s'>",'css/ionicons.min.css');
	printf("<script type='text/javascript' src='%s'></script>",'js/tables_interface.js');
	printf("<script type='text/javascript' src='%s'></script>",'js/tables_'.$neighbor_type.'.js');
	
}

// Summary Action

function neighbor_summary() {
	
	$total_rows = 0;
	$rows = 1;
	
	$xdpNeighborStats = getXdpNeighborStats($total_rows);
	//print "<pre>".print_r($xdpNeighborStats,1)."</pre>";
	
	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}
	
	print 
	html_start_box('Neighbor Summary', '50%','' , '4', 'left', '');

	$display_text = array(
		'method'         => array('display' => __('Method', 'neighbor'),     	'sort' => '',	'align' => 'left'),
		'hosts'          => array('display' => __('Hosts', 'neighbor'),        	'sort' => '',  	'align' => 'center'),
		'interfaces'     => array('display' => __('Interfaces', 'neighbor'),    'sort' => '', 	'align' => 'center'),
		'last_polled'    => array('display' => __('Last Polled', 'neighbor'),   'sort' => '',  	'align' => 'center'));
	
	
	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'neighbor.php?action=summary');
	if ($xdpNeighborStats) {
		printf("<tr><td><a href='?action=xdp'>CPD/LLDP</a></td><td align='center'> %s </td><td align='center'> %s </td><td align='center'> %s </td>",$xdpNeighborStats['hosts'],$xdpNeighborStats['interfaces'],$xdpNeighborStats['last_polled']);
		form_end_row();
	}
	html_end_box();
	print $nav;
	
}




