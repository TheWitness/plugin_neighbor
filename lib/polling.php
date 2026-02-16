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

include_once(dirname(__FILE__) . '/neighbor_functions.php');

/**
 * Get the edge RRA
 *
 * @return array The edge RRA
 */
function get_edge_rra() {
	$rrd_array = [];
	$rows = db_fetch_assoc("SELECT rrd_file from plugin_neighbor_edge");

	if (!is_array($rows)) {
		return $rrd_array;
	}

	foreach ($rows as $row) {
		$rrd = isset($row['rrd_file']) ? $row['rrd_file'] : "";
		if ($rrd) {
			$rrd_array[$rrd] = $row;
		}
	}
	return $rrd_array;
}

/**
 * Process the poller output
 *
 * @param array $rrd_update_array The RRD update array
 * @return array The updated RRD update array
 */
function neighbor_poller_output(&$rrd_update_array) {
	global $config, $debug;

	$edge_rra = get_edge_rra();
	$path_rra = $config['rra_path'];

	foreach ($rrd_update_array as $rrd => $rec) {
		$rra_subst = str_replace($path_rra,"<path_rra>",$rrd);

		if (!isset($edge_rra[$rra_subst])) {
			continue;
		}
		
		$rec_json = json_encode($rec);
		foreach ($rec['times'] as $time => $data) {
		
			foreach ($data as $key => $counter) {
				
				db_execute_prepared("INSERT into plugin_neighbor_poller_output
						     VALUES ('',?,?,?,?,NOW())
						     ON DUPLICATE KEY UPDATE
						     key_name=?,
						     value=?",
						     array($rra_subst,$time,$key,$counter,$key,$counter));
			}
		}
		
	}
	
	db_execute_prepared("DELETE FROM plugin_neighbor_poller_output where timestamp < ?", array(time() - 900));

	return $rrd_update_array;
}

/**
 * Process the deltas from the poller_output hook
 * Called from poller_bottom hook
 */
function process_poller_deltas() {
	cacti_log("process_poller_deltas() is running", true, "NEIGHBOR POLLER");

	db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('','process_poller_deltas() is starting.'));

	$results = db_fetch_assoc("SELECT * from plugin_neighbor_poller_output");

	if (!is_array($results)) {
		return;
	}

	$hash = db_fetch_hash($results,array('rrd_file','timestamp','key_name'));

	if (!is_array($hash)) {
		return;
	}

		
	foreach ($hash as $rrdFile => $data) {
		cacti_log("process_poller_deltas() is processing RRD:$rrdFile,with data:" . print_r($data,1), true, "NEIGHBOR POLLER");

		$timestamps = array_keys($data);
		rsort($timestamps);
		
		db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('','process_poller_deltas() is running. Timestamps:'.print_r($timestamps,1)));
		
		if (sizeof($timestamps) >= 2) {
			$now = $timestamps[0];
			$before = $timestamps[1];
			$timeDelta = $now - $before;
			$poller_interval = read_config_option('poller_interval') ? read_config_option('poller_interval') : 300;
			
			/* Normalise these down to a poller cycle boundary to group them together */
			$timestamp_cycle = intval($now / $poller_interval) * $poller_interval ;
			
			cacti_log("process_poller_deltas(): now:$now, before:$before, Hash:".print_r($data[$now],true), true, "NEIGHBOR POLLER");
			db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('',"Now:$now, Before:$before, Hash:".print_r($data[$now],true)));
			foreach ($data[$now] as $key => $record) {
					
					db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('',"RRD:$rrdFile, data now:".print_r($data[$now][$key],true)));
					db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('',"RRD:$rrdFile, data before:".print_r($data[$now][$key],true)));
					$delta = sprintf("%.2f",($data[$now][$key]['value'] -  $data[$before][$key]['value']) / $timeDelta);
					cacti_log("process_poller_deltas(): RRD: $rrdFile, Key: $key, Delta: $delta", true, "NEIGHBOR POLLER");
					db_execute_prepared("INSERT INTO plugin_neighbor_poller_delta VALUES ('',?,?,?,?,?)",array($rrdFile,$now,$timestamp_cycle,$key,$delta));
			}
		}
	}
	
	/* Nothing older than 15 minutes */
	db_execute_prepared("DELETE FROM plugin_neighbor_poller_delta where timestamp < ?", array(time() - 900));
}


