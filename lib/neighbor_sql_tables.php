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

function neighbor_setup_table() {
	global $config, $database_default;
	include_once($config['library_path'] . '/database.php');

	// CDP and LLDP Neighbors table
	// Table: plugin_neighbor_xdp
	db_execute("
      CREATE TABLE IF NOT EXISTS `plugin_neighbor_xdp` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `type` enum('cdp','lldp') NOT NULL,
            `host_id` int(11) NOT NULL,
            `host_ip` varchar(64) NOT NULL COMMENT 'Host IP address',
            `hostname` varchar(64) NOT NULL COMMENT 'Device Name from host',
            `snmp_id` int(11) NOT NULL,
            `interface_name` varchar(32) NOT NULL,
            `interface_alias` varchar(64) DEFAULT NULL,
            `interface_speed` int(11) DEFAULT NULL,
            `interface_status` varchar(16) DEFAULT NULL,
            `interface_ip` varchar(45) DEFAULT NULL,
            `interface_hwaddr` char(16) DEFAULT NULL,
            `neighbor_host_id` int(11) NOT NULL,
            `neighbor_hostname` varchar(64) NOT NULL,
            `neighbor_snmp_id` int(11) NOT NULL,
            `neighbor_interface_name` varchar(32) NOT NULL,
            `neighbor_interface_alias` varchar(64) DEFAULT NULL,
            `neighbor_interface_speed` int(11) DEFAULT NULL,
            `neighbor_interface_status` varchar(16) DEFAULT NULL,
            `neighbor_interface_ip` varchar(45) DEFAULT NULL,
            `neighbor_interface_hwaddr` char(16) DEFAULT NULL,
            `neighbor_platform` varchar(128) NOT NULL,
            `neighbor_software` varchar(128) NOT NULL,
            `neighbor_duplex` enum('Full','Half') NOT NULL,    
            `neighbor_last_changed` datetime NOT NULL,
			`last_seen` datetime NOT NULL,
            `neighbor_hash` char(32) NOT NULL,
            `record_hash` char(32) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `entry_hash` (`record_hash`),
            KEY `host_id` (`host_id`),
            KEY `type` (`type`),
            KEY `neighbor_host_id` (`neighbor_host_id`),
            KEY `snmp_id` (`snmp_id`),
            KEY `interface` (`interface_name`),
            KEY `neighbor_snmp_id` (`neighbor_snmp_id`),
            KEY `neighbor_interface` (`neighbor_interface_name`),
            KEY `neighbor_interface_2` (`neighbor_interface_name`),
            KEY `neighbor_hostname` (`neighbor_hostname`),
            KEY `neighbor_last_changed` (`neighbor_last_changed`),
			KEY `last_seen` (`last_seen`),
            KEY `neighbor_duplex` (`neighbor_duplex`),
            KEY `neighbor_hash` (`neighbor_hash`) USING BTREE
        ) AUTO_INCREMENT=45446 DEFAULT CHARSET=utf8mb4 
        ");

	// Table: plugin_neighbor_processes
	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_processes` (
                `pid` int(10) unsigned NOT NULL,
                `taskid` int(10) unsigned NOT NULL,
                `started` timestamp NOT NULL default CURRENT_TIMESTAMP,
                PRIMARY KEY  (`pid`))
                ENGINE=MEMORY
                COMMENT='Running collector processes';");

	// Table: plugin_neighbor_log
	db_execute('CREATE TABLE IF NOT EXISTS `plugin_neighbor_log` (
                                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                                `logtime` datetime NOT NULL,
                                                `message` mediumtext,
                                                PRIMARY KEY (`id`),
                                                KEY `logtime` (`logtime`)
                                          ) DEFAULT CHARSET=utf8mb4
            ');

	// Table: plugin_neighbor_edge
	db_execute('CREATE TABLE IF NOT EXISTS `plugin_neighbor_edge` (
                                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                                `rule_id` int(11) NOT NULL,
                                                `from_id` int(11) NOT NULL,
                                                `to_id` int(11) NOT NULL,
                                                `rrd_file` varchar(255) NOT NULL,
                                                `edge_json` mediumtext,
                                                `edge_updated` datetime NOT NULL,
                                                PRIMARY KEY (`id`),
                                                KEY `rule_id` (`rule_id`),
                                                KEY `edge_updated` (`edge_updated`),
                                                KEY `rrd_file` (`rrd_file`)
                                          ) DEFAULT CHARSET=utf8mb4
            ');

	// Table: plugin_neighbor_poller_output
	db_execute('CREATE TABLE IF NOT EXISTS `plugin_neighbor_poller_output` (
                                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                                `rrd_file` varchar(255) NOT NULL,
                                                `timestamp` int(11) NOT NULL,
                                                `key_name` varchar(64) NOT NULL,
                                                `value` double DEFAULT NULL,
                                                `last_updated` datetime NOT NULL,
                                                PRIMARY KEY (`id`),
                                                UNIQUE KEY `rrd_time_key` (`rrd_file`,`timestamp`,`key_name`),
                                                KEY `timestamp` (`timestamp`)
                                          ) DEFAULT CHARSET=utf8mb4
            ');

	// Table: plugin_neighbor_poller_delta
	db_execute('CREATE TABLE IF NOT EXISTS `plugin_neighbor_poller_delta` (
                                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                                `rrd_file` varchar(255) NOT NULL,
                                                `timestamp` int(11) NOT NULL,
                                                `timestamp_cycle` int(11) NOT NULL,
                                                `key_name` varchar(64) NOT NULL,
                                                `delta` double DEFAULT NULL,
                                                PRIMARY KEY (`id`),
                                                UNIQUE KEY `rrd_cycle_key` (`rrd_file`,`timestamp_cycle`,`key_name`),
                                                KEY `timestamp` (`timestamp`)
                                          ) DEFAULT CHARSET=utf8mb4
            ');

	// Table: plugin_neighbor_user_map
	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_user_map` (
                                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                                `user_id` int(11) NOT NULL,
                                                `rule_id` int(11) NOT NULL,
                                                `item_id` varchar(64) NOT NULL,
                                                `item_x` double DEFAULT NULL,
                                                `item_y` double DEFAULT NULL,
                                                `item_mass` double DEFAULT NULL,
                                                `item_label` varchar(255) DEFAULT NULL,
                                                `random_seed` int(11) DEFAULT '0',
                                                PRIMARY KEY (`id`),
                                                UNIQUE KEY `user_rule_item` (`user_id`,`rule_id`,`item_id`),
                                                KEY `rule_id` (`rule_id`)
                                          ) DEFAULT CHARSET=utf8mb4
            ");

	// Table: plugin_neighbor_ipv4_cache
	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_ipv4_cache` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `host_id` int(11) NOT NULL,
                `hostname` varchar(64) NOT NULL,
                `snmp_id` int(11) NOT NULL,
                `ip_address` char(16) NOT NULL,
                `ip_netmask` char(16) NOT NULL,
                `vrf` varchar(64) NOT NULL,
                `last_seen` datetime NOT NULL COMMENT 'When did we last see this',
                PRIMARY KEY (`id`),
                UNIQUE KEY `host_id_2` (`host_id`,`ip_address`,`vrf`),
                KEY `snmp_id` (`snmp_id`),
                KEY `host_id` (`host_id`),
                KEY `ip_address` (`ip_address`),
                KEY `vrf` (`vrf`),
                KEY `last_seen` (`last_seen`)
              ) DEFAULT CHARSET=utf8mb4
    ");

	// Table: plugin_neighbor_ipv4
	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_ipv4` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `vrf` varchar(64) NOT NULL,
                `host_id` int(11) NOT NULL,
                `hostname` varchar(64) NOT NULL COMMENT 'Device Name from host',
                `snmp_id` int(11) NOT NULL,
                `interface_name` varchar(32) NOT NULL,
                `interface_alias` varchar(64) DEFAULT NULL,
                `interface_ip` char(16) DEFAULT NULL,
                `interface_netmask` char(16) NOT NULL,
                `interface_hwaddr` char(16) DEFAULT NULL,
                `neighbor_host_id` int(11) NOT NULL,
                `neighbor_hostname` varchar(64) NOT NULL,
                `neighbor_snmp_id` int(11) NOT NULL,
                `neighbor_interface_name` varchar(32) NOT NULL,
                `neighbor_interface_alias` varchar(64) DEFAULT NULL,
                `neighbor_interface_ip` char(16) DEFAULT NULL,
                `neighbor_interface_netmask` char(16) NOT NULL,
                `neighbor_interface_hwaddr` char(16) DEFAULT NULL,
                `neighbor_hash` char(32) NOT NULL,
                `record_hash` char(32) NOT NULL,
                `last_seen` datetime NOT NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `entry_hash` (`record_hash`),
               KEY `host_id` (`host_id`),
               KEY `neighbor_host_id` (`neighbor_host_id`),
               KEY `snmp_id` (`snmp_id`),
               KEY `interface` (`interface_name`),
               KEY `neighbor_snmp_id` (`neighbor_snmp_id`),
               KEY `neighbor_interface` (`neighbor_interface_name`),
               KEY `neighbor_interface_2` (`neighbor_interface_name`),
               KEY `neighbor_hostname` (`neighbor_hostname`),
               KEY `neighbor_hash` (`neighbor_hash`) USING BTREE,
               KEY `vrf` (`vrf`)
              ) DEFAULT CHARSET=utf8mb4
    ");

	// Table: plugin_neighbor_graph_rules

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_graph_rules` (
		`id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
		`name` varchar(255) NOT NULL DEFAULT '',
		`snmp_query_id` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`graph_type_id` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`enabled` char(2) DEFAULT '',
		PRIMARY KEY (`id`),
		KEY `snmp_query_id` (`snmp_query_id`),
		KEY `enabled` (`enabled`)
	      ) DEFAULT CHARSET=utf8mb4 COMMENT='Automation Graph Rules';
	");

	// Table: plugin_neighbor_graph_rule_items

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_graph_rule_items` (
		`id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
		`rule_id` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
		`sequence` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`operation` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`field` varchar(255) NOT NULL DEFAULT '',
		`operator` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`pattern` varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (`id`),
		KEY `rule_id` (`rule_id`)
	      ) DEFAULT CHARSET=utf8mb4 COMMENT='Automation Graph Rule Items';
	");

	// Table: plugin_neighbor_match_rule_items

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_match_rule_items` (
		`id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
		`rule_id` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
		`rule_type` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`sequence` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`operation` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`field` varchar(255) NOT NULL DEFAULT '',
		`operator` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`pattern` varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (`id`),
		KEY `rule_id` (`rule_id`),
		KEY `rule_type` (`rule_type`)
	      ) DEFAULT CHARSET=utf8mb4 COMMENT='Automation Match Rule Items';
	");

	// Table: plugin_neighbor_rules

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_rules` (
		`id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
		`name` varchar(255) NOT NULL DEFAULT '',
            `description` varchar(64) DEFAULT NULL,
            `neighbor_type` varchar(64) NOT NULL DEFAULT 'interface',
            `neighbor_options` varchar(255) DEFAULT '',
		`enabled` char(2) DEFAULT '',
		PRIMARY KEY (`id`),
		KEY `enabled` (`enabled`),
		KEY `neighbor_type` (`neighbor_type`)
	      ) DEFAULT CHARSET=utf8mb4 COMMENT='Neighbor Automation Rules';
	");

	// Table: plugin_neighbor_vrf_rules
	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_vrf_rules` (
            `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `description` varchar(64) DEFAULT NULL,
            `neighbor_type` varchar(64) NOT NULL DEFAULT 'interface',
            `neighbor_options` varchar(255) DEFAULT '',
            `vrf` varchar(64) NOT NULL DEFAULT '',
            `enabled` char(2) DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `enabled` (`enabled`),
            KEY `vrf` (`vrf`),
            KEY `neighbor_type` (`neighbor_type`)
            ) DEFAULT CHARSET=utf8mb4 COMMENT='Automation VRF Rules';
      ");

	// Table: plugin_neighbor_vrf_rule_items
	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_vrf_rule_items` (
            `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
            `rule_id` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
            `sequence` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
            `operation` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
            `field` varchar(255) NOT NULL DEFAULT '',
            `operator` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
            `pattern` varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `rule_id` (`rule_id`)
            ) DEFAULT CHARSET=utf8mb4 COMMENT='Automation VRF Rule Items';
      ");

	// Table: plugin_neighbor_vrf_match_rule_items
	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_vrf_match_rule_items` (
            `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
            `rule_id` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
            `rule_type` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
            `sequence` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
            `operation` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
            `field` varchar(255) NOT NULL DEFAULT '',
            `operator` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
            `pattern` varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `rule_id` (`rule_id`),
            KEY `rule_type` (`rule_type`)
            ) DEFAULT CHARSET=utf8mb4 COMMENT='Automation VRF Match Rule Items';
      ");

	// Table: plugin_neighbor_tree_rules

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_tree_rules` (
		`id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
		`name` varchar(255) NOT NULL DEFAULT '',
		`tree_id` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`tree_item_id` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
		`leaf_type` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`host_grouping_type` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`enabled` char(2) DEFAULT '',
		PRIMARY KEY (`id`),
		KEY `tree_id` (`tree_id`),
		KEY `enabled` (`enabled`)
	      ) DEFAULT CHARSET=utf8mb4 COMMENT='Automation Tree Rules';
	");

	// Table: plugin_neighbor_tree_rule_items

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_neighbor_tree_rule_items` (
		`id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
		`rule_id` mediumint(8) UNSIGNED NOT NULL DEFAULT '0',
		`sequence` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`field` varchar(255) NOT NULL DEFAULT '',
		`sort_type` smallint(3) UNSIGNED NOT NULL DEFAULT '0',
		`propagate_changes` char(2) DEFAULT '',
		`search_pattern` varchar(255) NOT NULL DEFAULT '',
		`replace_pattern` varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY (`id`),
		KEY `rule_id` (`rule_id`)
	      ) DEFAULT CHARSET=utf8mb4 COMMENT='Automation Tree Rule Items';
	");

	/*
	 * Do not auto-alter Cacti's core host table here.
	 * On installs with wide host schemas this causes InnoDB row-size failures.
	 */

	if (!db_fetch_cell("SHOW COLUMNS FROM plugin_neighbor_rules LIKE 'neighbor_type'")) {
		api_plugin_db_add_column('neighbor', 'plugin_neighbor_rules', ['name' => 'neighbor_type', 'type' => 'varchar(64)', 'NULL' => false, 'default' => 'interface', 'after' => 'description']);
	}

	if (!db_fetch_cell("SHOW COLUMNS FROM plugin_neighbor_rules LIKE 'neighbor_options'")) {
		api_plugin_db_add_column('neighbor', 'plugin_neighbor_rules', ['name' => 'neighbor_options', 'type' => 'varchar(255)', 'NULL' => true, 'default' => '', 'after' => 'neighbor_type']);
	}

	// Create plugin_neighbor_host table for per-host settings
	neighbor_setup_host_table();

	// Migrate data from host table if needed
	neighbor_migrate_host_settings();
}

