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

include_once(__DIR__ . '/neighbor_functions.php');

/**
 * Get the edge RRA
 *
 * @return array The edge RRA
 */
function get_edge_rra() {
	$rrd_array = [];
	$rows      = db_fetch_assoc('SELECT rrd_file from plugin_neighbor_edge');

	if (!is_array($rows)) {
		return $rrd_array;
	}

	foreach ($rows as $row) {
		$rrd = isset($row['rrd_file']) ? $row['rrd_file'] : '';

		if ($rrd) {
			$rrd_array[$rrd] = $row;
		}
	}

	return $rrd_array;
}

/**
 * Process the poller output
 *
 * @param  array $rrd_update_array The RRD update array
 * @return array The updated RRD update array
 */
function neighbor_poller_output(&$rrd_update_array) {
	global $config, $debug;

	$edge_rra = get_edge_rra();
	$path_rra = $config['rra_path'];

	foreach ($rrd_update_array as $rrd => $rec) {
		$rra_subst = str_replace($path_rra,'<path_rra>',$rrd);

		if (!isset($edge_rra[$rra_subst])) {
			continue;
		}

		$rec_json = json_encode($rec);

		foreach ($rec['times'] as $time => $data) {
			foreach ($data as $key => $counter) {
				db_execute_prepared("INSERT INTO plugin_neighbor_poller_output
				     VALUES ('', ?, ?, ?, ?, NOW())
				     ON DUPLICATE KEY UPDATE
				     key_name = ?,
				     value = ?",
					[$rra_subst, $time, $key, $counter, $key, $counter]);
			}
		}
	}

	db_execute_prepared('DELETE FROM plugin_neighbor_poller_output
		WHERE timestamp < ?',
		[time() - 900]);

	return $rrd_update_array;
}

/**
 * Process the deltas from the poller_output hook
 * Called from poller_bottom hook
 */
