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



if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
        die('<br><strong>This script is only meant to run at the command line.</strong>');
}

// Constants
define('NEIGHBOR_PROCESS_TIMEOUT', 600);    // 10 minutes - Purge stale process locks
define('NEIGHBOR_DATA_RETENTION', 900);     // 15 minutes - Data retention for poller output/deltas

declare(ticks = 1);
ini_set('max_execution_time', '0');
$dir = dirname(__FILE__);
chdir($dir);
include_once (dirname(__FILE__) . '/../../include/global.php');

// For some reason, including global breaks if you've changed directory first. No idea.

$dir = dirname(__FILE__);
chdir($dir);
if (strpos($dir, 'plugins') !== false) { chdir('../../'); }
include_once('lib/snmp.php');
include_once('lib/ping.php');
include_once('lib/poller.php');
include_once('lib/data_query.php');
include_once('plugins/neighbor/lib/neighbor_functions.php');
include_once('plugins/neighbor/lib/neighbor_sql_tables.php');
include_once('plugins/neighbor/lib/polling.php');

if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGINT, "sigHandler");
	pcntl_signal(SIGTERM, "sigHandler");
}
	
	// Global Variables
	$parms = $_SERVER['argv'];
	array_shift($parms);
	global $debug, $verbose, $start, $seed, $forceRun;	
	$debug	  	= FALSE;
	$verbose	= FALSE;
	$forceRun   	= FALSE;
	$mainRun	= FALSE;
	$autoDiscoverAll = FALSE;
	$hostId		= '';
	$start	  	= '';
	$seed	  	= '';
	$key	    	= '';
	$dieNow		= '';			# Capture the signal handlers and die cleanly

	global $dieNow;

	if (sizeof($parms)) {
		foreach ($parms as $parameter) {
			if (strpos($parameter, '=')) {
				list($arg, $value) = explode('=', $parameter);
			} else {
				$arg   = $parameter;
				$value = '';
			}
		
		   switch ($arg) {
			case '-d':
			case '--debug':
				$debug = TRUE;
				break;
			
			case '--host-id':
				$hostId = $value;
				break;
			
			case '--start':
				$start = $value;
				break;
			
			case '--seed':
				$seed = $value;
				break;

			case '--key':
				$key = $value;
				break;
			
			case '-f':
			case '--force':
				$forceRun = TRUE;
				break;
			
			case '-M':
				$mainRun = TRUE;
				break;
			case '-A':
			case '--auto-discover-all':
				$autoDiscoverAll = TRUE;
				break;
		       	case '--verbose':
				$verbose = TRUE;
				break;
			case '--debug':
				$verbose = TRUE;
				$debug   = TRUE;
				break; 
			case '--version':
			case '-V':
			case '-v':
				displayVersion();
				exit;
			case '--help':
			case '-H':
			case '-h':
				displayHelp();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				displayHelp();
				exit;
		   }
		}
	}
	
	/* Check for mandatory parameters */

	if (!$mainRun && !$autoDiscoverAll && $hostId == '') {
		$mainRun = TRUE;  // Default to processing all hosts
	}
	
	/* Do not process if not enabled */
	
	if (read_config_option('neighbor_global_enabled') == '') {
		cacti_log("NEIGHBOR: Info: Neighbor polling is disabled in the settings.", true, "NEIGHBOR");
		exit(0);
	}

	$seed = $seed ? $seed : rand();
	
	if ($start == '') {
		list($micro, $seconds) = explode(' ', microtime());
		$start = $seconds + $micro;
	}
	if ($mainRun) {
		processHosts();
	}
	elseif ($autoDiscoverAll) {
		debug('Auto-discovering all hosts...');
		autoDiscoverHosts();
	}
	else {
		discoverHost($hostId);
	}
	
	exit(0);

function runCollector($start, $lastrun, $frequency)
{
	global $forceRun, $dieNow;

	if ((empty($lastrun) || ($start - $lastrun) > $frequency) && $frequency > 0 || $forceRun) {
		return true;
	}
	elseif ($dieNow) { 
		return false;
	} 
	else {
		return false;
	}
}

function debug($message)
{
	global $debug;
	
	// Format timestamp
	$timestamp = date('H:i:s');
	
	if ($debug) {
		// Clean terminal output with timestamp and consistent prefix
		echo $timestamp . ' NEIGHBOR DEBUG: ' . trim($message) . "\n";
	}
	// Also log to file for non-debug runs
	cacti_log('NEIGHBOR DEBUG: ' . trim($message), FALSE, 'NEIGHBOR');
}

// neighbor_host_discovery_enabled() function is now in lib/neighbor_sql_tables.php
/**
 * Discovers neighbors for a specific host.
 *
 * @param int $hostId The ID of the host to discover.
 * @return bool True if discovery was successful, false otherwise.
 */
function discoverHost($hostId)
{
	global $debug, $key;
	
	$hostRec = db_fetch_assoc_prepared("SELECT * from host where id = ?",array($hostId));
	if (isset($hostRec[0])) { 
		debug(str_repeat('-', 85));
		debug(sprintf("Starting Discovery: %s [ID=%d]",$hostRec[0]['description'],$hostId));
		debug(str_repeat('-', 85));
		
		/* set a process lock */
		if ($key) {
			debug("Process Lock ID: $key");
		}
		db_execute_prepared('REPLACE INTO plugin_neighbor_processes (pid, taskid) VALUES (?,?)',array($key,0));
		debug(str_repeat('-', 85));
		
		if (read_config_option('neighbor_global_discover_cdp') && neighbor_host_discovery_enabled($hostRec[0], 'neighbor_discover_cdp')) {
		    $cdpNeighbors = discoverCdpNeighbors($hostRec[0]);
			debug(sprintf("Found   %7d - CDP Neighbor(s)",$cdpNeighbors));
		}
		
		if (read_config_option('neighbor_global_discover_lldp') && neighbor_host_discovery_enabled($hostRec[0], 'neighbor_discover_lldp')) {
		    $lldpNeighbors = discoverLldpNeighbors($hostRec[0]);
			debug(sprintf("Found   %7d - LLDP Neighbor(s)",$lldpNeighbors));
		}
		
		if (read_config_option('neighbor_global_discover_ip') && neighbor_host_discovery_enabled($hostRec[0], 'neighbor_discover_ip')) {
		    discoverIpNeighbors($hostRec[0]);
		}
		
		// $statsJson = json_encode($stats);
		/* remove the process lock */
		db_execute_prepared('DELETE FROM plugin_neighbor_processes WHERE pid=?', array($key));
		
		db_execute_prepared("REPLACE INTO settings (name,value) VALUES (?, ?)", array('plugin_neighbor_last_run', time()));
		return true;
	}
}

