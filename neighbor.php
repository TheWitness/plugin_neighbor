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

/**
 * Render a neighbor page with standard header/footer wrapper
 * 
 * @param callable $callback Function to call for page content
 * @return void
 */
function render_neighbor_page($callback) {
	general_header();
	neighbor_tabs();
	call_user_func($callback);
	bottom_footer();
}

set_default_action('summary');

switch(get_request_var('action')) {
	case 'neighbor_map':
	case 'maps':
		render_neighbor_page('display_interface_map');
		break;
	case 'neighbor_interface':
		render_neighbor_page('display_neighbors');
		break;
	case 'xdp':
		render_neighbor_page('display_neighbors');
		break;
	case 'neighbor_routing':
		render_neighbor_page('display_routing_neighbors');
		break;
	case 'ajax_hosts':
	case 'ajax_hosts_noany':
		get_allowed_ajax_hosts(true, false, 'h.id IN (SELECT host_id FROM plugin_neighbor_xdp)');
		break;
	case 'hoststat':
		render_neighbor_page('hosts');
		break;
	default:
		render_neighbor_page('neighbor_summary');
		break;
}

// Clear the Nav Cache, so that it doesn't know we came from Thold
$_SESSION['sess_nav_level_cache'] = '';

////
// CDP & LLDP Neighbors 
////

/**
 * Display neighbor interface listing page
 * 
 * Renders the neighbor interface view with JavaScript data tables for displaying
 * CDP/LLDP, IPv4 subnet, or interface alias-based neighbor relationships.
 * 
 * @return void Outputs HTML and JavaScript includes
 */
function display_neighbors() {
	// Validate neighbor_type against whitelist
	$neighbor_type = 'xdp';
	if (isset_request_var('neighbor_type')) {
		$type = get_request_var('neighbor_type');
		$allowed_types = array('xdp', 'ipv4', 'ifalias');
		if (in_array($type, $allowed_types, true)) {
			$neighbor_type = $type;
		}
	}
	
	print "<div id='neighbor_toolbar'></div>\n";
	print "<div id='xdp_neighbors_holder'></div>\n";
	print "<form>\n";
	print "<input type='hidden' id='table' value='xdp'>\n";
	print "</form>\n";
	printf("<link rel='stylesheet' type='text/css' href='%s'>\n", 'css/ionicons.min.css');
	printf("<script type='text/javascript' src='%s'></script>\n", 'js/tables_interface.js');
	printf("<script type='text/javascript' src='%s'></script>\n", 'js/tables_' . $neighbor_type . '.js');
}

/**
 * Display routing protocol neighbors page
 * 
 * Renders the routing neighbors view showing OSPF, BGP, and IS-IS neighbor relationships.
 * 
 * @return void Outputs HTML placeholder content
 */
function display_routing_neighbors() {
	html_start_box(__('Routing Protocol Neighbors', 'neighbor'), '100%', '', '3', 'center', '');
	
	print '<tr><td>';
	print '<div style="padding: 20px; text-align: center;">';
	print '<h3>' . __('Routing Protocol Neighbor Discovery', 'neighbor') . '</h3>';
	print '<p>' . __('This feature displays neighbors discovered via routing protocols:', 'neighbor') . '</p>';
	print '<ul style="text-align: left; display: inline-block;">';
	print '<li>' . __('OSPF (Open Shortest Path First)', 'neighbor') . '</li>';
	print '<li>' . __('BGP (Border Gateway Protocol)', 'neighbor') . '</li>';
	print '<li>' . __('IS-IS (Intermediate System to Intermediate System)', 'neighbor') . '</li>';
	print '</ul>';
	print '<p><em>' . __('Note: Routing protocol discovery must be enabled in Settings and configured on your devices.', 'neighbor') . '</em></p>';
	print '</div>';
	print '</td></tr>';
	
	html_end_box();
}

// Summary Action

/**
 * Display neighbor discovery summary statistics
 * 
 * Shows aggregated counts of discovered neighbors by protocol type.
 * 
 * @return void Outputs HTML summary table
 */
function neighbor_summary() {
	/* ================= input validation ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'method',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);
	validate_store_request_vars($filters, 'sess_neighbor');
	/* ================= end input validation ================= */
	
	$total_rows = 0;
	$xdpNeighborStats = getXdpNeighborStats($total_rows);
	
	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}
	
	html_start_box('Neighbor Summary', '50%', '', '4', 'left', '');

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
}