function process_poller_deltas() {
	cacti_log('process_poller_deltas() is running', true, 'NEIGHBOR POLLER');

	db_execute_prepared('INSERT INTO plugin_neighbor_log
		VALUES (?, NOW(), ?)',
		['', 'process_poller_deltas() is starting.']);

	$results = db_fetch_assoc('SELECT * FROM plugin_neighbor_poller_output');

	if (!is_array($results)) {
		return;
	}

	$hash = db_fetch_hash($results,['rrd_file', 'timestamp', 'key_name']);

	if (!is_array($hash)) {
		return;
	}

	foreach ($hash as $rrdFile => $data) {
		cacti_log("process_poller_deltas() is processing RRD:$rrdFile,with data:" . print_r($data,1), true, 'NEIGHBOR POLLER');

		$timestamps = array_keys($data);
		rsort($timestamps);

		db_execute_prepared('INSERT INFO plugin_neighbor_log
			VALUES (?, NOW(), ?)',
			['', 'process_poller_deltas() is running. Timestamps:' . print_r($timestamps,1)]);

		if (sizeof($timestamps) >= 2) {
			$now             = $timestamps[0];
			$before          = $timestamps[1];
			$timeDelta       = $now - $before;
			$poller_interval = read_config_option('poller_interval') ? read_config_option('poller_interval') : 300;

			// Normalise these down to a poller cycle boundary to group them together
			$timestamp_cycle = intval($now / $poller_interval) * $poller_interval;

			cacti_log("process_poller_deltas(): now:$now, before:$before, Hash:" . print_r($data[$now],true), true, 'NEIGHBOR POLLER');

			db_execute_prepared('INSERT INTO plugin_neighbor_log
				VALUES (?, NOW(), ?)',
				['', "Now:$now, Before:$before, Hash:" . print_r($data[$now],true)]);

			foreach ($data[$now] as $key => $record) {
				db_execute_prepared('INSERT INTO plugin_neighbor_log
					VALUES (?, NOW(), ?)',
					['', "RRD:$rrdFile, data now:" . print_r($data[$now][$key],true)]);

				db_execute_prepared('INSERT INTO plugin_neighbor_log
					VALUES (?, NOW(), ?)',
					['', "RRD:$rrdFile, data before:" . print_r($data[$now][$key],true)]);

				$delta = sprintf('%.2f',($data[$now][$key]['value'] - $data[$before][$key]['value']) / $timeDelta);

				cacti_log("process_poller_deltas(): RRD: $rrdFile, Key: $key, Delta: $delta", true, 'NEIGHBOR POLLER');

				db_execute_prepared("INSERT INTO plugin_neighbor_poller_delta
					VALUES ('',?,?,?,?,?)",
					[$rrdFile, $now, $timestamp_cycle, $key, $delta]);
			}
		}
	}

	// Nothing older than 15 minutes
	db_execute_prepared('DELETE FROM plugin_neighbor_poller_delta where timestamp < ?', [time() - 900]);
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
	return [
		// CISCO-CDP-MIB
		'cdpMibWalk'        => ['1.3.6.1.4.1.9.9.23.1.2.1.1'],
		'cdpCacheIfIndex'   => '1.3.6.1.4.1.9.9.23.1.2.1.1.1',
		'cdpCacheVersion'   => '1.3.6.1.4.1.9.9.23.1.2.1.1.5',
		'cdpCacheDeviceId'  => '1.3.6.1.4.1.9.9.23.1.2.1.1.6',
		'cdpCacheDevicePort'=> '1.3.6.1.4.1.9.9.23.1.2.1.1.7',
		'cdpCachePlatform'  => '1.3.6.1.4.1.9.9.23.1.2.1.1.8',
		'cdpCacheDuplex'    => '1.3.6.1.4.1.9.9.23.1.2.1.1.12',
		'cdpCacheUptime'    => '1.3.6.1.4.1.9.9.23.1.2.1.1.24',

		// LLDP-MIB
		'lldpMibWalk'       => ['1.0.8802.1.1.2.1.3.7.1.4', '1.0.8802.1.1.2.1.4.1', '.1.0.8802.1.1.2.1.4.2'],
		'lldpLocPortDesc'   => '1.0.8802.1.1.2.1.3.7.1.4',
		'lldpRemPortId'     => '1.0.8802.1.1.2.1.4.1.1.7',
		'lldpRemPortDesc'   => '1.0.8802.1.1.2.1.4.1.1.8',
		'lldpRemSysName'    => '1.0.8802.1.1.2.1.4.1.1.9',
		'lldpRemSysDesc'    => '1.0.8802.1.1.2.1.4.1.1.10',
		'lldpRemManAddrIfId'=> '1.0.8802.1.1.2.1.4.2.1.4',

		// IP-MIB
		'ipMibWalk' => ['1.3.6.1.2.1.4.20.1', '1.3.6.1.3.118.1.2.1'],
		'ipIpAddr'  => '1.3.6.1.2.1.4.20.1.2',
		'ifNetmask' => '1.3.6.1.2.1.4.20.1.3',
		'ciscoVrf'  => '1.3.6.1.3.118.1.2.1.1',
	];
}

/**
 * Perform SNMP walks for given OIDs and flatten results into a keyed array
 *
 * Walks multiple OID trees on a host and returns a single associative array
 * keyed by full OID with the corresponding SNMP value.
 *
 * @param  array $host     Host array from database with SNMP credentials
 * @param  array $walkOids Array of base OIDs to walk
 * @return array Associative array of OID => value pairs
 */