/**
 * Discovers CDP neighbors for a given host.
 *
 * @param array $host Host array from database.
 * @return int Number of neighbors found.
 */
function discoverCdpNeighbors($host)
{
	debug("Processing CDP Neighbors: " . $host['description']);
	$oidTable = get_neighbor_oid_table();
	$cdpTable = neighbor_snmp_walk_and_flatten($host, $oidTable['cdpMibWalk']);
	$neighCount = 0;

	$cdpParsed = array();
	foreach ($cdpTable as $oid => $val) { 

		if 	(preg_match('/'.$oidTable['cdpCacheDeviceId'].'\.(\d+\.\d+)/',$oid,$matches)) {
				$index = isset($matches[1]) ? $matches[1] : '';
				$cdpParsed[$index]['device'] = $val;
		}	
		elseif (preg_match('/'.$oidTable['cdpCacheDevicePort'].'\.(\d+\.\d+)/',$oid,$matches)) {
                                $index = isset($matches[1]) ? $matches[1] : '';
                                $cdpParsed[$index]['interface'] = $val;
                }
		elseif (preg_match('/'.$oidTable['cdpCacheVersion'].'\.(\d+\.\d+)/',$oid,$matches)) {
                                $index = isset($matches[1]) ? $matches[1] : '';
                                $cdpParsed[$index]['version'] = $val;
                }
		elseif (preg_match('/'.$oidTable['cdpCachePlatform'].'\.(\d+\.\d+)/',$oid,$matches)) {
                                $index = isset($matches[1]) ? $matches[1] : '';
                                $cdpParsed[$index]['platform'] = $val;
                }
		elseif (preg_match('/'.$oidTable['cdpCacheDuplex'].'\.(\d+\.\d+)/',$oid,$matches)) {
                                $index = isset($matches[1]) ? $matches[1] : '';
				if ($val == 1) {
					$duplex = 'unknown';
				} elseif ($val == 2) {
					$duplex = 'half';
				} else {
					$duplex = 'full';
				}
                                $cdpParsed[$index]['duplex'] = $duplex;
                }
		elseif (preg_match('/'.$oidTable['cdpCacheUptime'].'\.(\d+\.\d+)/',$oid,$matches)) {
                                $index = isset($matches[1]) ? $matches[1] : '';
				$uptime = is_numeric($val) ? intval($val/1000) : 0;
                                $cdpParsed[$index]['uptime'] = $uptime;
                }
	}


	foreach ($cdpParsed as $index => $record) { 

		// Create a unique hash of the neighbor based on the record
		list($snmpId,$idx) = explode(".",$index);
		$myHostId = $host['id'];
		$myIntRecord = findCactiInterface($host['id'],'',$snmpId);
		if (!$myIntRecord) { debug("Error: Couldn't own find Cacti interface record for host=$myHostId, snmp_index=$snmpId"); continue; } 
		
		$myIp			= isset($host['hostname']) 			? $host['hostname']		: "";
		$myHostname		= isset($host['description']) 		? $host['description']		: "";
		$myIntName 		= isset($myIntRecord['ifDescr']) 	? $myIntRecord['ifDescr'] 	: "";
		$myIntAlias 	= isset($myIntRecord['ifAlias']) 	? $myIntRecord['ifAlias'] 	: "";
		$myIntSpeed 	= isset($myIntRecord['ifHighSpeed']) 	? $myIntRecord['ifHighSpeed'] 	: inferIntSpeed($myIntName);
		$myIntStatus 	= isset($myIntRecord['ifOperStatus']) 	? $myIntRecord['ifOperStatus'] 	: "";
		$myIntIp 		= isset($myIntRecord['ifIP']) 		? $myIntRecord['ifIP'] 		: "";
		$myIntHwAddr 	= isset($myIntRecord['ifHwAddr']) 	? $myIntRecord['ifHwAddr'] 	: "";

		$neighHostname 	= preg_replace('/\..+/','',$record['device']);				// This is a nasty way of stripping a domain
		$neighPlatform 	= $record['platform'];
		$neighSoftware 	= $record['version'];
		$neighDuplex 	= $record['duplex'];
		$neighUptime 	= $record['uptime'];
		$neighInterface 	= $record['interface'];
		$neighRecord = findCactiHost($neighHostname);
		$neighHostId = isset($neighRecord[$neighHostname]['id']) ? $neighRecord[$neighHostname]['id'] : "";
		$neighIntRecord = findCactiInterface($neighHostId,$neighInterface);
		
		$neighSnmpId	      = isset($neighIntRecord['snmp_index'])     ? $neighIntRecord['snmp_index']    : "";
		$neighIntName      = isset($neighIntRecord['ifDescr'])        ? $neighIntRecord['ifDescr']       : "";
		$neighIntAlias     = isset($neighIntRecord['ifAlias'])        ? $neighIntRecord['ifAlias']       : "";
		$neighIntSpeed     = isset($neighIntRecord['ifHighSpeed'])    ? $neighIntRecord['ifHighSpeed']   : inferIntSpeed($neighIntName);
		$neighIntStatus    = isset($neighIntRecord['ifOperStatus'])   ? $neighIntRecord['ifOperStatus']  : "";
		$neighIntIp        = isset($neighIntRecord['ifIP'])           ? $neighIntRecord['ifIP']          : "";
		$neighIntHwAddr    = isset($neighIntRecord['ifHwAddr'])       ? $neighIntRecord['ifHwAddr']      : ""; 

		$hashArray = array(	$host['id'], 'cdp', $myIp, $myHostname,$snmpId,
					$myIntName, $myIntAlias,$myIntSpeed,$myIntStatus,$myIntIp,$myIntHwAddr,
					$neighHostId, $neighHostname, $neighSnmpId,
					$neighIntName, $neighIntAlias,$neighIntSpeed,$neighIntStatus,$neighIntIp,$neighIntHwAddr,
					$neighPlatform, $neighSoftware,$neighDuplex);

		$hostArray = array($myHostname,$neighHostname);
		sort($hostArray);
		$intArray = array($myIntName,$neighIntName);
		sort($intArray);
		$neighArray = array_merge($hostArray,$intArray);		// Sort the values to  make them even for each neighbor pair
		
		$recordHash = md5(serialize($hashArray));												// This should be unique to each CDP entry
		$neighHash = md5(serialize($neighArray));												// This should allow us to pair neighbors together
		
		if (db_execute_prepared("REPLACE  INTO `plugin_neighbor_xdp` 
				       (`host_id`, `type`,`host_ip`, `hostname`, `snmp_id`, 
					`interface_name`, `interface_alias`, `interface_speed`, `interface_status`, `interface_ip`, `interface_hwaddr`, 
					`neighbor_host_id`, `neighbor_hostname`, `neighbor_snmp_id`, 
					`neighbor_interface_name`, `neighbor_interface_alias`, `neighbor_interface_speed`, 
					`neighbor_interface_status`, `neighbor_interface_ip`, `neighbor_interface_hwaddr`, 
					`neighbor_platform`, `neighbor_software`, `neighbor_duplex`, 
					`neighbor_last_changed`, `last_seen`,`neighbor_hash`, `record_hash`)
				    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_SUB(NOW(),INTERVAL ? SECOND),NOW(),?,?)",
				    array_merge($hashArray,array($neighUptime,$neighHash,$recordHash))
		)) { $neighCount++;}
	}
	return($neighCount);
}