function neighbor_setup_host_table() {
	$data              = [];
	$data['columns'][] = [
		'name'           => 'id',
		'type'           => 'int(11)',
		'unsigned'       => true,
		'NULL'           => false,
		'auto_increment' => true
	];
	$data['columns'][] = [
		'name'     => 'host_id',
		'type'     => 'int(11)',
		'unsigned' => true,
		'NULL'     => false,
		'default'  => '0'
	];
	$data['columns'][] = [
		'name'    => 'enabled',
		'type'    => 'char(3)',
		'NULL'    => false,
		'default' => 'on'
	];
	$data['columns'][] = [
		'name'    => 'discover_cdp',
		'type'    => 'char(3)',
		'NULL'    => false,
		'default' => 'on'
	];
	$data['columns'][] = [
		'name'    => 'discover_lldp',
		'type'    => 'char(3)',
		'NULL'    => false,
		'default' => 'on'
	];
	$data['columns'][] = [
		'name'    => 'discover_ip',
		'type'    => 'char(3)',
		'NULL'    => false,
		'default' => 'on'
	];
	$data['columns'][] = [
		'name'    => 'discover_switching',
		'type'    => 'char(3)',
		'NULL'    => false,
		'default' => 'on'
	];
	$data['columns'][] = [
		'name'    => 'discover_ifalias',
		'type'    => 'char(3)',
		'NULL'    => false,
		'default' => 'on'
	];
	$data['columns'][] = [
		'name'    => 'discover_ospf',
		'type'    => 'char(3)',
		'NULL'    => false,
		'default' => 'on'
	];
	$data['columns'][] = [
		'name'    => 'discover_bgp',
		'type'    => 'char(3)',
		'NULL'    => false,
		'default' => 'on'
	];
	$data['columns'][] = [
		'name'    => 'discover_isis',
		'type'    => 'char(3)',
		'NULL'    => false,
		'default' => 'on'
	];
	$data['columns'][] = [
		'name'    => 'last_discovered',
		'type'    => 'timestamp',
		'NULL'    => true,
		'default' => null
	];
	$data['columns'][] = [
		'name'    => 'discovery_status',
		'type'    => 'varchar(64)',
		'NULL'    => false,
		'default' => ''
	];

	$data['primary']       = 'id';
	$data['keys'][]        = ['name' => 'host_id', 'columns' => 'host_id'];
	$data['keys'][]        = ['name' => 'enabled', 'columns' => 'enabled'];
	$data['unique_keys'][] = ['name' => 'host_id_unique', 'columns' => 'host_id'];
	$data['type']          = 'InnoDB';
	$data['comment']       = 'Per-host Neighbor Discovery Settings';

	api_plugin_db_table_create('neighbor', 'plugin_neighbor_host', $data);
}