function neighbor_snmp_walk_and_flatten($host, $walkOids) {
	$results = [];

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

/**
 * Constants for IP neighbor discovery
 */
define('NEIGHBOR_LOOPBACK_MASK', '255.255.255.255');
define('NEIGHBOR_DEFAULT_DEADTIMER', 60);
define('NEIGHBOR_DEFAULT_SUBNET_CORRELATION', 30);

/**
 * Discovers CDP neighbors for a given host
 *
 * Performs SNMP walk of Cisco Discovery Protocol MIB to identify directly
 * connected network devices and their interface relationships.
 *
 * @param  array $host Host array from database with SNMP credentials
 * @return int   Number of neighbors discovered and stored
 */
function discoverCdpNeighbors($host) {
	debug('Processing CDP Neighbors: ' . $host['description']);
	$oidTable   = get_neighbor_oid_table();
	$cdpTable   = neighbor_snmp_walk_and_flatten($host, $oidTable['cdpMibWalk']);
	$neighCount = 0;

	$cdpParsed = [];

	foreach ($cdpTable as $oid => $val) {
		if (preg_match('/' . $oidTable['cdpCacheDeviceId'] . '\.(\d+\.\d+)/',$oid,$matches)) {
			$index                       = isset($matches[1]) ? $matches[1] : '';
			$cdpParsed[$index]['device'] = $val;
		} elseif (preg_match('/' . $oidTable['cdpCacheDevicePort'] . '\.(\d+\.\d+)/',$oid,$matches)) {
			$index                          = isset($matches[1]) ? $matches[1] : '';
			$cdpParsed[$index]['interface'] = $val;
		} elseif (preg_match('/' . $oidTable['cdpCacheVersion'] . '\.(\d+\.\d+)/',$oid,$matches)) {
			$index                        = isset($matches[1]) ? $matches[1] : '';
			$cdpParsed[$index]['version'] = $val;
		} elseif (preg_match('/' . $oidTable['cdpCachePlatform'] . '\.(\d+\.\d+)/',$oid,$matches)) {
			$index                         = isset($matches[1]) ? $matches[1] : '';
			$cdpParsed[$index]['platform'] = $val;
		} elseif (preg_match('/' . $oidTable['cdpCacheDuplex'] . '\.(\d+\.\d+)/',$oid,$matches)) {
			$index = isset($matches[1]) ? $matches[1] : '';

			if ($val == 1) {
				$duplex = 'unknown';
			} elseif ($val == 2) {
				$duplex = 'half';
			} else {
				$duplex = 'full';
			}
			$cdpParsed[$index]['duplex'] = $duplex;
		} elseif (preg_match('/' . $oidTable['cdpCacheUptime'] . '\.(\d+\.\d+)/',$oid,$matches)) {
			$index                       = isset($matches[1]) ? $matches[1] : '';
			$uptime                      = is_numeric($val) ? intval($val / 1000) : 0;
			$cdpParsed[$index]['uptime'] = $uptime;
		}
	}

	foreach ($cdpParsed as $index => $record) {
		// Create a unique hash of the neighbor based on the record
		[$snmpId,$idx] = explode('.',$index);
		$myHostId      = $host['id'];
		$myIntRecord   = findCactiInterface($host['id'],'',$snmpId);

		if (!$myIntRecord) {
			debug("Error: Couldn't own find Cacti interface record for host=$myHostId, snmp_index=$snmpId");

			continue;
		}

		$myIp			       = isset($host['hostname']) ? $host['hostname'] : '';
		$myHostname		  = isset($host['description']) ? $host['description'] : '';
		$myIntName 		  = isset($myIntRecord['ifDescr']) ? $myIntRecord['ifDescr'] : '';
		$myIntAlias 	  = isset($myIntRecord['ifAlias']) ? $myIntRecord['ifAlias'] : '';
		$myIntSpeed 	  = isset($myIntRecord['ifHighSpeed']) ? $myIntRecord['ifHighSpeed'] : inferIntSpeed($myIntName);
		$myIntStatus 	 = isset($myIntRecord['ifOperStatus']) ? $myIntRecord['ifOperStatus'] : '';
		$myIntIp 		    = isset($myIntRecord['ifIP']) ? $myIntRecord['ifIP'] : '';
		$myIntHwAddr 	 = isset($myIntRecord['ifHwAddr']) ? $myIntRecord['ifHwAddr'] : '';

		$neighHostname 	  = preg_replace('/\..+/','',$record['device']);				// This is a nasty way of stripping a domain
		$neighPlatform 	  = $record['platform'];
		$neighSoftware 	  = $record['version'];
		$neighDuplex 	    = $record['duplex'];
		$neighUptime 	    = $record['uptime'];
		$neighInterface 	 = $record['interface'];
		$neighRecord      = findCactiHost($neighHostname);
		$neighHostId      = isset($neighRecord[$neighHostname]['id']) ? $neighRecord[$neighHostname]['id'] : '';
		$neighIntRecord   = findCactiInterface($neighHostId,$neighInterface);

		if ($neighHostId && $neighIntRecord) {
			$neighSnmpId	      = isset($neighIntRecord['snmp_index']) ? $neighIntRecord['snmp_index'] : '';
			$neighIntName      = isset($neighIntRecord['ifDescr']) ? $neighIntRecord['ifDescr'] : '';
			$neighIntAlias     = isset($neighIntRecord['ifAlias']) ? $neighIntRecord['ifAlias'] : '';
			$neighIntSpeed     = isset($neighIntRecord['ifHighSpeed']) ? $neighIntRecord['ifHighSpeed'] : inferIntSpeed($neighIntName);
			$neighIntStatus    = isset($neighIntRecord['ifOperStatus']) ? $neighIntRecord['ifOperStatus'] : '';
			$neighIntIp        = isset($neighIntRecord['ifIP']) ? $neighIntRecord['ifIP'] : '';
			$neighIntHwAddr    = isset($neighIntRecord['ifHwAddr']) ? $neighIntRecord['ifHwAddr'] : '';
		} else {
			$neighSnmpId       = 0;
			$neighIntName      = $neighInterface;
			$neighIntAlias     = '';
			$neighIntSpeed     = inferIntSpeed($neighInterface);
			$neighIntStatus    = '';
			$neighIntIp        = '';
			$neighIntHwAddr    = '';
		}

		$hashArray = [	$host['id'], 'cdp', $myIp, $myHostname, $snmpId,
					$myIntName, $myIntAlias, $myIntSpeed, $myIntStatus, $myIntIp, $myIntHwAddr,
					$neighHostId, $neighHostname, $neighSnmpId,
					$neighIntName, $neighIntAlias, $neighIntSpeed, $neighIntStatus, $neighIntIp, $neighIntHwAddr,
					$neighPlatform, $neighSoftware, $neighDuplex];

		$hostArray = [$myHostname, $neighHostname];
		sort($hostArray);
		$intArray = [$myIntName, $neighIntName];
		sort($intArray);
		$neighArray = array_merge($hostArray,$intArray);		// Sort the values to  make them even for each neighbor pair

		$recordHash = md5(serialize($hashArray));												// This should be unique to each CDP entry
		$neighHash  = md5(serialize($neighArray));												// This should allow us to pair neighbors together

		if (db_execute_prepared('REPLACE  INTO `plugin_neighbor_xdp`
				       (`host_id`, `type`,`host_ip`, `hostname`, `snmp_id`,
					`interface_name`, `interface_alias`, `interface_speed`, `interface_status`, `interface_ip`, `interface_hwaddr`,
					`neighbor_host_id`, `neighbor_hostname`, `neighbor_snmp_id`,
					`neighbor_interface_name`, `neighbor_interface_alias`, `neighbor_interface_speed`,
					`neighbor_interface_status`, `neighbor_interface_ip`, `neighbor_interface_hwaddr`,
					`neighbor_platform`, `neighbor_software`, `neighbor_duplex`,
					`neighbor_last_changed`, `last_seen`,`neighbor_hash`, `record_hash`)
				    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_SUB(NOW(),INTERVAL ? SECOND),NOW(),?,?)',
			array_merge($hashArray,[$neighUptime, $neighHash, $recordHash])
		)) {
			$neighCount++;
		}
	}

	return ($neighCount);
}