/**
 * Discovers LLDP neighbors for a given host.
 *
 * @param array $host Host array from database.
 * @return int Number of neighbors found.
 */
function discoverLldpNeighbors($host)
{
	$oidTable = get_neighbor_oid_table();
	debug("Processing LLDP Neighbors: " . $host['description']);
	$neighCount = 0;
	$hostId=$host['id'];
	$lldpTable = neighbor_snmp_walk_and_flatten($host, $oidTable['lldpMibWalk']);


	$lldpParsed = array();
	$lldpToSnmp = array();
	foreach ($lldpTable as $oid => $val) { 

		if 	(preg_match('/'.$oidTable['lldpLocPortDesc'].'\.(\d+)/',$oid,$matches)) {
				$index = isset($matches[1]) ? $matches[1] : '';
				$intRec = findCactiInterface($hostId,$val);
				$snmpIndex = isset($intRec['snmp_index']) ? $intRec['snmp_index'] : $index;
				$lldpToSnmp[$index] = $snmpIndex;
		}	
		elseif (preg_match('/'.$oidTable['lldpRemPortDesc'].'\.\d+\.(\d+\.\d+)/',$oid,$matches)) {
                                list($portIndex,$lldpIndex) = isset($matches[1]) ? explode(".",$matches[1]) : array("","");
				$snmpIndex = isset($lldpToSnmp[$portIndex]) ? $lldpToSnmp[$portIndex] : "";
				//debug("lldpRemPort: portIndex: $portIndex, lldpIndex: $lldpIndex, snmpIndex: $snmpIndex\n");
                                $lldpParsed["$snmpIndex.$lldpIndex"]['interface'] = $val;
				$lldpParsed["$snmpIndex.$lldpIndex"]['duplex'] = 'unknown';				// No duplex in the MIB
				$lldpParsed["$snmpIndex.$lldpIndex"]['uptime'] = 0;					// No timeticks in the MIB
                }
		elseif (preg_match('/'.$oidTable['lldpRemSysName'].'\.\d+\.(\d+\.\d+)/',$oid,$matches)) {
                                list($portIndex,$lldpIndex) = isset($matches[1]) ? explode(".",$matches[1]) : array("","");
				$snmpIndex = isset($lldpToSnmp[$portIndex]) ? $lldpToSnmp[$portIndex] : "";
                                $lldpParsed["$snmpIndex.$lldpIndex"]['device'] = $val;
                }
		elseif (preg_match('/'.$oidTable['lldpRemSysDesc'].'\.\d+\.(\d+\.\d+)/',$oid,$matches)) {
                                list($portIndex,$lldpIndex) = isset($matches[1]) ? explode(".",$matches[1]) : array("","");
				$snmpIndex = isset($lldpToSnmp[$portIndex]) ? $lldpToSnmp[$portIndex] : "";
                                $lldpParsed["$snmpIndex.$lldpIndex"]['version'] = $val;
                                $lldpParsed["$snmpIndex.$lldpIndex"]['platform'] = (is_string($val) ? strtok($val, "\n") : '');			// The first line of lldpRemSysDesc is closest to platform inc CDP
                }
	}

	// Update the Database

	foreach ($lldpParsed as $index => $record) { 

		// Create a unique hash of the neighbor based on the record
		list($snmpId,$idx) = explode(".",$index);
		$myHostId = $host['id'];
		$myIntRecord = findCactiInterface($host['id'],'',$snmpId);
		if (!$myIntRecord) { debug("Error: Couldn't own find Cacti interface record for host=$myHostId, snmp_index=$snmpId"); continue; } 
		
		$myIp		= isset($host['hostname']) 		? $host['hostname']		: "";
		$myHostname	= isset($host['description']) 		? $host['description']		: "";
		$myIntName 	= isset($myIntRecord['ifDescr']) 	? $myIntRecord['ifDescr'] 	: "";
		$myIntAlias 	= isset($myIntRecord['ifAlias']) 	? $myIntRecord['ifAlias'] 	: "";
		$myIntSpeed 	= isset($myIntRecord['ifHighSpeed']) 	? $myIntRecord['ifHighSpeed'] 	: inferIntSpeed($myIntName);
		$myIntStatus 	= isset($myIntRecord['ifOperStatus']) 	? $myIntRecord['ifOperStatus'] 	: "";
		$myIntIp 	= isset($myIntRecord['ifIP']) 		? $myIntRecord['ifIP'] 		: "";
		$myIntHwAddr 	= isset($myIntRecord['ifHwAddr']) 	? $myIntRecord['ifHwAddr'] 	: "";

	
		$neighHostname 	= preg_replace('/\..+/','',$record['device']);
		$neighPlatform 	= $record['platform'];
		$neighSoftware 	= $record['version'];
		$neighDuplex 	= $record['duplex'];
		$neighUptime 	= $record['uptime'];
		$neighInterface 	= $record['interface'];
		$neighRecord = findCactiHost($neighHostname);
		$neighHostId = isset($neighRecord[$neighHostname]['id']) ? $neighRecord[$neighHostname]['id'] : "";
		$neighIntRecord = findCactiInterface($neighHostId,$neighInterface);
		
		$neighSnmpId	      = isset($neighIntRecord['snmp_index'])     ? $neighIntRecord['snmp_index']    : "";
		$neighIntName      = isset($neighIntRecord['ifDescr'])        ? $neighIntRecord['ifDescr']       : "";
                $neighIntAlias     = isset($neighIntRecord['ifAlias'])        ? $neighIntRecord['ifAlias']       : "";
                $neighIntSpeed     = isset($neighIntRecord['ifHighSpeed'])    ? $neighIntRecord['ifHighSpeed']   : inferIntSpeed($neighIntName);
                $neighIntStatus    = isset($neighIntRecord['ifOperStatus'])   ? $neighIntRecord['ifOperStatus']  : "";
                $neighIntIp        = isset($neighIntRecord['ifIP'])           ? $neighIntRecord['ifIP']          : "";
                $neighIntHwAddr    = isset($neighIntRecord['ifHwAddr'])       ? $neighIntRecord['ifHwAddr']      : ""; 

		$hashArray = array(	$host['id'], 'lldp',$myIp, $myHostname,$snmpId,
					$myIntName, $myIntAlias,$myIntSpeed,$myIntStatus,$myIntIp,$myIntHwAddr,
					$neighHostId, $neighSnmpId,
					$neighIntName, $neighIntAlias,$neighIntSpeed,$neighIntStatus,$neighIntIp,$neighIntHwAddr,
					$neighHostname, $neighPlatform, $neighSoftware,$neighDuplex);

		$hostArray = array($myHostname,$neighHostname);
		sort($hostArray);
		$intArray = array($myIntName,$neighIntName);
		sort($intArray);
		$neighArray = array_merge($hostArray,$intArray);		// Sort the values to  make them even for each neighbor pair
		
		$recordHash = md5(serialize($hashArray));												// This should be unique to each CDP entry
		$neighHash = md5(serialize($neighArray));												// This should allow us to pair neighbors together
		if (db_execute_prepared("REPLACE  INTO `plugin_neighbor_xdp` 
				       (`host_id`, `type`,`host_ip`, `hostname`, `snmp_id`, 
					`interface_name`, `interface_alias`, `interface_speed`, `interface_status`, `interface_ip`, `interface_hwaddr`, 
					`neighbor_host_id`, `neighbor_hostname`, `neighbor_snmp_id`, 
					`neighbor_interface_name`, `neighbor_interface_alias`, `neighbor_interface_speed`, 
					`neighbor_interface_status`, `neighbor_interface_ip`, `neighbor_interface_hwaddr`, 
					`neighbor_platform`, `neighbor_software`, `neighbor_duplex`, 
					`neighbor_last_changed`, `neighbor_hash`, `record_hash`)
				    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE_SUB(NOW(),INTERVAL ? SECOND),?,?)",
				    array_merge($hashArray,array($neighUptime,$neighHash,$recordHash))
		)) { $neighCount++;}
	}
	return($neighCount);
}