/**
 * Get the SNMP OID table for neighbor discovery protocols
 *
 * Returns an associative array of OID definitions for CDP, LLDP, and IP MIBs
 * used during neighbor discovery polling.
 *
 * @return array The OID table keyed by protocol field name
 */
function get_neighbor_oid_table() {
	return array(
		// CISCO-CDP-MIB
		'cdpMibWalk'        => array('1.3.6.1.4.1.9.9.23.1.2.1.1'),
		'cdpCacheIfIndex'   => '1.3.6.1.4.1.9.9.23.1.2.1.1.1',
		'cdpCacheVersion'   => '1.3.6.1.4.1.9.9.23.1.2.1.1.5',
		'cdpCacheDeviceId'  => '1.3.6.1.4.1.9.9.23.1.2.1.1.6',
		'cdpCacheDevicePort'=> '1.3.6.1.4.1.9.9.23.1.2.1.1.7',
		'cdpCachePlatform'  => '1.3.6.1.4.1.9.9.23.1.2.1.1.8',
		'cdpCacheDuplex'    => '1.3.6.1.4.1.9.9.23.1.2.1.1.12',
		'cdpCacheUptime'    => '1.3.6.1.4.1.9.9.23.1.2.1.1.24',

		// LLDP-MIB
		'lldpMibWalk'       => array('1.0.8802.1.1.2.1.3.7.1.4','1.0.8802.1.1.2.1.4.1','.1.0.8802.1.1.2.1.4.2'),
		'lldpLocPortDesc'   => '1.0.8802.1.1.2.1.3.7.1.4',
		'lldpRemPortId'     => '1.0.8802.1.1.2.1.4.1.1.7',
		'lldpRemPortDesc'   => '1.0.8802.1.1.2.1.4.1.1.8',
		'lldpRemSysName'    => '1.0.8802.1.1.2.1.4.1.1.9',
		'lldpRemSysDesc'    => '1.0.8802.1.1.2.1.4.1.1.10',
		'lldpRemManAddrIfId'=> '1.0.8802.1.1.2.1.4.2.1.4',

		// IP-MIB
		'ipMibWalk' => array('1.3.6.1.2.1.4.20.1','1.3.6.1.3.118.1.2.1'),
		'ipIpAddr'  => '1.3.6.1.2.1.4.20.1.2',
		'ifNetmask' => '1.3.6.1.2.1.4.20.1.3',
		'ciscoVrf'  => '1.3.6.1.3.118.1.2.1.1',
	);
}

/**
 * Perform SNMP walks for given OIDs and flatten results into a keyed array
 *
 * Walks multiple OID trees on a host and returns a single associative array
 * keyed by full OID with the corresponding SNMP value.
 *
 * @param array $host Host array from database with SNMP credentials
 * @param array $walkOids Array of base OIDs to walk
 * @return array Associative array of OID => value pairs
 */
function neighbor_snmp_walk_and_flatten($host, $walkOids) {
	$results = array();

	foreach ($walkOids as $oid) {
		$walked = plugin_cacti_snmp_walk(
			$host['hostname'], $host['snmp_community'],
			$oid, $host['snmp_version'], $host['snmp_username'],
			$host['snmp_password'], $host['snmp_auth_protocol'],
			$host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
			$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'],
			read_config_option('snmp_retries'), $host['max_oids']
		);

		foreach ($walked as $rec) {
			$oidKey = isset($rec['oid']) ? $rec['oid'] : '';
			$value  = isset($rec['value']) ? $rec['value'] : '';
			if ($oidKey !== '') {
				$results[$oidKey] = $value;
			}
		}
	}

	return $results;
}