/**
 * Discovers LLDP neighbors for a given host
 *
 * Performs SNMP walk of Link Layer Discovery Protocol MIB to identify
 * vendor-neutral neighbor relationships.
 *
 * @param  array $host Host array from database with SNMP credentials
 * @return int   Number of neighbors discovered and stored
 */
function discoverLldpNeighbors($host) {
	$oidTable = get_neighbor_oid_table();
	debug('Processing LLDP Neighbors: ' . $host['description']);
	$neighCount = 0;
	$hostId     = $host['id'];
	$lldpTable  = neighbor_snmp_walk_and_flatten($host, $oidTable['lldpMibWalk']);

	$lldpParsed = [];
	$lldpToSnmp = [];

	foreach ($lldpTable as $oid => $val) {
		if (preg_match('/' . $oidTable['lldpLocPortDesc'] . '\.(\d+)/',$oid,$matches)) {
			$index              = isset($matches[1]) ? $matches[1] : '';
			$intRec             = findCactiInterface($hostId,$val);
			$snmpIndex          = isset($intRec['snmp_index']) ? $intRec['snmp_index'] : $index;
			$lldpToSnmp[$index] = $snmpIndex;
		} elseif (preg_match('/' . $oidTable['lldpRemPortDesc'] . '\.\d+\.(\d+\.\d+)/',$oid,$matches)) {
			[$portIndex,$lldpIndex] = isset($matches[1]) ? explode('.',$matches[1]) : ['', ''];
			$snmpIndex              = isset($lldpToSnmp[$portIndex]) ? $lldpToSnmp[$portIndex] : '';
			// debug("lldpRemPort: portIndex: $portIndex, lldpIndex: $lldpIndex, snmpIndex: $snmpIndex\n");
			$lldpParsed["$snmpIndex.$lldpIndex"]['interface'] = $val;
			$lldpParsed["$snmpIndex.$lldpIndex"]['duplex']    = 'unknown';				// No duplex in the MIB
			$lldpParsed["$snmpIndex.$lldpIndex"]['uptime']    = 0;					// No timeticks in the MIB
		} elseif (preg_match('/' . $oidTable['lldpRemSysName'] . '\.\d+\.(\d+\.\d+)/',$oid,$matches)) {
			[$portIndex,$lldpIndex]                        = isset($matches[1]) ? explode('.',$matches[1]) : ['', ''];
			$snmpIndex                                     = isset($lldpToSnmp[$portIndex]) ? $lldpToSnmp[$portIndex] : '';
			$lldpParsed["$snmpIndex.$lldpIndex"]['device'] = $val;
		} elseif (preg_match('/' . $oidTable['lldpRemSysDesc'] . '\.\d+\.(\d+\.\d+)/',$oid,$matches)) {
			[$portIndex,$lldpIndex]                          = isset($matches[1]) ? explode('.',$matches[1]) : ['', ''];
			$snmpIndex                                       = isset($lldpToSnmp[$portIndex]) ? $lldpToSnmp[$portIndex] : '';
			$lldpParsed["$snmpIndex.$lldpIndex"]['version']  = $val;
			$lldpParsed["$snmpIndex.$lldpIndex"]['platform'] = (is_string($val) ? strtok($val, "\n") : '');			// The first line of lldpRemSysDesc is closest to platform inc CDP
		}
	}

	// Update the Database

	foreach ($lldpParsed as $index => $record) {
		// Create a unique hash of the neighbor based on the record
		[$snmpId,$idx] = explode('.',$index);
		$myHostId      = $host['id'];
		$myIntRecord   = findCactiInterface($host['id'],'',$snmpId);

		if (!$myIntRecord) {
			debug("Error: Couldn't own find Cacti interface record for host=$myHostId, snmp_index=$snmpId");

			continue;
		}

		$myIp         = isset($host['hostname']) ? $host['hostname'] : '';
		$myHostname   = isset($host['description']) ? $host['description'] : '';
		$myIntName    = isset($myIntRecord['ifDescr']) ? $myIntRecord['ifDescr'] : '';
		$myIntAlias   = isset($myIntRecord['ifAlias']) ? $myIntRecord['ifAlias'] : '';
		$myIntSpeed   = isset($myIntRecord['ifHighSpeed']) ? $myIntRecord['ifHighSpeed'] : inferIntSpeed($myIntName);
		$myIntStatus  = isset($myIntRecord['ifOperStatus']) ? $myIntRecord['ifOperStatus'] : '';
		$myIntIp      = isset($myIntRecord['ifIP']) ? $myIntRecord['ifIP'] : '';
		$myIntHwAddr  = isset($myIntRecord['ifHwAddr']) ? $myIntRecord['ifHwAddr'] : '';

		$neighHostname    = preg_replace('/\..+/','',$record['device']);
		$neighPlatform    = $record['platform'];
		$neighSoftware    = $record['version'];
		$neighDuplex      = $record['duplex'] ?? '';
		$neighUptime      = $record['uptime'] ?? '';
		$neighInterface   = $record['interface'] ?? '';
		$neighRecord      = findCactiHost($neighHostname);
		$neighHostId      = isset($neighRecord[$neighHostname]['id']) ? $neighRecord[$neighHostname]['id'] : '';
		$neighIntRecord   = findCactiInterface($neighHostId,$neighInterface);

		// If neighbor is in Cacti, use cached interface details; otherwise use LLDP-reported values
		if ($neighHostId && $neighIntRecord) {
			$neighSnmpId       = isset($neighIntRecord['snmp_index']) ? $neighIntRecord['snmp_index'] : '';
			$neighIntName      = isset($neighIntRecord['ifDescr']) ? $neighIntRecord['ifDescr'] : '';
			$neighIntAlias     = isset($neighIntRecord['ifAlias']) ? $neighIntRecord['ifAlias'] : '';
			$neighIntSpeed     = isset($neighIntRecord['ifHighSpeed']) ? $neighIntRecord['ifHighSpeed'] : inferIntSpeed($neighIntName);
			$neighIntStatus    = isset($neighIntRecord['ifOperStatus']) ? $neighIntRecord['ifOperStatus'] : '';
			$neighIntIp        = isset($neighIntRecord['ifIP']) ? $neighIntRecord['ifIP'] : '';
			$neighIntHwAddr    = isset($neighIntRecord['ifHwAddr']) ? $neighIntRecord['ifHwAddr'] : '';
		} else {
			// Neighbor not in Cacti - use LLDP-reported interface name directly
			$neighSnmpId       = 0;
			$neighIntName      = $neighInterface;  // Use the LLDP-reported remote port
			$neighIntAlias     = '';
			$neighIntSpeed     = inferIntSpeed($neighInterface);
			$neighIntStatus    = '';
			$neighIntIp        = '';
			$neighIntHwAddr    = '';
		}

		$hashArray = [	$host['id'], 'lldp', $myIp, $myHostname, $snmpId,
					$myIntName, $myIntAlias, $myIntSpeed, $myIntStatus, $myIntIp, $myIntHwAddr,
					$neighHostId, $neighSnmpId,
					$neighIntName, $neighIntAlias, $neighIntSpeed, $neighIntStatus, $neighIntIp, $neighIntHwAddr,
					$neighHostname, $neighPlatform, $neighSoftware, $neighDuplex];

		$hostArray = [$myHostname, $neighHostname];
		sort($hostArray);
		$intArray = [$myIntName, $neighIntName];
		sort($intArray);
		$neighArray = array_merge($hostArray,$intArray);		// Sort the values to  make them even for each neighbor pair

		$recordHash = md5(serialize($hashArray));												// This should be unique to each CDP entry
		$neighHash  = md5(serialize($neighArray));												// This should allow us to pair neighbors together

		if (db_execute_prepared('REPLACE  INTO `plugin_neighbor_xdp`
			(`host_id`, `type`,`host_ip`, `hostname`, `snmp_id`,
			`interface_name`, `interface_alias`, `interface_speed`, `interface_status`, `interface_ip`, `interface_hwaddr`,
					`neighbor_host_id`, `neighbor_hostname`, `neighbor_snmp_id`,
					`neighbor_interface_name`, `neighbor_interface_alias`, `neighbor_interface_speed`,
					`neighbor_interface_status`, `neighbor_interface_ip`, `neighbor_interface_hwaddr`,
					`neighbor_platform`, `neighbor_software`, `neighbor_duplex`,
					`neighbor_last_changed`, `neighbor_hash`, `record_hash`)
				    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_SUB(NOW(),INTERVAL ? SECOND),?,?)',
			array_merge($hashArray,[$neighUptime, $neighHash, $recordHash])
		)) {
			$neighCount++;
		}
	}

	return ($neighCount);
}

/**
 * Fetch IPv4 cache entries for neighbor correlation
 *
 * Retrieves IP address and subnet information that was collected via SNMP
 * for use in finding IP-based neighbor relationships.
 *
 * @param  int|null $hostId Optional host ID to filter results
 * @return array    Nested array indexed by [vrf][ip_address]
 */
function getIpv4Cache($hostId = null) {
	if ($hostId) {
		$query  = db_fetch_assoc_prepared('SELECT * from plugin_neighbor_ipv4_cache where host_id=?',[$hostId]);
		$result = db_fetch_hash($query,['vrf', 'ip_address']);

		return ($result);
	} else {
		$query  = db_fetch_assoc('SELECT * from plugin_neighbor_ipv4_cache');
		$result = db_fetch_hash($query,['vrf', 'ip_address']);

		return ($result);
	}
}