/**
 * Discovers IP neighbors for a given host by correlating subnets.
 *
 * @param array $host Host array from database.
 * @return void
 */
function discoverIpNeighbors($host)
{
	$oidTable = get_neighbor_oid_table();
	debug("Processing IP Neighbors: " . $host['description']);
	$pollerDeadtimer = read_config_option('neighbor_global_deadtimer') ? (int) read_config_option('neighbor_global_deadtimer')  : 60;
	$hostId=$host['id'];
	$myHostname	= isset($host['description']) ? $host['description'] : "";
	$ipTable = neighbor_snmp_walk_and_flatten($host, $oidTable['ipMibWalk']);
	
	$ipParsed = array();
	$ifTranslate = array();
	foreach ($ipTable as $oid => $val) { 

		if 	(preg_match('/'.$oidTable['ipIpAddr'].'\.(\d+\.\d+\.\d+\.\d+)/',$oid,$matches)) {
				$index = $val;
				$ipAddress = isset($matches[1]) ? $matches[1] : '';
				$ipParsed[$ipAddress]['snmp_id'] = $index;
				$ipParsed[$ipAddress]['numeric'] = ip2long($ipAddress);		// Store for later comparison in the nested loop
				$ifTranslate[$index] = $ipAddress;							// We need to be able to translate snmp_index to IP in the VRF section
		}	
		elseif (preg_match('/'.$oidTable['ifNetmask'].'\.(\d+\.\d+\.\d+\.\d+)/',$oid,$matches)) {
			
				$ipAddress = isset($matches[1]) ? $matches[1] : '';
				$ipParsed[$ipAddress]['address'] = $ipAddress;
				$ipParsed[$ipAddress]['netmask'] = $val;
        }
		// The VRF tables all appear to be proprietory - here is the MPLS-VPN-MIB where the VRF name is ascii encoded
		elseif (preg_match('/'.$oidTable['ciscoVrf'].'\.(\d+)\.(.+)/',$oid,$matches)) {
				
				$vrfIndex =  isset($matches[1]) ? $matches[1] : '';
				$vrfOctetArray = isset($matches[2]) ? explode(".",$matches[2]) : '';
				$vrfNameLength = array_shift($vrfOctetArray);								// Number of chars is the first value
				$vrfIfIndex = array_pop($vrfOctetArray);									// Ifindex is the last value
				$ipAddress = isset($ifTranslate[$vrfIfIndex]) ? $ifTranslate[$vrfIfIndex] : "";
				if (!$ipAddress) { continue;}
				
				$vrfName = "";
				foreach ($vrfOctetArray as $chr) { $vrfName.=chr($chr); }
				$ipParsed[$ipAddress]['vrf'] = $vrfName;
        }
	}

	if (count($ipParsed) > 0) {
		debug(sprintf("Found   %7d - IP/Subnet Entries", count($ipParsed)));
	} else {
		debug("Found         0 - IP/Subnet Entries");
	}

	// Update the Database

	// First update the ipv4 cache table
	$vrfMapping = get_neighbor_vrf_maps();
	
	foreach ($ipParsed as $ipAddress => $record) { 

		// Create a unique hash of the neighbor based on the record
		$myHostId = $host['id'];
		$snmpId 	= isset($record['snmp_id']) ? $record['snmp_id'] : "";
		$ipSubnet 	= isset($record['netmask']) ? $record['netmask'] : "";
		$vrf 		= isset($record['vrf']) ? $record['vrf'] : "";
		
		if (!$vrf) {		// Lets see if we have a VRF Mapping rule
			$vrf = isset($vrfMapping[$myHostId][$ipAddress]['vrf']) && ($vrfMapping[$myHostId][$ipAddress]['vrf']) ? $vrfMapping[$myHostId][$ipAddress]['vrf'] : "";		
		}
		
		if ($ipSubnet == '255.255.255.255') { continue;} 					// No loopbacks
		
		db_execute_prepared("REPLACE  INTO `plugin_neighbor_ipv4_cache` 
						(`host_id`, `hostname`,`snmp_id`,`ip_address`,`ip_netmask`,`vrf`,`last_seen`)
						VALUES (?,?,?,?,?,?,NOW())",
						array($myHostId, $myHostname,$snmpId, $ipAddress, $ipSubnet, $vrf)
		);
	}
	
	// Now get all the ipv4_cache entries back to work out the neighbor relationships

	$ipCache = getIpv4Cache();
	$myIpCache = getIpv4Cache($hostId);				// We only want to calculate our view of things
	$ipNeighbors = array();
	$time_start = microtime(true);
	$confSubnetCorrelation = read_config_option('neighbor_global_subnet_correlation') ? (int) read_config_option('neighbor_global_subnet_correlation') : 30;
	$minCorrelation = ip2long(long2ip(0xffffffff << (32 - $confSubnetCorrelation)));		// This looks silly but gets around the 32/64 bit inconsistency...
	
	$neighsFound = 0;
	$totalSearched = 0;
	
	// Summarize IP cache
	$cacheCount = 0;
	foreach ($myIpCache as $vrf => $vrfData) {
		$cacheCount += count($vrfData);
	}
	debug(sprintf("Found   %7d - IP Cache Entries (Subnet: /%d)", $cacheCount, $confSubnetCorrelation));
	foreach ($myIpCache as $vrf => $vrfRec) {
		
		foreach ($vrfRec as $ipAddress1 => $record1) {
			
			foreach ($ipCache as $allVrf => $allVrfRec) { 
				// Worried about performance of this double iteration - maybe quicker to sort and find first match?	
				foreach ($allVrfRec as $ipAddress2 => $record2) {
						
						// cacti_tag_log("NEIGHBOR POLLER: vrf=$vrf, ipAddress1= $ipAddress1, ipAddress2= $ipAddress2");
						
						// Calculate numeric values on-the-fly since they're not stored
						$ip1_num = ip2long($record1['ip_address']);
						$ip2_num = ip2long($record2['ip_address']);
						$subnet1_num = ip2long($record1['ip_netmask']);
						
						if ($ip1_num == $ip2_num) { continue; }
						if (($record1['host_id'] == $record2['host_id']) && ($record1['snmp_id']) == $record2['snmp_id']) { continue; }		// Catches HSRP & VRRP sillyness
						if ($subnet1_num < $minCorrelation) { 	continue; }		// Catches addresses not meeting min correlation (e.g. /30)
						$totalSearched++;
						// cacti_tag_log("NEIGHBOR POLLER: vrf=$vrf, ipAddress1= $ipAddress1, ipAddress2= $ipAddress2 - Past correlation check. totalSearched: $totalSearched, neighsFound: $neighsFound");
						
						if (ipSubnetCheck($ipAddress1,$record1['ip_netmask'],$ipAddress2,$record2['ip_netmask'])) {
							cacti_log("Match found for ipAddress1= $ipAddress1, ipAddress2= $ipAddress2", true, "NEIGHBOR POLLER");
							// Order the arrays from lowest host_id
							$first = $record1['host_id'] < $record2['host_id'] ? $record1 : $record2;
							$second = $record1['host_id'] < $record2['host_id'] ? $record2 : $record1;
							
							$firstHost = $first['host_id'];
							$firstSnmpId = $first['snmp_id'];
							
							$secondHost = $second['host_id'];
							$secondSnmpId = $second['snmp_id'];
							
							$firstInterface = findCactiInterface($firstHost,'',$firstSnmpId);
							$secondInterface = findCactiInterface($secondHost,'',$secondSnmpId);
							
							$ipNeighbors["$firstHost:$secondHost"]["$firstSnmpId:$secondSnmpId"] = array( 'first' => $first, 'second' => $second);
							$neighsFound++;
						}
				}
			}
			
		}
	}
	$time_end = microtime(true);
	debug(sprintf("Found   %7d - IP Neighbors (%.2f sec, %d comparisons)",$neighsFound, $time_end-$time_start, $totalSearched));
	
	$neighCount = 0;
	$hostCache = array();
	$intCache = array();
	
	foreach ($ipNeighbors as $hostKey => $ipNeighbor) {
		list($myHostId,$neighHostId) = explode(":",$hostKey);
		
		$hostCache[$myHostId] = isset($hostCache[$myHostId]) ? $hostCache[$myHostId] : getCactiHostById($myHostId);
		$hostCache[$neighHostId] = isset($hostCache[$neighHostId]) ? $hostCache[$neighHostId] : getCactiHostById($neighHostId);
		
		foreach ($ipNeighbor as $snmpKey => $record) {
			list ($mySnmpId, $neighSnmpId) = explode(":",$snmpKey);
			
			$intCache[$myHostId][$mySnmpId] = isset($intCache[$myHostId][$mySnmpId]) ? $intCache[$myHostId][$mySnmpId] : findCactiInterface($myHostId,'',$mySnmpId);
			$intCache[$neighHostId][$neighSnmpId] = isset($intCache[$neighHostId][$neighSnmpId]) ? $intCache[$neighHostId][$neighSnmpId] : findCactiInterface($neighHostId,'',$neighSnmpId);
		
			if (!($intCache[$myHostId][$mySnmpId] && $intCache[$neighHostId][$neighSnmpId])) { continue; } // We must at least find the neighbor's interfaces
			
			$hashArray = array( 'ipv4',$record['first']['vrf'],$myHostId,$hostCache[$myHostId]['description'],$mySnmpId,
								$intCache[$myHostId][$mySnmpId]['ifDescr'], $intCache[$myHostId][$mySnmpId]['ifAlias'],
								$record['first']['ip_address'], $record['first']['ip_netmask'],$intCache[$myHostId][$mySnmpId]['ifHwAddr'],
								$neighHostId,$hostCache[$neighHostId]['description'],$neighSnmpId,
								$intCache[$neighHostId][$neighSnmpId]['ifDescr'], $intCache[$neighHostId][$neighSnmpId]['ifAlias'],
								$record['second']['ip_address'], $record['second']['ip_netmask'],$intCache[$neighHostId][$neighSnmpId]['ifHwAddr']);
			
			$neighArray = array($record['first']['vrf'],$hostKey,$record['first']['ip_address'],$record['second']['ip_address']);
			$hostArray = array($hostCache[$myHostId]['description'],$hostCache[$neighHostId]['description']);
			$intArray = array($intCache[$myHostId][$mySnmpId]['ifDescr'],$intCache[$neighHostId][$neighSnmpId]['ifDescr']);
			$intNeighArray = array_merge($hostArray,$intArray);
			$intNeighHash = md5(serialize($intNeighArray));											// This is to de-duplicate the xdp/ipv4 etc. etc. table entries
			$recordHash = md5(serialize($hashArray));												// This should be unique to each CDP entry
			$neighHash = md5(serialize($neighArray));												// This should allow us to pair neighbors together				
		
			db_execute_prepared("DELETE from plugin_neighbor_ipv4 where last_seen < DATE_SUB(NOW(), INTERVAL ? SECOND)",array($pollerDeadtimer));
			if (db_execute_prepared("REPLACE  INTO `plugin_neighbor_ipv4` 
				    (`type`,`vrf`,`host_id`, `hostname`, `snmp_id`, 
					`interface_name`, `interface_alias`, `interface_ip`, `interface_netmask`,`interface_hwaddr`, 
					`neighbor_host_id`, `neighbor_hostname`, `neighbor_snmp_id`, 
					`neighbor_interface_name`, `neighbor_interface_alias`, `neighbor_interface_ip`, `neighbor_interface_netmask`, `neighbor_interface_hwaddr`, 
					`neighbor_hash`, `ipv4_neighbor_hash`, `record_hash`, `last_seen`)
				    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
				    array_merge($hashArray,array($intNeighHash,$neighHash,$recordHash))
			)) { $neighCount++;}			
		}
		
	}
	$time_end = microtime(true);
}

