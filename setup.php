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

include_once($config['base_path'] . '/lib/data_query.php');

/**
 * Register plugin hooks and initialize plugin during installation
 *
 * Sets up all necessary hooks for configuration, device management, and polling.
 * Creates database tables and registers plugin realms for access control.
 *
 * @return void
 */
function plugin_neighbor_install() {
	global $config;

	// graph setup all arrays needed for automation
	api_plugin_register_hook('neighbor', 'config_arrays',         	'neighbor_config_arrays',         	'setup.php');
	api_plugin_register_hook('neighbor', 'config_form',           	'neighbor_config_form',           	'setup.php');
	api_plugin_register_hook('neighbor', 'config_settings',       	'neighbor_config_settings',       	'setup.php');
	api_plugin_register_hook('neighbor', 'draw_navigation_text',  	'neighbor_draw_navigation_text',  	'setup.php');
	api_plugin_register_hook('neighbor', 'poller_output', 		  	'neighbor_poller_output', 			'lib/polling.php');
	api_plugin_register_hook('neighbor', 'poller_bottom', 		  	'process_poller_deltas',         	'lib/polling.php');
	api_plugin_register_hook('neighbor', 'poller_bottom',         	'neighbor_poller_bottom',         	'setup.php');
	api_plugin_register_hook('neighbor', 'top_header_tabs',       	'neighbor_show_tab',              	'setup.php');
	api_plugin_register_hook('neighbor', 'top_graph_header_tabs', 	'neighbor_show_tab',              	'setup.php');

	// device actions and interaction
	api_plugin_register_hook('neighbor', 'api_device_save', 'neighbor_api_device_save', 'setup.php');
	api_plugin_register_hook('neighbor', 'device_action_array', 'neighbor_device_action_array', 'setup.php');
	api_plugin_register_hook('neighbor', 'device_action_execute', 'neighbor_device_action_execute', 'setup.php');
	api_plugin_register_hook('neighbor', 'device_action_prepare', 'neighbor_device_action_prepare', 'setup.php');
	api_plugin_register_hook('neighbor', 'device_remove', 'neighbor_device_remove', 'setup.php');

	api_plugin_register_realm('neighbor', 'neighbor.php,ajax.php', __('Plugin -> Neighbors'), 1);
	api_plugin_register_realm('neighbor', 'neighbor_rules.php,neighbor_tree_rules.php,neighbor_graph_rules.php,neighbor_vrf_rules.php', __('Plugin -> Configure Neighbor Rules', 'thold'), 1);

	include_once($config['base_path'] . '/plugins/neighbor/lib/neighbor_sql_tables.php');

	neighbor_setup_table();
}

/**
 * Clean up plugin resources during uninstallation
 *
 * Drops all plugin database tables. Settings are automatically removed by Cacti.
 *
 * @return void
 */
function plugin_neighbor_uninstall() {
	// Drop all plugin tables
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_xdp`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_ipv4`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_ipv4_cache`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_processes`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_log`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_edge`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_poller_output`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_poller_delta`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_user_map`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_host`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_rules`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_graph_rules`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_graph_rule_items`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_match_rule_items`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_tree_rules`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_tree_rule_items`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_vrf_rules`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_vrf_rule_items`');
	db_execute('DROP TABLE IF EXISTS `plugin_neighbor_vrf_match_rule_items`');
}

/**
 * Verify plugin configuration is valid
 *
 * Runs upgrade check to ensure database schema is current.
 *
 * @return bool Always returns true
 */
function plugin_neighbor_check_config() {
	// Here we will check to ensure everything is configured
	neighbor_check_upgrade();

	return true;
}

/**
 * Handle plugin upgrades
 *
 * Triggers upgrade check to apply any necessary database schema changes.
 *
 * @return bool Always returns true
 */
function plugin_neighbor_upgrade() {
	// Here we will upgrade to the newest version
	neighbor_check_upgrade();

	return true;
}

/**
 * Get plugin version information
 *
 * Reads version details from the INFO file.
 *
 * @return array Plugin information from INFO file
 */
function plugin_neighbor_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/neighbor/INFO', true);

	return $info['info'];
}

/**
 * Check and perform database schema upgrades
 *
 * Compares installed version with current version and creates/updates
 * database tables as needed. Only runs on specific plugin pages to avoid
 * unnecessary overhead.
 *
 * @return void
 */