function neighbor_migrate_host_settings() {
	// Check if we need to migrate data from host table to plugin_neighbor_host table
	$old_fields = [
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

	// Check if the old columns still exist in the host table
	$has_old_columns = db_fetch_cell("SHOW COLUMNS FROM host LIKE 'neighbor_discover_enable'");

	if ($has_old_columns) {
		// Migrate data from host table to plugin_neighbor_host table
		$hosts = db_fetch_assoc('SELECT id,
			neighbor_discover_enable,
			neighbor_discover_cdp,
			neighbor_discover_lldp,
			neighbor_discover_ip,
			neighbor_discover_switching,
			neighbor_discover_ifalias,
			neighbor_discover_ospf,
			neighbor_discover_bgp,
			neighbor_discover_isis
			FROM host
			WHERE id > 0');

		if (cacti_sizeof($hosts)) {
			foreach ($hosts as $host) {
				// Check if this host already exists in plugin_neighbor_host
				$exists = db_fetch_cell_prepared('SELECT id FROM plugin_neighbor_host WHERE host_id = ?', [$host['id']]);

				if (!$exists) {
					// Insert the host settings
					db_execute_prepared('INSERT INTO plugin_neighbor_host
						(host_id, enabled, discover_cdp, discover_lldp, discover_ip,
						discover_switching, discover_ifalias, discover_ospf, discover_bgp, discover_isis)
						VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						[
							$host['id'],
							$host['neighbor_discover_enable'],
							$host['neighbor_discover_cdp'],
							$host['neighbor_discover_lldp'],
							$host['neighbor_discover_ip'],
							$host['neighbor_discover_switching'],
							$host['neighbor_discover_ifalias'],
							$host['neighbor_discover_ospf'],
							$host['neighbor_discover_bgp'],
							$host['neighbor_discover_isis']
						]
					);
				}
			}

			cacti_log('NEIGHBOR: Migrated ' . cacti_sizeof($hosts) . ' host settings to plugin_neighbor_host table', false, 'SYSTEM');
		}

		// Drop the old columns from the host table
		// NOTE: Only do this after verifying migration was successful
		// We'll keep the columns for now for backward compatibility
		// In a future version, we can add code to remove them
	}
}

function add_fields_host() {
	// Legacy function - kept for backward compatibility
	// New installations will use plugin_neighbor_host table instead
	// This will only run on upgrades from older versions

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

	$last = 'disabled';

	foreach ($fields as $field) {
		// Only add if column doesn't exist
		if (!db_fetch_cell("SHOW COLUMNS FROM host LIKE '$field'")) {
			api_plugin_db_add_column('neighbor', 'host', [
				'name'    => $field,
				'type'    => 'char(3)',
				'NULL'    => false,
				'default' => 'on',
				'after'   => $last
			]);
		}
		$last = $field;
	}
}

/**
 * Get neighbor discovery settings for a host
 * 
 * @param  int   $host_id The host ID
 * @return array Host settings or default settings if not found
 */
function neighbor_get_host_settings($host_id) {
	$settings = db_fetch_row_prepared('SELECT * FROM plugin_neighbor_host WHERE host_id = ?', [$host_id]);

	if (!$settings) {
		// Return default settings if host not found
		$settings = [
			'host_id'            => $host_id,
			'enabled'            => 'on',
			'discover_cdp'       => 'on',
			'discover_lldp'      => 'on',
			'discover_ip'        => 'on',
			'discover_switching' => 'on',
			'discover_ifalias'   => 'on',
			'discover_ospf'      => 'on',
			'discover_bgp'       => 'on',
			'discover_isis'      => 'on',
		];
	}

	return $settings;
}

/**
 * Save neighbor discovery settings for a host
 * 
 * @param  int   $host_id  The host ID
 * @param  array $settings Array of settings to save
 * @return bool  Success status
 */
function neighbor_save_host_settings($host_id, $settings) {
	$exists = db_fetch_cell_prepared('SELECT id FROM plugin_neighbor_host WHERE host_id = ?', [$host_id]);

	$columns = [
		'enabled', 'discover_cdp', 'discover_lldp', 'discover_ip',
		'discover_switching', 'discover_ifalias', 'discover_ospf',
		'discover_bgp', 'discover_isis'
	];

	if ($exists) {
		// Update existing record
		$sql_parts = [];
		$params    = [];

		foreach ($columns as $column) {
			if (isset($settings[$column])) {
				$sql_parts[] = "$column = ?";
				$params[]    = $settings[$column];
			}
		}

		if (count($sql_parts) > 0) {
			$params[] = $host_id;
			$sql      = 'UPDATE plugin_neighbor_host SET ' . implode(', ', $sql_parts) . ' WHERE host_id = ?';

			return db_execute_prepared($sql, $params);
		}
	} else {
		// Insert new record
		$insert_columns = ['host_id'];
		$insert_values  = ['?'];
		$params         = [$host_id];

		foreach ($columns as $column) {
			if (isset($settings[$column])) {
				$insert_columns[] = $column;
				$insert_values[]  = '?';
				$params[]         = $settings[$column];
			}
		}

		$sql = 'INSERT INTO plugin_neighbor_host (' . implode(', ', $insert_columns) . ') VALUES (' . implode(', ', $insert_values) . ')';

		return db_execute_prepared($sql, $params);
	}

	return false;
}

/**
 * Check if a specific discovery type is enabled for a host
 * Provides backward compatibility with old host table columns
 * 
 * @param  mixed  $host  Host array or host ID
 * @param  string $field Field name (e.g., 'discover_cdp', 'enabled')
 * @return bool   True if enabled, false otherwise
 */
function neighbor_host_discovery_enabled($host, $field) {
	// If host is an array with the old column names from host table
	if (is_array($host)) {
		$host_id = isset($host['id']) ? $host['id'] : 0;

		// Check for old-style field names (neighbor_discover_*)
		$old_field = 'neighbor_' . $field;

		if (array_key_exists($old_field, $host)) {
			return !empty($host[$old_field]) && $host[$old_field] != 'off';
		}

		// Check for new-style field names
		if (array_key_exists($field, $host)) {
			return !empty($host[$field]) && $host[$field] != 'off';
		}
	} else {
		$host_id = $host;
	}

	// Fetch from new table
	if ($host_id > 0) {
		$settings = neighbor_get_host_settings($host_id);

		if (isset($settings[$field])) {
			return $settings[$field] == 'on';
		}
	}

	// Default to enabled if not found
	return true;
}

/**
 * Update last discovered timestamp for a host
 * 
 * @param  int    $host_id The host ID
 * @param  string $status  Optional status message
 * @return bool   Success status
 */
function neighbor_update_host_discovery_status($host_id, $status = '') {
	$exists = db_fetch_cell_prepared('SELECT id FROM plugin_neighbor_host WHERE host_id = ?', [$host_id]);

	if ($exists) {
		return db_execute_prepared(
			'UPDATE plugin_neighbor_host SET last_discovered = NOW(), discovery_status = ? WHERE host_id = ?',
			[$status, $host_id]
		);
	} else {
		// Create entry with defaults if it doesn't exist
		return db_execute_prepared(
			'INSERT INTO plugin_neighbor_host (host_id, last_discovered, discovery_status) VALUES (?, NOW(), ?)',
			[$host_id, $status]
		);
	}
}

/**
 * Get all hosts with neighbor discovery enabled
 * 
 * @return array Array of host IDs with neighbor discovery enabled
 */
function neighbor_get_enabled_hosts() {
	return db_fetch_assoc("SELECT host_id FROM plugin_neighbor_host WHERE enabled = 'on'");
}

/**
 * Remove host settings when a host is deleted
 * 
 * @param  int  $host_id The host ID to remove
 * @return bool Success status
 */
function neighbor_delete_host_settings($host_id) {
	return db_execute_prepared('DELETE FROM plugin_neighbor_host WHERE host_id = ?', [$host_id]);
}

?>