// Check if two IP / Netmask combinations are in the same subnet
function ipSubnetCheck($ip1, $net1, $ip2, $net2)
{
	$ip_ip1 = ip2long($ip1);
	$ip_ip2 = ip2long($ip2);
	$ip_net1 = ip2long($net1);
	$ip_net2 = ip2long($net2);

	$ip_ip1_net = $ip_ip1 & $ip_net1;
	$ip_ip2_net = $ip_ip2 & $ip_net2;

	return ($ip_ip1_net == $ip_ip2_net);
}

// Fetch the contents of the ipv4 cache
function getIpv4Cache($hostId = null) {
	
	if ($hostId) {
		$query = db_fetch_assoc_prepared("SELECT * from plugin_neighbor_ipv4_cache where host_id=?",array($hostId));
		$result = db_fetch_hash($query,array('vrf','ip_address'));
		return ($result);
	}
	else { 
		$query = db_fetch_assoc("SELECT * from plugin_neighbor_ipv4_cache");
		$result = db_fetch_hash($query,array('vrf','ip_address'));
		return ($result);
	}
}

function inferIntSpeed($interface)
{
	if (preg_match('/^Fa/',$interface)) 	{ return(100); }
	if (preg_match('/^Gi/',$interface)) 	{ return(1000); }
	if (preg_match('/^Te/',$interface)) 	{ return(10000); }
	if (preg_match('/^Fo/',$interface)) 	{ return(40000); }
	if (preg_match('/^One/',$interface)) 	{ return(100000); }
}