function neighbor_check_upgrade() {
	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');
	include_once($config['library_path'] . '/functions.php');

	// Let's only run this check if we are on a page that actually needs the data
	$files = ['plugins.php', 'neighbor.php', 'neighbor_rules.php', 'neighbor_graph_rules.php', 'neighbor_tree_rules.php', 'neighbor_vrf_rules.php'];

	if (!in_array(get_current_page(), $files, true)) {
		return;
	}

	$info    = plugin_neighbor_version();
	$current = $info['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='neighbor'");

	$has_xdp_table                  = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_xdp'");
	$has_rules_table                = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_rules'");
	$has_user_map_table             = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_user_map'");
	$has_edge_table                 = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_edge'");
	$has_poller_output_table        = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_poller_output'");
	$has_poller_delta_table         = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_poller_delta'");
	$has_processes_table            = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_processes'");
	$has_log_table                  = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_log'");
	$has_vrf_rules_table            = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_vrf_rules'");
	$has_vrf_rule_items_table       = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_vrf_rule_items'");
	$has_vrf_match_rule_items_table = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_vrf_match_rule_items'");
	$has_host_table                 = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_host'");
	$has_neighbor_type              = false;
	$has_neighbor_options           = false;

	if ($has_rules_table) {
		$has_neighbor_type    = db_fetch_cell("SHOW COLUMNS FROM plugin_neighbor_rules LIKE 'neighbor_type'");
		$has_neighbor_options = db_fetch_cell("SHOW COLUMNS FROM plugin_neighbor_rules LIKE 'neighbor_options'");
	}

	if (!$has_xdp_table || !$has_rules_table || !$has_user_map_table || !$has_edge_table || !$has_poller_output_table || !$has_poller_delta_table || !$has_processes_table || !$has_log_table || !$has_vrf_rules_table || !$has_vrf_rule_items_table || !$has_vrf_match_rule_items_table || !$has_host_table || !$has_neighbor_type || !$has_neighbor_options) {
		include_once($config['base_path'] . '/plugins/neighbor/lib/neighbor_sql_tables.php');
		neighbor_setup_table();
	}

	if ($current != $old) {
		if (api_plugin_is_enabled('neighbor')) {
			// may sound ridiculous, but enables new hooks
			api_plugin_enable_hooks('neighbor');
		}

		db_execute_prepared("UPDATE plugin_config
			SET version=?
			WHERE directory='neighbor'", [$current]);

		db_execute_prepared('UPDATE plugin_config
			SET version=?, name=?, author=?, webpage=?
			WHERE directory=?',
			[$info['version'], $info['longname'], $info['author'], $info['homepage'], $info['name']]);
	}
}

/**
 * Check for required plugin dependencies
 *
 * @return bool Always returns true (no dependencies required)
 */
function neighbor_check_dependencies() {
	return true;
}

/**
 * Execute neighbor discovery during poller cycle
 *
 * Processes poller deltas and launches the neighbor discovery script.
 * Called by Cacti's poller_bottom hook.
 *
 * @return void
 */
function neighbor_poller_bottom() {
	global $config;
	include_once($config['base_path'] . '/plugins/neighbor/lib/polling.php');
	process_poller_deltas();
	exec_background(read_config_option('path_php_binary'), ' -q ' . $config['base_path'] . '/plugins/neighbor/poller_neighbor.php -M');
}

/**
 * Register plugin configuration settings
 *
 * Defines all configuration options available in Cacti's settings page for
 * neighbor discovery, including protocol selection, polling frequency, and
 * subnet correlation settings.
 *
 * @return void
 */
function neighbor_config_settings() {
	global $tabs, $settings, $neighbor_frequencies, $item_rows;

	$tabs['neighbor']     = 'Neighbor';
	$settings['neighbor'] = [
		'neighbor_global_header' => [
			'friendly_name' => __('Device Neighbor Settings'),
			'method'        => 'spacer',
		],
		'neighbor_global_enabled' => [
			'friendly_name' => __('Poller Enabled'),
			'description'   => __('Check this box to enable polling of neighbors.'),
			'method'        => 'checkbox',
			'default'       => 'on'
		],
		'neighbor_global_discover_header' => [
			'friendly_name' => __('Neighbor Discovery'),
			'method'        => 'spacer',
		],
		'neighbor_global_discover_cdp' => [
			'friendly_name' => __('CDP Neighbors'),
			'description'   => __('Discover Cisco Discovery Protocol neighbors'),
			'method'        => 'checkbox',
			'default'       => 'on'
		],
		'neighbor_global_discover_lldp' => [
			'friendly_name' => __('LLDP Neighbors'),
			'description'   => __('Discover Logical Link Discovery Protocol neighbors'),
			'method'        => 'checkbox',
			'default'       => 'on'
		 ],
		'neighbor_global_discover_ip' => [
			'friendly_name' => __('IP Neighbors'),
			'description'   => __('Discover Neighbors in the same IP Subnet'),
						'method'     => 'checkbox',
						'default'    => 'on'
		],
		'neighbor_global_subnet_correlation' => [
			'friendly_name' => __('Minimum Network Mask Correlation'),
			'description'   => __('Ignores neighbors on subnets with mask greater than this value.'),
			'method'        => 'drop_array',
			'default'       => '30',
			'array'         => [
				31  => __('  /%d Mask', 31),
				30  => __('  /%d Mask', 30),
				29  => __('  /%d Mask', 29),
				28  => __('  /%d Mask', 28),
				27  => __('  /%d Mask', 27),
				26  => __('  /%d Mask', 26),
				25  => __('  /%d Mask', 25),
				24  => __('  /%d Mask', 24),
				23  => __('  /%d Mask', 23),
				22  => __('  /%d Mask', 22),
				21  => __('  /%d Mask', 21),
				20  => __('  /%d Mask', 20),
			],
		],
		'neighbor_global_discover_switching' => [
			'friendly_name' => __('Switching Neighbors'),
			'description'   => __('Discover Neighbors in the same IP Subnet'),
			'method'        => 'checkbox',
			'default'       => 'on'
		],
		'neighbor_global_discover_ifalias' => [
			'friendly_name' => __('Interface Descriptions'),
			'description'   => __('Discover Neighbors using interface descriptions'),
			'method'        => 'checkbox',
			'default'       => 'on'
		],
		'neighbor_global_discover_routing_header' => [
			'friendly_name' => __('Routing Protocols'),
			'method'        => 'spacer',
		 ],
		'neighbor_global_discover_ospf' => [
			'friendly_name' => __('OSPF Neighbors'),
			'description'   => __('Discover OSPF Neighbors'),
			'method'        => 'checkbox',
			'default'       => 'on'
		 ],
		 'neighbor_global_discover_bgp' => [
			'friendly_name' => __('BGP Neighbors'),
			'description'   => __('Discover Internal BGP Neighbors'),
			'method'        => 'checkbox',
			'default'       => 'on'
		 ],
		 'neighbor_global_discover_isis' => [
			'friendly_name' => __('IS-IS Neighbors'),
			'description'   => __('Discover IS-IS Neighbors'),
			'method'        => 'checkbox',
			'default'       => 'on'
		 ],
		'neighbor_global_discover_polling_header' => [
			'friendly_name' => __('Polling Options'),
			'method'        => 'spacer',
		 ],
		'neighbor_global_poller_processes' => [
			'friendly_name' => __('Poller Concurrent Processes'),
			'description'   => __('What is the maximum number of concurrent collector process that you want to run at one time?'),
			'method'        => 'drop_array',
			'default'       => '10',
			'array'         => [
				1  => __('%d Process', 1),
				2  => __('%d Processes', 2),
				3  => __('%d Processes', 3),
				4  => __('%d Processes', 4),
				5  => __('%d Processes', 5),
				10 => __('%d Processes', 10),
				15 => __('%d Processes', 15),
				20 => __('%d Processes', 20),
				25 => __('%d Processes', 25),
				30 => __('%d Processes', 30),
				35 => __('%d Processes', 35),
				40 => __('%d Processes', 40),
			],
		],
		'neighbor_global_autodiscovery_freq' => [
			'friendly_name' => __('Neighbor Discovery Frequency'),
			'description'   => __('How often do you want to look for new neighbors'),
			'method'        => 'drop_array',
			'default'       => '300',
			'array'         => $neighbor_frequencies
			],
		'neighbor_global_deadtimer' => [
			'friendly_name' => __('Neighbor Dead Timer'),
			'description'   => __('After what period should old entries be aged out?'),
			'method'        => 'drop_array',
			'default'       => '300',
			'array'         => $neighbor_frequencies
		],
		'neighbor_poller_process_timeout' => [
			'friendly_name' => __('Poller Process Timeout'),
			'description'   => __('How long should we allow a poller process to run before we consider it timed out and kill it?'),
			'method'        => 'drop_array',
			'default'       => '600',
			'array'         => [
				60    => __('%d Minute', 5),
				300   => __('%d Minutes', 10),
				600   => __('%d Minutes', 15),
				1200  => __('%d Minutes', 20),
			],
		],
		'neighbor_data_retention' => [
			'friendly_name' => __('Dead Neighbor Data Retention'),
			'description'   => __('How long should we keep neighbor discovery data (including deltas) before we automatically delete it?'),
			'method'        => 'drop_array',
			'default'       => '900',
			'array'         => [
				3600    => __('%d Hour', 1),
				7200    => __('%d Hours', 2),
				14400   => __('%d Hours', 4),
				43200   => __('%d Hours', 12),
				86400   => __('%d Day', 1),
				604800  => __('%d Week', 1),
				2592000 => __('%d Month', 1),
				-1      => __('Keep Forever'),
			]
		]
	];
}

/**
 * Register plugin arrays and menu items
 *
 * Sets up frequency options, menu entries under Automation, and triggers
 * upgrade checks. Called by Cacti's config_arrays hook.
 *
 * @return void
 */
function neighbor_config_arrays() {
	global $menu, $messages, $neighbor_frequencies, $menu_glyphs;

	$neighbor_frequencies = [
		-1    => __('Disabled'),
		60    => __('%d Minute', 1),
		300   => __('%d Minutes', 5),
		600   => __('%d Minutes', 10),
		1200  => __('%d Minutes', 20),
		3600  => __('%d Hour', 1),
		7200  => __('%d Hours', 2),
		14400 => __('%d Hours', 4),
		43200 => __('%d Hours', 12),
		86400 => __('%d Day', 1)
	];

	if (isset($_SESSION['neighbor_message']) && $_SESSION['neighbor_message'] != '') {
		$messages['neighbor_message'] = ['message' => $_SESSION['neighbor_message'], 'type' => 'info'];
	}

	// Remove
	// $menu[__('Automation')]['plugins/neighbor/neighbor_rules.php'] = __('Neighbor Rules');

	$menu2 =  [];

	foreach ($menu as $temp => $temp2) {
		$menu2[$temp] = $temp2;

		if ($temp == __('Automation')) {
			$menu2[__('Neighbors', 'neighbor')]['plugins/neighbor/neighbor_rules.php']            = __('Map Rules', 'neighbor');
			$menu2[__('Neighbors', 'neighbor')]['plugins/neighbor/neighbor_vrf_rules.php']        = __('VRF Mapping', 'neighbor');
		}
	}
	$menu                                     = $menu2;
	$menu_glyphs[__('Neighbors', 'neighbor')] = 'fas fa-sitemap';

	neighbor_check_upgrade();
}

/**
 * Add plugin navigation breadcrumbs
 *
 * Registers navigation text for the neighbor plugin pages.
 *
 * @param  array $nav Existing navigation array
 * @return array Updated navigation array
 */
function neighbor_draw_navigation_text($nav) {
	$nav['neighbor.php:']          = ['title' => __('Neighbor Summary'), 'mapping' => '', 'url' => 'neighbor.php', 'level' => '0'];
	$nav['neighbor.php:summary']   = ['title' => __('Neighbor Summary'), 'mapping' => '', 'url' => 'neighbor.php', 'level' => '0'];
	$nav['neighbor.php:links']     = ['title' => __('Neighbor Links'), 'mapping' => '', 'url' => 'neighbor.php', 'level' => '0'];
	$nav['neighbor.php:routing']   = ['title' => __('Routing Protocols'), 'mapping' => '', 'url' => 'neighbor.php', 'level' => '0'];

	$nav['neighbor_types.php:']        = ['title' => __('Host MIB OS Types'), 'mapping' => 'index.php:', 'url' => 'neighbor_types.php', 'level' => '1'];
	$nav['neighbor_types.php:actions'] = ['title' => __('Actions'), 'mapping' => 'index.php:,neighbor_types.php:', 'url' => 'neighbor_types.php', 'level' => '2'];
	$nav['neighbor_types.php:edit']    = ['title' => __('(Edit)'), 'mapping' => 'index.php:,neighbor_types.php:', 'url' => 'neighbor_types.php', 'level' => '2'];
	$nav['neighbor_types.php:import']  = ['title' => __('Import'), 'mapping' => 'index.php:,neighbor_types.php:', 'url' => 'neighbor_types.php', 'level' => '2'];

	return $nav;
}

/**
 * Display neighbor plugin tab in Cacti header
 *
 * Shows the neighbor tab with appropriate active/inactive graphics.
 *
 * @return void Outputs HTML img tag
 */
function neighbor_show_tab() {
	global $config;

	if (api_user_realm_auth('neighbor.php')) {
		if (substr_count($_SERVER['REQUEST_URI'], 'neighbor.php')) {
			print '<a href="' . $config['url_path'] . 'plugins/neighbor/neighbor.php"><img src="' . $config['url_path'] . 'plugins/neighbor/img/tab_neighbor_down.gif" alt="neighbor"></a>';
		} else {
			print '<a href="' . $config['url_path'] . 'plugins/neighbor/neighbor.php"><img src="' . $config['url_path'] . 'plugins/neighbor/img/tab_neighbor.gif" alt="neighbor"></a>';
		}
	}
}

/**
 * Add neighbor discovery settings to host edit form
 *
 * Injects neighbor discovery configuration checkboxes into the device
 * edit form, allowing per-host control of discovery protocols.
 *
 * @return void Modifies global $fields_host_edit array
 */
function neighbor_config_form() {
	global $fields_host_edit, $criticalities, $config;
	include_once($config['base_path'] . '/plugins/neighbor/lib/neighbor_sql_tables.php');

	// Get host_id from the form if editing an existing host
	// Use get_nfilter_request_var to avoid validation errors when 'id' is non-numeric (e.g., plugin name)
	$host_id = 0;

	if (isset_request_var('id')) {
		$id_val = get_nfilter_request_var('id');

		if (is_numeric($id_val)) {
			$host_id = (int)$id_val;
		}
	}

	// Load settings from plugin_neighbor_host table
	$neighbor_settings = [];

	if ($host_id > 0) {
		$neighbor_settings = neighbor_get_host_settings($host_id);
	}

	$fields_host_edit2 = $fields_host_edit;
	$fields_host_edit3 = [];

	// error_log(print_r($fields_host_edit2,1));
	if (!sizeof($fields_host_edit2)) {
		return;
	}

	foreach ($fields_host_edit2 as $f => $a) {
		$fields_host_edit3[$f] = $a;

		if ($f == 'disabled') {
			$fields_host_edit3['neighbor_header'] = [
				'friendly_name' => __('Neighbor Discovery Settings', 'neighbor'),
				'method'        => 'spacer',
				'collapsible'   => 'true'
			];

			$fields_host_edit3['neighbor_discover_enable'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('Enable Neighbor Discovery', 'neighbor'),
				'description'   => __('Enable Neighbor discovery during polling', 'neighbor'),
				'value'         => isset($neighbor_settings['enabled']) ? $neighbor_settings['enabled'] : 'on',
				'default'       => 'on',
				'form_id'       => false
			];

			$fields_host_edit3['neighbor_discover_cdp'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('CDP', 'neighbor'),
				'description'   => __('Discover Cisco Discovery Protocol neighbors', 'neighbor'),
				'value'         => isset($neighbor_settings['discover_cdp']) ? $neighbor_settings['discover_cdp'] : 'on',
				'default'       => 'on',
				'form_id'       => false
			];

			$fields_host_edit3['neighbor_discover_lldp'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('LLDP', 'neighbor'),
				'description'   => __('Discover Logical Link Discovery Protocol neighbors', 'neighbor'),
				'value'         => '|arg1:neighbor_discover_lldp|',
				'default'       => 'on',
				'form_id'       => false
			];

			$fields_host_edit3['neighbor_discover_ip'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('IP Subnets', 'neighbor'),
				'description'   => __('Discover neighbors in the same IP subnet ', 'neighbor'),
				'value'         => isset($neighbor_settings['discover_ip']) ? $neighbor_settings['discover_ip'] : 'on',
				'default'       => 'on',
				'form_id'       => false
			];

			$fields_host_edit3['neighbor_discover_switching'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('Switching', 'neighbor'),
				'description'   => __('Discover neighbors by learned MAC address', 'neighbor'),
				'value'         => isset($neighbor_settings['discover_switching']) ? $neighbor_settings['discover_switching'] : 'on',
				'default'       => 'on',
				'form_id'       => false
			];

			$fields_host_edit3['neighbor_discover_ifalias'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('Interface Descriptions', 'neighbor'),
				'description'   => __('Discover neighbors by parsing interface descriptions', 'neighbor'),
				'value'         => isset($neighbor_settings['discover_ifalias']) ? $neighbor_settings['discover_ifalias'] : 'on',
				'default'       => 'on',
				'form_id'       => false
			];

			$fields_host_edit3['neighbor_discover_ospf'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('OSPF', 'neighbor'),
				'description'   => __('Discover OSPF neighbors', 'neighbor'),
				'value'         => isset($neighbor_settings['discover_ospf']) ? $neighbor_settings['discover_ospf'] : 'on',
				'default'       => 'on',
				'form_id'       => false
			];

			$fields_host_edit3['neighbor_discover_bgp'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('BGP', 'neighbor'),
				'description'   => __('Discover BGP neighbors', 'neighbor'),
				'value'         => isset($neighbor_settings['discover_bgp']) ? $neighbor_settings['discover_bgp'] : 'on',
				'default'       => 'on',
				'form_id'       => false
			];

			$fields_host_edit3['neighbor_discover_isis'] = [
				'method'        => 'checkbox',
				'friendly_name' => __('IS-IS', 'neighbor'),
				'description'   => __('Discover IS-IS neighbors', 'neighbor'),
				'value'         => isset($neighbor_settings['discover_isis']) ? $neighbor_settings['discover_isis'] : 'on',
				'default'       => 'on',
				'form_id'       => false
			];
		}
	}
	$fields_host_edit = $fields_host_edit3;
}

/**
 * Add neighbor discovery actions to device action dropdown
 *
 * @param  array $device_action_array Existing device actions
 * @return array Updated device actions with neighbor options
 */
function neighbor_device_action_array($device_action_array) {
	$device_action_array['neighbor_settings'] = __('Change Neighbor Options', 'neighbor');

	return $device_action_array;
}

/**
 * Execute bulk device actions for neighbor discovery
 *
 * Handles enable/disable actions and bulk settings changes for neighbor
 * discovery on multiple devices.
 *
 * @param  string $action Action to execute
 * @return string Action name to allow pass-through to other handlers
 */
function neighbor_device_action_execute($action) {
	global $config, $fields_host_edit;
	include_once($config['base_path'] . '/plugins/neighbor/lib/neighbor_sql_tables.php');

	if ($action != 'neighbor_enable' && $action != 'neighbor_disable' && $action != 'neighbor_settings') {
		return $action;
	}

	$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

	if ($selected_items != false) {
		if ($action == 'neighbor_enable' || $action == 'neighbor_disable') {
			for ($i = 0; ($i < count($selected_items)); $i++) {
				$settings = ['enabled' => ($action == 'neighbor_enable' ? 'on' : '')];
				neighbor_save_host_settings($selected_items[$i], $settings);
			}
		} else {
			// Map old field names to new field names
			$field_mapping = [
				'neighbor_discover_enable'    => 'enabled',
				'neighbor_discover_cdp'       => 'discover_cdp',
				'neighbor_discover_lldp'      => 'discover_lldp',
				'neighbor_discover_ip'        => 'discover_ip',
				'neighbor_discover_switching' => 'discover_switching',
				'neighbor_discover_ifalias'   => 'discover_ifalias',
				'neighbor_discover_ospf'      => 'discover_ospf',
				'neighbor_discover_bgp'       => 'discover_bgp',
				'neighbor_discover_isis'      => 'discover_isis',
			];

			for ($i = 0; ($i < count($selected_items)); $i++) {
				$settings = [];

				foreach ($field_mapping as $old_field => $new_field) {
					if (isset_request_var("t_$old_field")) {
						$settings[$new_field] = get_nfilter_request_var($old_field);
					}
				}

				if (count($settings) > 0) {
					neighbor_save_host_settings($selected_items[$i], $settings);
				}
			}
		}
	}

	return $action;
}

/**
 * Prepare device action form for neighbor discovery settings
 *
 * Displays the bulk edit form for changing neighbor discovery settings
 * on multiple devices simultaneously.
 *
 * @param  array $save Save data including action and selected devices
 * @return array Unmodified save data
 */
function neighbor_device_action_prepare($save) {
	global $host_list, $fields_host_edit;

	$action = $save['drp_action'];

	if ($action != 'neighbor_enable' && $action != 'neighbor_disable' && $action != 'neighbor_settings') {
		return $save;
	}

	if ($action == 'neighbor_enable' || $action == 'neighbor_disable') {
		if ($action == 'neighbor_enable') {
			$action_description = 'enable';
		} elseif ($action == 'neighbor_disable') {
			$action_description = 'disable';
		}

		print "<tr>
			<td colspan='2' class='even'>
				<p>" . __('Click \'Continue\' to %s neighbor discovery on these device(s)', $action_description, 'neighbor') . "</p>
				<p><div class='itemlist'><ul>" . $save['host_list'] . '</ul></div></p>
			</td>
		</tr>';
	} else {
		print "<tr>
			<td colspan='2' class='even'>
				<p>" . __('Click \'Continue\' to Change the neighbor discovery settings for the following device(s). Remember to check \'Update this Field\' to indicate which columns to update.', 'neighbor') . "</p>
				<p><div class='itemlist'><ul>" . $save['host_list'] . '</ul></div></p>
			</td>
		</tr>';

		$form_array = [];

		$fields = [
			'neighbor_discover_enable',
			'neighbor_discover_cdp',
			'neighbor_discover_lldp',
			'neighbor_discover_ip',
			'neighbor_discover_switching',
			'neighbor_discover_ifalias',
			'neighbor_discover_ospf',
			'neighbor_discover_bgp',
			'neighbor_discover_isis',
		];

		foreach ($fields as $field) {
			$form_array += [$field => $fields_host_edit[$field]];

			$form_array[$field]['value']        = '';
			$form_array[$field]['form_id']      = 0;
			$form_array[$field]['sub_checkbox'] = [
				'name'          => 't_' . $field,
				'friendly_name' => __('Update this Field', 'neighbor'),
				'value'         => ''
			];
		}

		draw_edit_form(
			[
				'config' => ['no_form_tag' => true],
				'fields' => $form_array
			]
		);
	}
}

/**
 * Save neighbor discovery settings when device is saved
 *
 * Validates and stores neighbor discovery settings in plugin_neighbor_host table
 * when a device is created or updated.
 *
 * @param  array $save Device save data
 * @return array Unmodified save data to allow other hooks to process
 */
function neighbor_api_device_save($save) {
	global $config;
	include_once($config['base_path'] . '/plugins/neighbor/lib/neighbor_sql_tables.php');

	// Map old field names to new field names (without 'neighbor_' prefix)
	$field_mapping = [
		'neighbor_discover_enable'    => 'enabled',
		'neighbor_discover_cdp'       => 'discover_cdp',
		'neighbor_discover_lldp'      => 'discover_lldp',
		'neighbor_discover_ip'        => 'discover_ip',
		'neighbor_discover_switching' => 'discover_switching',
		'neighbor_discover_ifalias'   => 'discover_ifalias',
		'neighbor_discover_ospf'      => 'discover_ospf',
		'neighbor_discover_bgp'       => 'discover_bgp',
		'neighbor_discover_isis'      => 'discover_isis',
	];

	$settings = [];

	foreach ($field_mapping as $old_field => $new_field) {
		if (isset_request_var($old_field)) {
			$settings[$new_field] = form_input_validate(get_nfilter_request_var($old_field), $old_field, '', true, 3);
		} else {
			$settings[$new_field] = form_input_validate('', $old_field, '', true, 3);
		}
	}

	// Save to plugin_neighbor_host table if we have a host_id
	if (isset($save['id']) && $save['id'] > 0) {
		neighbor_save_host_settings($save['id'], $settings);
	}

	return $save;
}

/**
 * Clean up neighbor data when devices are deleted
 *
 * Removes all neighbor discovery data and settings for deleted devices.
 * Uses prepared statements to prevent SQL injection.
 *
 * @param  array $devices Array of device IDs to remove
 * @return array Unmodified device array to allow other hooks to process
 */
function neighbor_device_remove($devices) {
	if (!is_array($devices) || count($devices) == 0) {
		return $devices;
	}

	$devices      = array_map('intval', $devices);
	$placeholders = implode(',', array_fill(0, count($devices), '?'));

	db_execute_prepared("DELETE FROM plugin_neighbor_xdp WHERE host_id IN($placeholders)", $devices);
	db_execute_prepared("DELETE FROM plugin_neighbor_host WHERE host_id IN($placeholders)", $devices);

	return $devices;
}