/**
 * Find Cacti host record by hostname or host ID
 * 
 * @param string|null $hostName Hostname to search for
 * @param int|null $hostId Host ID to search for
 * @return array Hash of host records indexed by description
 */
function findCactiHost($hostName = null, $hostId = null)
{
	$strippedHost = preg_replace('/\..+/','',$hostName);
	$sorted = array();
	$hostRecords = "";
    if ($hostId && $hostRecords = db_fetch_assoc_prepared("SELECT * from host where id = ?",array($hostId)) ) {
		$sorted = db_fetch_hash($hostRecords,array('description'));
	}
	elseif ($hostRecords = db_fetch_assoc_prepared("SELECT * from host where LOWER(description) LIKE LOWER(?)",array($hostName))) { 
       	$sorted = db_fetch_hash($hostRecords,array('description'));
	}
	elseif ($hostRecords = db_fetch_assoc_prepared("SELECT * from host where LOWER(description) LIKE LOWER(?)",array($strippedHost))) {
        $sorted = db_fetch_hash($hostRecords,array('description'));
    }
    return($sorted);

}

function getCactiHostById($hostId)
{
	if ($hostRecords = db_fetch_assoc_prepared("SELECT * from host where id = ?",array($hostId))) {
		if (isset($hostRecords[0])) {
			return($hostRecords[0]);
		}
	}
	else {
		debug("Error: Couldn't locate host record for host_id = $hostId");
		return(null);
	}
}


# Find an interface in the host_snmp_cache given a host ID and interface

function findCactiInterface($hostId,$interface, $snmpIndex = null)
{
	if (!$snmpIndex) { 
		$snmpIndex = db_fetch_cell_prepared("SELECT snmp_index from host_snmp_cache where host_id = ? AND field_name = 'ifDescr' and field_value = ?",array($hostId,$interface));
	}

	$cacheRecords = db_fetch_assoc_prepared("SELECT * from host_snmp_cache where host_id = ? AND snmp_index=?",array($hostId,$snmpIndex));
	if ($cacheRecords) { 
	        $sorted = db_fetch_hash($cacheRecords,array('snmp_index','field_name'));
		return(array(
        		'snmp_index'	=> $snmpIndex,
        		'ifDescr'	=> isset($sorted[$snmpIndex]['ifDescr']['field_value']) 	? $sorted[$snmpIndex]['ifDescr']['field_value'] : "",
        		'ifAlias'	=> isset($sorted[$snmpIndex]['ifAlias']['field_value']) 	? $sorted[$snmpIndex]['ifAlias']['field_value'] : "",
        		'ifHighSpeed'	=> isset($sorted[$snmpIndex]['ifHighSpeed']['field_value']) 	? $sorted[$snmpIndex]['ifHighSpeed']['field_value'] : "",
        		'ifSpeed'	=> isset($sorted[$snmpIndex]['ifSpeed']['field_value']) 	? $sorted[$snmpIndex]['ifSpeed']['field_value'] : "",
        		'ifIP'		=> isset($sorted[$snmpIndex]['ifIP']['field_value']) 		? $sorted[$snmpIndex]['ifIP']['field_value'] : "",
        		'ifHwAddr' 	=> isset($sorted[$snmpIndex]['ifHwAddr']['field_value']) 	? $sorted[$snmpIndex]['ifHwAddr']['field_value'] : "",
        		'ifOperStatus'  => isset($sorted[$snmpIndex]['ifOperStatus']['field_value']) 	? $sorted[$snmpIndex]['ifOperStatus']['field_value'] : "",

		));
	}
	else {
		return(false);
	}

}

function autoDiscoverHosts()
{
	global $debug;

	$hosts = db_fetch_assoc("SELECT *
		FROM host
		WHERE snmp_version > 0
		AND disabled != 'on'
		AND status != 1");

	cacti_log("NEIGHBOR: INFO: Starting Auto-Discovery for '" . sizeof($hosts) . "' Hosts", true, "NEIGHBOR");
	debug("Starting AutoDiscovery for '" . sizeof($hosts) . "' Hosts");

	$hostsAdded = 0;
	$hostsUpdated = 0;

	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			debug("AutoDiscovery Check for Host '" . $host['description'] . '[' . $host['hostname'] . "']" );
			
			// Check if host already exists in plugin_neighbor_host
			$existing = db_fetch_cell_prepared("SELECT host_id FROM plugin_neighbor_host WHERE host_id = ?", array($host['id']));
			
			if ($existing) {
				debug("Host '" . $host['description'] . "' already in neighbor discovery table");
				// Update existing entry to ensure it's enabled
				db_execute_prepared("UPDATE plugin_neighbor_host 
					SET enabled = 1 
					WHERE host_id = ?", 
					array($host['id']));
				$hostsUpdated++;
			} else {
				// Add new host with default settings
				db_execute_prepared("INSERT INTO plugin_neighbor_host 
					(host_id, enabled, discover_cdp, discover_lldp, discover_ip) 
					VALUES (?, 1, 1, 1, 1)",
					array($host['id']));
				debug("Host '" . $host['description'] . "' added to neighbor discovery table");
				$hostsAdded++;
			}
		}
	}

	cacti_log("NEIGHBOR: INFO: Auto-Discovery Complete - Added: $hostsAdded, Updated: $hostsUpdated", true, "NEIGHBOR");
	debug("AutoDiscovery Complete - Added: $hostsAdded, Updated: $hostsUpdated");
	db_execute_prepared("REPLACE INTO settings (name,value) VALUES ('plugin_neighbor_autodiscovery_lastrun', ?)", array(time()));

	return true;
}

/**
 * Main polling process manager.
 * Launches child processes for each host.
 *
 * @return void
 */
function processHosts()
{
	global $start, $seed, $verbose, $debug, $dieNow, $config;
	global $database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port;

	debug(str_repeat('-', 85));
	debug('NEIGHBOR POLLER - Processing Hosts');
	debug(str_repeat('-', 85));
	
	/* Purge collectors that run longer than 10 minutes */
	db_execute('DELETE FROM plugin_neighbor_processes WHERE (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(started)) > ' . NEIGHBOR_PROCESS_TIMEOUT);
	/* Do not process collectors are still running */
	$processes = db_fetch_cell('SELECT count(*) as num_proc FROM plugin_neighbor_processes');
	if ($processes) {
		cacti_log("NEIGHBOR: WARNING: Another neighbor process is still running!  Exiting...", true, "NEIGHBOR");
		exit(0);
	}
	
	/* The hosts to scan will
	 *  1) Be configured in plugin_neighbor_host table
	 *  2) Not be disabled in host table
	 *  3) Be up and operational
	 */
	$hosts = db_fetch_assoc("SELECT pnh.host_id, h.description, h.hostname 
				FROM plugin_neighbor_host AS pnh
				INNER JOIN host AS h ON pnh.host_id = h.id
				WHERE h.disabled != 'on'
				AND h.status != 1
				AND pnh.enabled = 1");

	/* Remove entries for disabled or removed hosts */
	db_execute("DELETE FROM plugin_neighbor_xdp WHERE host_id IN (SELECT id FROM host WHERE disabled='on')");

	$concurrentProcesses = read_config_option('neighbor_global_poller_processes');
	$concurrentProcesses = ($concurrentProcesses == '' ? 5 : $concurrentProcesses);

	debug(sprintf("Found   %7d - Host(s) to Process", count($hosts)));
	debug(str_repeat('-', 85));
	
	$running_pids = array();
	
	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			if ($dieNow) break;

			// PCNTL Method (Linux/Unix High Performance)
			if (function_exists('pcntl_fork')) {
				while (1) {
					if ($dieNow) break 2;

					// Clean up finished children
					foreach ($running_pids as $pid => $data) {
						$res = pcntl_waitpid($pid, $status, WNOHANG);
						if ($res == -1 || $res > 0) {
							unset($running_pids[$pid]);
						}
					}

					// If we have slots available, fork a new one
					if (count($running_pids) < $concurrentProcesses) {
						$pid = pcntl_fork();
						if ($pid == -1) {
							// Fork failed
							die("FATAL: Could not fork process!");
						} elseif ($pid) {
							// Parent
							$running_pids[$pid] = true;
							usleep(1000); // Slight pause to let child start
						} else {
							// Child
							// Reset database connection for child (IMPORTANT for MySQL)
							db_close();
							db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port);
							
							$key = rand();
							// Only use DB tracking for visual status, not process control
							db_execute_prepared("INSERT INTO plugin_neighbor_processes (pid, taskid, started) VALUES (?,?, NOW())", array($key, $seed));
							
							// Run the actual work
							discoverHost($host['host_id']);
							
							// Remove lock
							db_execute_prepared('DELETE FROM plugin_neighbor_processes WHERE pid=?', array($key));
							
							exit(0);
						}
						break; // Break wait loop to process next host
					}
					
					// Table full, wait a bit
					usleep(50000);
				}
			} 
			// Fallback Method (Windows / Non-PCNTL)
			else {
				// Old logic: use DB table to count processes
				while ($dieNow == 0) {
					$processes = db_fetch_cell('SELECT COUNT(*) as num_proc FROM plugin_neighbor_processes');
					if ($processes < $concurrentProcesses) {
						$key = rand();
						db_execute_prepared("INSERT INTO plugin_neighbor_processes (pid, taskid, started) VALUES (?,?, NOW())",array($key, $seed));
						debug("INFO: Launching Background Shell for: '" . $host['description'] . "'");
						processHost($host['host_id'], $seed, $key);
						usleep(10000);
						break;
					} else {
						sleep(1);
					}
				}
			}
		}
	}
	
	debug(str_repeat('-', 85));
	
	// Wait for stragglers
	if (function_exists('pcntl_fork')) {
		while (count($running_pids) > 0) {
			foreach ($running_pids as $pid => $data) {
				$res = pcntl_waitpid($pid, $status, WNOHANG);
				if ($res == -1 || $res > 0) {
					unset($running_pids[$pid]);
				}
			}
			usleep(100000);
			if ($dieNow) break;
		}
	} else {
		/* wait for all processes to end or max run time */
		while ($dieNow == 0) {
			$processesLeft 	= db_fetch_cell_prepared("SELECT count(*) as num_proc FROM plugin_neighbor_processes WHERE taskid=?",array($seed));
			if ($processesLeft == 0) {
				break;
			} else {
				sleep(2);
			}
		}
	}
	
	debug('All Collector Processes Complete');
	debug(str_repeat('-', 85));
	
	// Update the last runtimes
	
	$lastRun     		= read_config_option('plugin_neighbor_last_run');
	$pollerFrequency     	= read_config_option('neighbor_autodiscovery_freq');

	/* set the collector statistics */
	if (runCollector($start, $lastRun, $pollerFrequency)) {
		db_execute_prepared("REPLACE INTO settings (name,value) VALUES (?, ?)", array('plugin_neighbor_last_run', $start));
	}
	
	list($micro, $seconds) = explode(' ', microtime());
	$end	 = $seconds + $micro;
	$cactiStats = sprintf('time:%01.4f ' . 'processes:%s ' . 'hosts:%s', round($end - $start, 2), $concurrentProcesses, sizeof($hosts));
	/* log to the logfile */
	cacti_log('NEIGHBOR STATS: ' . $cactiStats, TRUE, 'SYSTEM');
	debug(str_repeat('-', 85));
	debug('NEIGHBOR POLLER COMPLETE: ' . $cactiStats);
	debug(str_repeat('-', 85));
	/* launch the graph creation process */

}

function processHost($hostId, $seed, $key) {
	global $debug, $config, $forceRun, $dieNow, $start;
	
	if ($dieNow) { return(false); }

	exec_background(read_config_option('path_php_binary'), ' '
			. $config['base_path'] . '/plugins/neighbor/poller_neighbor.php' 
			. ' --host-id=' . $hostId . ' --start=' . $start . ' --seed=' . $seed . ' --key=' . $key . ($forceRun ? ' --force' : '') . ($debug ? ' --debug' : ''));
}

/**
 * Display plugin version information
 * 
 * @return void
 */
function displayVersion()
{
	global $config;
	if (!function_exists('plugin_neighbor_version')) {
		include_once($config['base_path'] . '/plugins/neighbor/setup.php');
	}
	
	$info = plugin_neighbor_version();
	echo "Neighbor Plugin - Poller Process, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/**
 * Display command-line help information
 * 
 * @return void
 */
function displayHelp()
{
	echo "\nNeighbor discovery plugin for Cacti.\n\n";
	echo "Usage: \n";
	echo "Master process      : poller_neighbor.php [-M] [-f] [-d]\n";
	echo "Auto-discover hosts : poller_neighbor.php [-A|--auto-discover-all] [-d]\n";
	echo "Child process       : poller_neighbor.php --host-id=N [--seed=N] [-f] [-d]\n\n";
	echo "Options:\n";
	echo "  -M                    Run main poller for configured hosts\n";
	echo "  -A, --auto-discover-all  Add all eligible hosts to neighbor discovery\n";
	echo "  --host-id=N           Poll specific host by ID\n";
	echo "  -f, --force           Force polling regardless of schedule\n";
	echo "  -d, --debug           Enable debug output\n";
	echo "  --verbose             Enable verbose output\n";
	echo "  -v, -V, --version     Display version information\n";
	echo "  -h, -H, --help        Display this help message\n\n";
}

/**
 * Clean up process table and exit gracefully
 * 
 * @return void
 */
function exitCleanly()
{
	print "Cleaning processes table...";
	if (db_execute("DELETE FROM plugin_neighbor_processes")) {
		print "[OK]\n";	
	}
	else { 
		print "[FAIL]\n";
	}
	print "Done.\n";
}

/**
 * Handle system signals (SIGINT, SIGTERM) for graceful shutdown
 * 
 * @param int $signo Signal number received
 * @return void
 */
function sigHandler($signo)
{
	global $dieNow;
	print "Handling signal!\n";
     	switch ($signo) {
          case SIGINT:
	  case SIGTERM:
             $dieNow++;							// The poller processes will read this an gracefully exit
	     if ($dieNow == 1) { 
	     	print "Stopping polling processes...\nPlease wait for them to finish.\n";
		sleep(3);
		exitCleanly();
	     }
	     else {
		print "Closing forcefully - please hold...\n";
		exitCleanly();
		exit;
	     }
             break;
         default:							// handle all other signals
     }
}

