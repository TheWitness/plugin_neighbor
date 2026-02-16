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


// Modified from thold_functions.php

include_once($config['base_path'] . '/plugins/neighbor/lib/api_neighbor.php');

/**
 * Include CSS and JavaScript dependencies for neighbor tab interface
 * 
 * Loads DevExpress UI components, CLDR for internationalization, and neighbor-specific
 * JavaScript. Creates a div container for the tabbed interface.
 * 
 * @return void Outputs HTML and script tags
 */
function neighbor_tabs() {
	global $config;
	printf("<link href='%s' rel='stylesheet'>", "js/devexpress/css/dx.common.css");
	printf("<link href='%s' rel='stylesheet'>", "js/devexpress/css/dx.light.css");
	printf("<script type='text/javascript' src='%s'></script>",'js/devexpress/js/cldr.min.js');
	printf("<script type='text/javascript' src='%s'></script>",'js/devexpress/js/dx.all.js');
	printf("<script type='text/javascript' src='%s'></script>",'js/neighbor.js');
	print "<div id='neighbor_tabs'></div>";
}

/**
 * Retrieve CDP/LLDP neighbor discovery data with filtering and pagination
 * 
 * Queries the plugin_neighbor_xdp table for neighbor relationships discovered via
 * CDP and LLDP protocols. Supports filtering by type, host, and search terms.
 * 
 * @param int &$total_rows Reference to store total matching rows for pagination
 * @param int $rowStart Starting row number (0-based, default 1)
 * @param int $rowEnd Ending row number (default 25)
 * @param string $xdpType Filter by protocol: 'cdp', 'lldp', or '' for all
 * @param string $hostId Filter by host ID (default '')
 * @param string $filterVal Search filter to match multiple fields (default '')
 * @param string $orderField Column to sort by (default 'hostname')
 * @param string $orderDir Sort direction 'asc' or 'desc' (default 'asc')
 * @param string $cactiOnly If 'on', only show neighbors where both sides are in Cacti (default 'on')
 * @param string $output Return format: 'array' or 'json' (default 'array')
 * 
 * @return array|string Array of neighbor records or JSON string
 */
function getXdpNeighbors(&$total_rows = 0, $rowStart = 1, $rowEnd = 25, $xdpType = '', $hostId = '', $filterVal = '', $orderField = 'hostname', $orderDir = 'asc', $cactiOnly = 'on', $output = 'array') {
 
    $sqlWhere 	= '';
    $sqlOrder 	= '';
    $sqlLimit 	= sprintf("limit %d,%d",$rowStart,$rowEnd);
    $result 	= '';
    
    $conditions = array();
    $params = array();

    if ($xdpType) 	{ array_push($conditions,"(`type` = ?)");	 array_push($params,(strtolower($xdpType))); }
    if ($hostId>0)	  { array_push($conditions,"(`host_id` = ? OR `neighbor_host_id` = ?)");  array_push($params, $hostId,$hostId); }
    if ($cactiOnly == 'on') { array_push($conditions,"(`host_id` > 0 AND `neighbor_host_id` > 0)"); }
    if ($orderField && ($orderDir != ''))   { $sqlOrder = "order by $orderField $orderDir"; }
    if ($filterVal != '')	{
				$searchArray  = array('hostname','neighbor_hostname','interface_name','interface_alias','neighbor_interface_name','neighbor_interface_alias','neighbor_platform', 'neighbor_software');
				$searchFields = array();
				$searchParams = array();
				foreach ($searchArray as $f) { array_push($searchFields,"`$f` LIKE ?"); array_push($searchParams,"%$filterVal%");}
				$searchMerged = "(".implode(" OR ", $searchFields).")";
				array_push($conditions,$searchMerged);
				$params = array_merge($params,$searchParams);
		}
		
    $sqlWhere = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $result = db_fetch_assoc_prepared("select * from plugin_neighbor_xdp xdp $sqlWhere $sqlOrder $sqlLimit", $params);
    $total_rows = db_fetch_cell_prepared("select count(*) as total_rows from plugin_neighbor_xdp xdp $sqlWhere",$params);
    //print "Set total_rows = $total_rows<br>";
    if ($output == 'array') 	{ return($result);}
    elseif ($output == 'json') 	{ return(json_encode($result));}
}

/**
 * Get summary statistics for CDP/LLDP neighbor discoveries
 * 
 * Returns aggregated counts of hosts, interfaces with neighbors, and last polling time
 * from the plugin_neighbor_xdp table.
 * 
 * @param int &$total_rows Reference to store row count (set to 1 if any data exists)
 * 
 * @return array Associative array with keys: hosts, interfaces, last_polled
 */
function getXdpNeighborStats(&$total_rows = 0) {
    
    $numHosts = db_fetch_cell("select count(distinct host_id) from plugin_neighbor_xdp");
    $numInterfaces = db_fetch_cell("select count(distinct concat(host_id,':',snmp_id)) from plugin_neighbor_xdp;");
    $lastPolled = db_fetch_cell("select last_seen from plugin_neighbor_xdp order by last_seen desc limit 1");
    
    if ($numHosts || $numInterfaces) { $total_rows++;}
    
    return(array('hosts'=>$numHosts, 'interfaces'=> $numInterfaces, 'last_polled' => $lastPolled));
}

/* Helper and Override functions */

/**
 * Convert flat database result array into nested associative array structure
 * 
 * Emulates Perl DBI's fetchall_hashref functionality. Organizes flat query results
 * into a multi-dimensional hash structure based on specified key columns.
 * 
 * @param array &$result Reference to flat array of database rows
 * @param array $index_keys Array of column names to use as hierarchical keys
 * 
 * @return array Nested associative array indexed by key columns
 */
function db_fetch_hash(& $result,$index_keys) {
  $assoc = array();             // The array we're going to be returning

  if (!is_array($result) || !is_array($index_keys) || !count($index_keys)) {
	  return($assoc);
  }

  foreach ($result as $row) {
	  if (!is_array($row)) {
		    continue;
	  }

        $pointer = & $assoc;            // Start the pointer off at the base of the array
        for ($i=0; $i<count($index_keys); $i++) {
                $key_name = $index_keys[$i];
                if (!array_key_exists($key_name,$row)) {
                        error_log("Error: Key [$key_name] is not present in the results output\n");
                        return(false);
                }

                $key_val= isset($row[$key_name]) ? $row[$key_name]  : "";
                if (!isset($pointer[$key_val])) {

					$pointer[$key_val] = array();                // Start a new node
                        $pointer = & $pointer[$key_val];                // Move the pointer on to the new node
                }
                else {
					if (!is_array($pointer[$key_val])) {
						$pointer[$key_val] = array();
					}
                        $pointer = & $pointer[$key_val];            // Already exists, move the pointer on to the new node
                }
        } // for $i
        foreach ($row as $key => $val) { $pointer[$key] = $val; }
  } // $row
  return($assoc);
}

/**
 * Build SNMP authentication string for binary snmpwalk commands
 *
 * @param string $community SNMP community string
 * @param int $version SNMP version (1, 2, or 3)
 * @param string $username SNMPv3 username
 * @param string $password SNMPv3 auth password
 * @param string $auth_proto SNMPv3 auth protocol
 * @param string $priv_pass SNMPv3 privacy passphrase
 * @param string $priv_proto SNMPv3 privacy protocol  
 * @param string $context SNMPv3 context
 * @param string $engineid SNMPv3 engine ID
 * @return array Array with 'auth' => auth string, 'version' => version string
 */
function neighbor_build_snmp_auth($community, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $context, $engineid) {
	$snmp_auth = '';
	
	if ($version == '1') {
		$snmp_auth = '-c ' . snmp_escape_string($community);
	} elseif ($version == '2') {
		$snmp_auth = '-c ' . snmp_escape_string($community);
		$version = '2c';
	} elseif ($version == '3') {
		$sec_level = ($priv_proto == '[None]' || $priv_pass == '') ? 'authNoPriv' : 'authPriv';
		
		$priv_string = '';
		if ($priv_pass != '') {
			$priv_string = '-X ' . snmp_escape_string($priv_pass) . ' -x ' . snmp_escape_string($priv_proto);
		}
		
		$context_string = ($context != '') ? '-n ' . snmp_escape_string($context) : '';
		$engineid_string = ($engineid != '') ? '-e ' . snmp_escape_string($engineid) : '';
		
		$snmp_auth = trim('-u ' . snmp_escape_string($username) .
			' -l ' . snmp_escape_string($sec_level) .
			' -a ' . snmp_escape_string($auth_proto) .
			' -A ' . snmp_escape_string($password) .
			' ' . $priv_string .
			' ' . $context_string .
			' ' . $engineid_string);
	}
	
	return array('auth' => $snmp_auth, 'version' => $version);
}

/**
 * Process and filter SNMP results removing banned strings
 *
 * @param array $temp_array Raw SNMP results
 * @param bool $snmp_oid_included Whether OID is included in results
 * @param int $value_output_format Output format constant
 * @return array Processed SNMP array with 'oid' and 'value' keys
 */
function neighbor_process_snmp_results($temp_array, $snmp_oid_included, $value_output_format) {
	global $banned_snmp_strings;
	
	$snmp_array = array();
	
	if (!is_array($temp_array) || !sizeof($temp_array)) {
		return $snmp_array;
	}
	
	// Remove banned strings
	foreach($temp_array as $key => $value) {
		foreach($banned_snmp_strings as $item) {
			if (strstr($value, $item) != '') {
				unset($temp_array[$key]);
				continue 2;
			}
		}
	}
	
	// Process results for PHP method
	if ($snmp_oid_included === false) {
		$o = 0;
		for (reset($temp_array); $i = key($temp_array); next($temp_array)) {
			if ($temp_array[$i] != 'NULL') {
				$snmp_array[$o]['oid'] = preg_replace('/^\./', '', $i);
				$snmp_array[$o]['value'] = format_snmp_string($temp_array[$i], $snmp_oid_included, $value_output_format);
			}
			$o++;
		}
	} else {
		// Process results for binary method
		$i = 0;
		foreach($temp_array as $index => $value) {
			if (preg_match('/(.*) =.*/', $value)) {
				$snmp_array[$i]['oid']   = trim(preg_replace('/(.*) =.*/', "\\1", $value));
				$snmp_array[$i]['value'] = format_snmp_string($value, true, $value_output_format);
				$i++;
			} else {
				if ($i > 0) {
					$snmp_array[$i-1]['value'] .= $value;
				}
			}
		}
	}
	
	return $snmp_array;
}

/**
 * Check if SNMP OID is a known optional MIB object that may be legitimately absent
 * 
 * Certain SNMP OID queries are expected to fail on devices that don't support
 * specific features or MIB modules. This function identifies OIDs where a "No Such Object"
 * response should not generate warning messages, as the absence is normal device behavior.
 * 
 * Known Optional MIB Objects:
 * - ENTITY-MIB: Physical inventory (entPhysicalDescr) - not supported on all platforms
 * - CISCO-VTP-MIB: VLAN Trunk Protocol - only on switches with VTP enabled
 * - CISCO-CDP-MIB: Cisco Discovery Protocol - only when CDP is active
 * 
 * @param string $oid The SNMP OID to check (must include leading dot)
 * @return bool True if warnings should be suppressed for this OID
 */
function query_snmp_neighbor($oid) {
	$known_optional_oids = array(
		'.1.3.6.1.2.1.47.1.1.1.1.2',        // ENTITY-MIB::entPhysicalDescr - Physical entity descriptions
		'.1.3.6.1.4.1.9.9.68.1.2.2.1.2',    // CISCO-VTP-MIB::vtpVlanState - VLAN Trunk Protocol state
		'.1.3.6.1.4.1.9.9.46.1.6.1.1.5',    // CISCO-VTP-MIB::vtpVlanIfIndex - VTP VLAN interface index
		'.1.3.6.1.4.1.9.9.46.1.6.1.1.14',   // CISCO-VTP-MIB::vtpVlanName - VTP VLAN name
		'.1.3.6.1.4.1.9.9.23.1.2.1.1.6'     // CISCO-CDP-MIB::cdpCacheDeviceId - CDP neighbor device ID
	);
	
	return in_array($oid, $known_optional_oids, true);
}

/**
 * Perform SNMP walk using PHP SNMP extension
 *
 * @param string $hostname Target hostname/IP
 * @param string $community SNMP community
 * @param string $oid OID to walk
 * @param int $version SNMP version
 * @param string $username SNMPv3 username
 * @param string $password SNMPv3 password
 * @param string $auth_proto SNMPv3 auth protocol
 * @param string $priv_pass SNMPv3 priv password
 * @param string $priv_proto SNMPv3 priv protocol
 * @param int $port SNMP port
 * @param int $timeout Timeout in milliseconds
 * @param int $retries Number of retries
 * @param int $value_output_format Output format
 * @return array|false Array of results or false on error
 */
function neighbor_snmp_walk_php($hostname, $community, $oid, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $port, $timeout, $retries, $value_output_format) {
	global $snmp_error;
	
	cacti_oid_numeric_format();
	snmp_set_quick_print(0);
	
	$temp_array = false;
	
	try {
		if ($version == '1') {
			$temp_array = @snmprealwalk($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} elseif ($version == 2) {
			$temp_array = @snmp2_real_walk($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
		} else {
			$sec_level = ($priv_proto == '[None]' || $priv_pass == '') ? 'authNoPriv' : 'authPriv';
			$temp_array = @snmp3_real_walk($hostname . ':' . $port, $username, $sec_level, $auth_proto, $password, $priv_proto, $priv_pass, $oid, ($timeout * 1000), $retries);
		}
	} catch (Exception $e) {
		cacti_log("WARNING: SNMP PHP Exception: " . $e->getMessage() . ", Device:'$hostname', OID:'$oid'", false);
		return false;
	}
	
	if ($temp_array === false) {
		if (!query_snmp_neighbor($oid)) {
			if (!preg_match('/No Such Object available on this agent at this OID/i', $snmp_error) && 
			    !preg_match('/currently exists at this OID/i', $snmp_error)) {
				cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
			}
		}
	}
	
	return $temp_array;
}

/**
 * Perform SNMP walk using binary snmpwalk/snmpbulkwalk commands
 *
 * @param string $hostname Target hostname/IP
 * @param string $oid OID to walk
 * @param array $auth_info Auth info from neighbor_build_snmp_auth()
 * @param int $port SNMP port
 * @param int $timeout Timeout in seconds
 * @param int $retries Number of retries
 * @param int $max_oids Max OIDs per request
 * @param int $value_output_format Output format
 * @return array|false Array of results or false on error
 */
function neighbor_snmp_walk_binary($hostname, $oid, $auth_info, $port, $timeout, $retries, $max_oids, $value_output_format) {
	$snmp_auth = $auth_info['auth'];
	$version = $auth_info['version'];
	$path_snmpbulkwalk = read_config_option('path_snmpbulkwalk');
	
	$oidCheck = (read_config_option('oid_increasing_check_disable') == 'on') ? '-Cc' : '';
	
	$temp_array = array();
	
	if (file_exists($path_snmpbulkwalk) && ($version > 1) && ($max_oids > 1)) {
		$temp_array = exec_into_array(cacti_escapeshellcmd($path_snmpbulkwalk) .
			' -O QnU' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ' : ' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout .
			' -r ' . $retries .
			' -Cr' . $max_oids .
			' ' . $oidCheck . ' ' .
			cacti_escapeshellarg($hostname) . ':' . $port . ' ' .
			cacti_escapeshellarg($oid));
	} else {
		$temp_array = exec_into_array(cacti_escapeshellcmd(read_config_option('path_snmpwalk')) .
			' -O QnU ' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ' : ' ') . $snmp_auth .
			' -v ' . $version .
			' -t ' . $timeout .
			' -r ' . $retries .
			' ' . $oidCheck . ' ' .
			cacti_escapeshellarg($hostname) . ':' . $port . ' ' .
			cacti_escapeshellarg($oid));
	}
	
	if (substr_count(implode(' ', $temp_array), 'Timeout:')) {
		cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false);
	}
	
	return $temp_array;
}

/**
 * Perform SNMP walk operation using either PHP extension or binary commands.
 * 
 * This is a wrapper function that performs SNMP walks using either PHP's native
 * SNMP functions or binary SNMP commands (snmpwalk/snmpbulkwalk). It handles
 * version-specific authentication and processes results into a standardized format.
 * 
 * @param string $hostname      The hostname or IP address of the device
 * @param string $community     The SNMP community string (for v1/v2)
 * @param string $oid           The OID to walk
 * @param string $version       The SNMP version ('1', '2', or '3')
 * @param string $username      The SNMPv3 username
 * @param string $password      The SNMPv3 authentication password
 * @param string $auth_proto    The SNMPv3 authentication protocol (MD5, SHA, etc.)
 * @param string $priv_pass     The SNMPv3 privacy password
 * @param string $priv_proto    The SNMPv3 privacy protocol (DES, AES, etc.)
 * @param string $context       The SNMPv3 context name
 * @param int    $port          The SNMP port number (default 161)
 * @param int    $timeout       The timeout in milliseconds (default 500)
 * @param int    $retries       The number of retries (default 0)
 * @param int    $max_oids      The maximum number of OIDs per request for bulk operations (default 10)
 * @param int    $environ       The environment constant (default SNMP_POLLER)
 * @param string $engineid      The SNMPv3 engine ID (default '')
 * @param int    $value_output_format The output format for values (default SNMP_STRING_OUTPUT_GUESS)
 * 
 * @return array An array of associative arrays with 'oid' and 'value' keys, or empty array on failure
 */
function plugin_cacti_snmp_walk($hostname, $community, $oid, $version, $username, $password,
        $auth_proto, $priv_pass, $priv_proto, $context,
        $port = 161, $timeout = 500, $retries = 0, $max_oids = 10, $environ = SNMP_POLLER,
        $engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

        global $config, $banned_snmp_strings, $snmp_error;

        $snmp_oid_included = true;
        $snmp_array        = array();
        $temp_array        = array();

        if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout, $retries, $max_oids)) {
                return array();
        }

        $path_snmpbulkwalk = read_config_option('path_snmpbulkwalk');

        if (snmp_get_method('walk', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
                $snmp_oid_included = function_exists('snmprealwalk') ? false : true;
                
                $temp_array = neighbor_snmp_walk_php($hostname, $community, $oid, $version, $username, 
                        $password, $auth_proto, $priv_pass, $priv_proto, $port, $timeout, $retries, $value_output_format);
                        
                if ($temp_array !== false && sizeof($temp_array)) {
                        $snmp_array = neighbor_process_snmp_results($temp_array, $snmp_oid_included, $value_output_format);
                }
        } else {
                $timeout = ceil($timeout / 1000);
                $auth_info = neighbor_build_snmp_auth($community, $version, $username, $password, $auth_proto, $priv_pass, $priv_proto, $context, $engineid);
                
                $temp_array = neighbor_snmp_walk_binary($hostname, $oid, $auth_info, $port, $timeout, $retries, $max_oids, $value_output_format);
                
                if (is_array($temp_array) && sizeof($temp_array)) {
                        $snmp_array = neighbor_process_snmp_results($temp_array, $snmp_oid_included, $value_output_format);
                }
        }

        return $snmp_array;
}

/**
 * Retrieve neighbor automation rules with filtering and pagination
 * 
 * Queries the plugin_neighbor_rules table for automation rules that control
 * automatic graph creation and device organization based on neighbor relationships.
 * 
 * @param int &$total_rows Reference to store total matching rows
 * @param int $rowStart Starting row number (0-based, default 1)
 * @param int $rowEnd Maximum rows per page (default 25)
 * @param string $filterVal Search filter for rule name (default '')
 * @param string $orderField Column to sort by (default 'hostname')
 * @param string $orderDir Sort direction 'asc' or 'desc' (default 'asc')
 * @param string $output Return format: 'array' or 'json' (default 'array')
 * 
 * @return array|string Array of rule records or JSON string
 */
function get_neighbor_rules(&$total_rows = 0, $rowStart = 1, $rowEnd = 25, $filterVal = '', $orderField = 'hostname', $orderDir = 'asc', $output = 'array') {
	
		$sqlWhere 	= '';
    $sqlOrder 	= '';
    $sqlLimit 	= sprintf("limit %d,%d",$rowStart,$rowEnd);
    $result 	= '';
    
    $conditions = array();
    $params = array();

	if ($orderField && ($orderDir != ''))   { $sqlOrder = "order by $orderField $orderDir"; }
	if ($filterVal != '')										{ array_push($conditions,"`name` like ?"); array_push($params, $filterVal); }
		
    $sqlWhere = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $result = db_fetch_assoc_prepared("select * from plugin_neighbor_rules rules $sqlWhere $sqlOrder $sqlLimit", $params);
    $total_rows = db_fetch_cell_prepared("select count(*) as total_rows from plugin_neighbor_rules rules $sqlWhere",$params);
    if ($output == 'array') 	{ return($result);}
    elseif ($output == 'json') 	{ return(json_encode($result));}
	
}

/**
 * Display filter form for neighbor rules listing page
 * 
 * Renders an HTML filter form with search input, status dropdown, and rows selector
 * for the neighbor rules management page. Includes JavaScript for AJAX filtering.
 * 
 * @return void Outputs HTML form and JavaScript
 */
function get_neighbor_rules_filter() {
	global $automation_graph_rules_actions, $config, $item_rows;
	
	html_start_box(__('Neighbor Rules'), '100%', '', '3', 'center', 'neighbor_rules.php?action=edit');

	?>
	<tr class='even'>
		<td>
			<form id='form_automation' action='neighbor_rules.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Status');?>
						</td>
						<td>
							<select id='status'>
								<option value='-1' <?php print (get_request_var('status') == '-1' ? ' selected':'');?>><?php print __('Any');?></option>
								<option value='-2' <?php print (get_request_var('status') == '-2' ? ' selected':'');?>><?php print __('Enabled');?></option>
								<option value='-3' <?php print (get_request_var('status') == '-3' ? ' selected':'');?>><?php print __('Disabled');?></option>
							</select>
						</td>
						<td>
							<?php print __('Rows');?>
						</td>
						<td>
							<select id='rows'>
								<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
								<?php
								if (sizeof($item_rows) > 0) {
									foreach ($item_rows as $key => $value) {
										print "<option value='" . $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . $value . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input type='submit' id='refresh' name='go' value='<?php print __esc('Go');?>'>
								<input type='button' id='clear' value='<?php print __esc('Clear');?>'></td>
							</span>
					</tr>
				</table>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL = 'neighbor_rules.php' +
				'?status='        + $('#status').val()+
				'&filter='        + $('#filter').val()+
				'&rows='          + $('#rows').val()+
				'&header=false';
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'neighbor_rules.php?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#refresh, #rules, #rows, #status, #snmp_query_id').change(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_automation').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		</td>
	</tr>
	<?php

	html_end_box();
	
}

/**
 * Display filter form for neighbor discovery listings
 * 
 * Renders an HTML table row with filter controls for the neighbor discovery display,
 * including search input, protocol type selector, host filter, and pagination controls.
 * The action parameter determines which optional filters are displayed.
 * 
 * @param string $action Page context: 'xdp' shows protocol filter, '' for generic filter
 * 
 * @return void Outputs HTML form elements and JavaScript
 */
function neighbor_filter($action='') {
	global $item_rows, $config;
	$rows = get_request_var('rows');
	?>
	<tr class='even'>
		<td>
		<form id='neighbor' action='neighbor.php'>
		<table class='filterTable'>
			<tr>
				<td> <?php print __('Search', 'neighbor');?> </td>
				<td> <input type='text' id='filter' size='25' value='<?php print html_escape(get_request_var('filter'));?>' onChange='applyFilter()'></td>
				<?php
				if ($action == 'xdp') {
				?>
						<td> <?php print __('Type', 'neighbor');?> </td>
						<td>
							<select id='xdp_type' onChange='applyFilter()'>
								<option value = '' <?php if (get_request_var('xdp_type') == '') 	{?> selected<?php }?>><?php print __('All', 'neighbor');?></option>
								<option value = 'cdp' <?php if (get_request_var('xdp_type') == 'cdp') 	{?> selected<?php }?>><?php print __('CDP', 'neighbor');?></option>
								<option value = 'lldp'<?php if (get_request_var('xdp_type') == 'lldp') 	{?> selected<?php }?>><?php print __('LLDP', 'neighbor');?></option>
							</select>
						</td>
				<?php
				}
				?>
				<?php print html_host_filter(get_request_var('host_id'));?>
				<td> Rows </td>
				<td>
					<select id='rows' onChange='applyFilter()'>
						<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default', 'thold');?></option>
						<?php
						if (sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
						}
						?>
					</select>
				</td>
				<td> Cacti Only</td>
				<td> <input type='checkbox' name='cacti_only' id='cacti_only' onChange='applyFilter()' <?php if (get_request_var('cacti_only') == 'on') { echo "checked"; }?>></input></td>
				<td>
					<input type='submit' value='<?php print __esc('Go', 'neighbor');?>'>
				</td>
				<td>
					<input id='clear' name='clear' type='button' value='<?php print __esc('Clear', 'neighbor');?>' onClick='clearFilter()'>
				</td>
		</table>
		<input type='hidden' id='page' value='<?php print get_request_var('page');?>'>
		<input type='hidden' id='rows' value='<?php print get_request_var('rows');?>'>
		<input type='hidden' id='tab' value='neighbor'>
		</form>
		<script type='text/javascript'>

		function applyFilter(e) {
			
			var elem = $(e);
			var cacti_only;
			popFired = true;
			strURL  = 'neighbor.php?header=false&action=xdp';
		  strURL += '&filter=' + $('#filter').val();
			strURL += '&xdp_type=' + $('#xdp_type').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&page=' + $('#page').val();
			strURL += '&host_id=' + ($('#host_id').val() > 0 ? $('#host_id').val() : '');
			strURL += '&cacti_only=' + ($('#cacti_only').is(':checked') ? 'on' : 'off');
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'neighbor.php?header=false&action=xdp&clear=1';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#neighbor').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
			
<?php






?>
	
	<?php
}

/**
 * Get user and group authentication policies
 *
 * @param int $user User ID
 * @return array Array of policy arrays containing id, type, policy_graphs, policy_hosts, policy_graph_templates
 */
function neighbor_get_user_policies($user) {
	$policies = array();
	
	// Get group policies for user
	$group_policies = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type,
		uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates
		FROM user_auth_group AS uag
		INNER JOIN user_auth_group_members AS uagm
		ON uag.id = uagm.group_id
		WHERE uag.enabled = 'on'
		AND uagm.user_id = ?",
		array($user)
	);
	
	if (is_array($group_policies) && sizeof($group_policies)) {
		$policies = array_merge($policies, $group_policies);
	}
	
	// Get user policy
	$user_policy = db_fetch_row_prepared("SELECT id, 'user' AS type,
		policy_graphs, policy_hosts, policy_graph_templates
		FROM user_auth
		WHERE id = ?",
		array($user)
	);
	
	if (is_array($user_policy) && sizeof($user_policy)) {
		$policies[] = $user_policy;
	}
	
	return $policies;
}

/**
 * Build SQL JOIN clauses for user authorization policies
 *
 * @param array $policies Array of policy records
 * @param string &$sql_select Reference to SELECT clause to append to
 * @param string &$sql_join Reference to JOIN clause to append to
 * @param string &$sql_having Reference to HAVING clause to append to
 * @return void Modifies parameters by reference
 */
function neighbor_build_auth_sql_clauses($policies, &$sql_select, &$sql_join, &$sql_having) {
	$i = 0;
	
	foreach ($policies as $policy) {
		// Build HAVING clause for graphs policy
		if ($policy['policy_graphs'] == 1) {
			$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NULL";
		} else {
			$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NOT NULL";
		}

		// Build JOIN for graph permissions
		$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
		$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
		$i++;

		// Build HAVING clause for hosts policy
		if ($policy['policy_hosts'] == 1) {
			$sql_having .= " OR (user$i IS NULL";
		} else {
			$sql_having .= " OR (user$i IS NOT NULL";
		}

		// Build JOIN for host permissions
		$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
		$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
		$i++;

		// Build HAVING clause for graph templates policy  
		if ($policy['policy_graph_templates'] == 1) {
			$sql_having .= " OR (user$i IS NULL";
		} else {
			$sql_having .= " OR (user$i IS NOT NULL";
		}

		// Build JOIN for graph template permissions
		$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
		$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
		$i++;

		// Close parentheses for all three policy checks
		$sql_having .= "))";
	}
}

/**
 * Get allowed devices with authentication and authorization applied
 * 
 * This function retrieves a list of devices that a user is authorized to view,
 * taking into account user and group permissions for graphs, hosts, and graph templates.
 * 
 * @param string $sql_where Additional WHERE clause conditions
 * @param string $order_by ORDER BY clause (default 'description')
 * @param string $limit LIMIT clause (default '')
 * @param int &$total_rows Reference to store total row count
 * @param int $user User ID (0 = current user, -1 = bypass auth)
 * @param int $host_id Specific host ID to filter (0 = all hosts)
 * 
 * @return array Array of host records the user is authorized to view
 */
function neighbor_get_allowed_devices($sql_where = '', $order_by = 'description', $limit = '', &$total_rows = 0, $user = 0, $host_id = 0) {
	if ($limit != '') {
		$limit = "LIMIT $limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND':'') . ' h.disabled=""';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE $sql_where";
	}

	if ($host_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " h.id=$host_id";
	}

	if ($user == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	$poller_interval = read_config_option('poller_interval');

	if ($auth_method != 0) {
		if ($user == 0) {
			if (isset($_SESSION['sess_user_id'])) {
				$user = $_SESSION['sess_user_id'];
			} else {
				return array();
			}
		}

		if (read_config_option('graph_auth_method') == 1) {
			$sql_operator = 'OR';
		} else {
			$sql_operator = 'AND';
		}

		/* get policies for all groups and user */
		$policies   = db_fetch_assoc_prepared("SELECT uag.id, 'group' AS type,
			uag.policy_graphs, uag.policy_hosts, uag.policy_graph_templates
			FROM user_auth_group AS uag
			INNER JOIN user_auth_group_members AS uagm
			ON uag.id = uagm.group_id
			WHERE uag.enabled = 'on'
			AND uagm.user_id = ?",
			array($user)
		);

		$policies[] = db_fetch_row_prepared("SELECT id, 'user' AS type,
			policy_graphs, policy_hosts, policy_graph_templates
			FROM user_auth
			WHERE id = ?",
			array($user)
		);

		$i          = 0;
		$sql_select = '';
		$sql_join   = '';
		$sql_having = '';

		foreach ($policies as $policy) {
			if ($policy['policy_graphs'] == 1) {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NULL";
			} else {
				$sql_having .= ($sql_having != '' ? ' OR ' : '') . "(user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.id=uap$i.item_id AND uap$i.type=1 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_hosts'] == 1) {
				$sql_having .= " OR (user$i IS NULL";
			} else {
				$sql_having .= " OR (user$i IS NOT NULL";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.host_id=uap$i.item_id AND uap$i.type=3 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;

			if ($policy['policy_graph_templates'] == 1) {
				$sql_having .= " $sql_operator user$i IS NULL))";
			} else {
				$sql_having .= " $sql_operator user$i IS NOT NULL))";
			}

			$sql_join   .= 'LEFT JOIN user_auth_' . ($policy['type'] == 'user' ? '':'group_') . "perms AS uap$i ON (gl.graph_template_id=uap$i.item_id AND uap$i.type=4 AND uap$i." . $policy['type'] . "_id=" . $policy['id'] . ") ";
			$sql_select .= ($sql_select != '' ? ', ' : '') . "uap$i." . $policy['type'] . "_id AS user$i";
			$i++;
		}

		$sql_having = "HAVING $sql_having";

		$host_list = db_fetch_assoc("SELECT h1.*, graphs, data_sources,
			IF(status_event_count>0, status_event_count*$poller_interval,
			IF(UNIX_TIMESTAMP(status_rec_date)>943916400,UNIX_TIMESTAMP()-UNIX_TIMESTAMP(status_rec_date),
			IF(snmp_sysUptimeInstance>0 AND snmp_version > 0, snmp_sysUptimeInstance,UNIX_TIMESTAMP()))) AS instate
			FROM host AS h1
			INNER JOIN (
				SELECT DISTINCT id FROM (
					SELECT h.*, $sql_select
					FROM host AS h
					LEFT JOIN graph_local AS gl
					ON h.id=gl.host_id
					LEFT JOIN graph_templates_graph AS gtg
					ON gl.id=gtg.local_graph_id
					LEFT JOIN graph_templates AS gt
					ON gt.id=gl.graph_template_id
					LEFT JOIN host_template AS ht
					ON h.host_template_id=ht.id
					$sql_join
					$sql_where
					$sql_having
				) AS rs1
			) AS rs2
			ON rs2.id=h1.id
			LEFT JOIN (SELECT host_id, COUNT(*) AS graphs FROM graph_local GROUP BY host_id) AS gl
			ON h1.id=gl.host_id
			LEFT JOIN (SELECT host_id, COUNT(*) AS data_sources FROM data_local GROUP BY host_id) AS dl
			ON h1.id=dl.host_id
			$order_by
			$limit"
		);

		$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT id)
			FROM (
				SELECT h.id, $sql_select
				FROM host AS h
				LEFT JOIN graph_local AS gl
				ON h.id=gl.host_id
				LEFT JOIN graph_templates_graph AS gtg
				ON gl.id=gtg.local_graph_id
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id
				$sql_join
				$sql_where
				$sql_having
			) AS rower"
		);
	} else {
		$host_list = db_fetch_assoc("SELECT h1.*, graphs, data_sources,
			IF(status_event_count>0, status_event_count*$poller_interval,
			IF(UNIX_TIMESTAMP(status_rec_date)>943916400,UNIX_TIMESTAMP()-UNIX_TIMESTAMP(status_rec_date),
			IF(snmp_sysUptimeInstance>0 AND snmp_version > 0, snmp_sysUptimeInstance,UNIX_TIMESTAMP()))) AS instate
			FROM host AS h1
			INNER JOIN (
				SELECT DISTINCT id FROM (
					SELECT h.*
					FROM host AS h
					LEFT JOIN graph_local AS gl
					ON h.id=gl.host_id
					LEFT JOIN graph_templates_graph AS gtg
					ON gl.id=gtg.local_graph_id
					LEFT JOIN graph_templates AS gt
					ON gt.id=gl.graph_template_id
					LEFT JOIN host_template AS ht
					ON h.host_template_id=ht.id
					$sql_where
				) AS rs1
			) AS rs2
			ON rs2.id=h1.id
			LEFT JOIN (SELECT host_id, COUNT(*) AS graphs FROM graph_local GROUP BY host_id) AS gl
			ON h1.id=gl.host_id
			LEFT JOIN (SELECT host_id, COUNT(*) AS data_sources FROM data_local GROUP BY host_id) AS dl
			ON h1.id=dl.host_id
			$order_by
			$limit"
		);

		$total_rows = db_fetch_cell("SELECT COUNT(DISTINCT id)
			FROM (
				SELECT h.id
				FROM host AS h
				LEFT JOIN graph_local AS gl
				ON h.id=gl.host_id
				LEFT JOIN graph_templates_graph AS gtg
				ON gl.id=gtg.local_graph_id
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN host_template AS ht
				ON h.host_template_id=ht.id
				$sql_where
			) AS rower"
		);
	}

	return $host_list;
}

/* VRF Mapping Functions */

/**
 * Build VRF topology maps for all defined VRF rules
 * 
 * Iterates through all VRF mapping rules and builds network topology data
 * by querying neighbor relationships and organizing them by VRF. Used for
 * multi-tenancy network visualization.
 * 
 * @return array Nested array of VRF mappings with topology data
 */
function get_neighbor_vrf_maps() {
	
	// Initialize mapping array BEFORE the loop to preserve all rules' mappings
	$mapping = [];
	
	$rules = get_vrf_rules();
	foreach ($rules as $rule) {
		
		$rule_id 		= isset($rule['id']) ? $rule['id'] : 0;
		$rule_name	= isset($rule['name']) ? $rule['name'] : "";
		$vrf 				= isset($rule['vrf']) ? $rule['vrf'] : "";
		
		if ($rule_id) {
			
				$sql_query = neighbor_build_vrf_data_query_sql($rule);
				$result = db_fetch_assoc($sql_query);
				
				// Check if result is valid before processing
				if (is_array($result) && count($result) > 0) {
					$result_vrf = [];
					// Merge the VRF name into the results
					foreach ($result as $result) { $result['vrf'] = $vrf; $result_vrf[] = $result;}
					$hash = db_fetch_hash($result_vrf,array("host_id","ip_address"));
					$mapping = array_replace($mapping,$hash);				
					//cacti_log("get_neighbor_vrf_maps(): Rule '$rule_name'=".pre_print_r($mapping,"VRF:",false),false, 'NEIGHBOR TRACE');
				}
		}
		
	}
	return($mapping);
}

/**
 * Retrieve all VRF mapping rules from database
 * 
 * Returns all records from the plugin_neighbor_vrf_rules table which define
 * how neighbor relationships should be organized by VRF for multi-tenant networks.
 * 
 * @return array Array of VRF rule records
 */
function get_vrf_rules() {
	$rules = db_fetch_assoc("SELECT * from plugin_neighbor_vrf_rules");
	return($rules);
}

/**
 * Build SQL query for VRF-based neighbor data retrieval
 * 
 * Constructs a complex SQL query that joins host and neighbor data tables
 * based on VRF rule configuration, applying filters for matching hosts and edges.
 * 
 * @param array $rule VRF rule configuration record
 * @param string $host_filter Optional hostname filter (default '')
 * @param string $edge_filter Optional edge/connection filter (default '')
 * 
 * @return string Complete SQL query string
 */
function neighbor_build_vrf_data_query_sql($rule,$host_filter = '',$edge_filter='') {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule), false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	
	$sql_query = 'SELECT h.description AS automation_host, h.disabled, h.status ';
	$neighbor_options = isset($rule['neighbor_options']) ? explode(",",$rule['neighbor_options']) : array();
		
	$tables = array("plugin_neighbor_ipv4_cache as cache");
	$table_join = array("LEFT JOIN plugin_neighbor_ipv4_cache cache ON cache.host_id=h.id");
	$cols = db_get_table_column_types("plugin_neighbor_ipv4_cache");
	foreach ($cols as $col => $rec) {
		$sql_query .= ", cache.$col";
	}
						

	/* take matching hosts into account */
	$rule_id = isset($rule['id']) ? $rule['id'] : '';
	$sql_where = trim(neighbor_build_vrf_matching_objects_filter($rule_id, AUTOMATION_RULE_TYPE_GRAPH_MATCH));
	$sql_where2 = trim(neighbor_build_vrf_object_rule_item_filter($rule_id));
	$sql_where_combined = array();
	
	if ($sql_where !== '') {
		$sql_where_combined[] = "($sql_where)";
	}
	
	if ($sql_where2 !== '') {
		$sql_where_combined[] = "($sql_where2)";
	}
	
	$table_list = implode(",",$tables);
	$table_join_list = implode(" ",$table_join);
	$query_where = sizeof($sql_where_combined) ? "WHERE ".implode(" AND ",$sql_where_combined) : "";
	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query .= " FROM host as h
		$table_join_list
	    $query_where
	";

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_query, false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_query;
}


function neighbor_build_vrf_object_rule_item_filter($rule_id, $prefix = '') {
	
	global $automation_op_array, $automation_oper;
	$sql_filter = '';
	
	if ($rule_id) {
		
		$object_rule_items = db_fetch_assoc_prepared("SELECT * from plugin_neighbor_vrf_rule_items where rule_id=?",array($rule_id));
	
		if (count((array) $object_rule_items)) {
			$sql_filter = ' ';
	
			foreach($object_rule_items as $object_rule_item) {
				# AND|OR|(|)
				if ($object_rule_item['operation'] != AUTOMATION_OPER_NULL) {
					$sql_filter .= ' ' . $automation_oper[$object_rule_item['operation']];
				}
	
				# right bracket ')' does not come with a field
				if ($object_rule_item['operation'] == AUTOMATION_OPER_RIGHT_BRACKET) {
					continue;
				}
	
				# field name
				if ($object_rule_item['field'] != '') {
					
					$sql_filter .= (' ' . $prefix . '`' . implode('`.`', explode('.', $object_rule_item['field'])) . '`');
					#
					$sql_filter .= ' ' . $automation_op_array['op'][$object_rule_item['operator']] . ' ';
					if ($automation_op_array['binary'][$object_rule_item['operator']]) {
						$sql_filter .= (db_qstr($automation_op_array['pre'][$object_rule_item['operator']] . $object_rule_item['pattern'] . $automation_op_array['post'][$object_rule_item['operator']]));
					}
				}
			}
		}
	}

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_filter, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_filter;
}

function neighbor_build_vrf_matching_objects_filter($rule_id, $rule_type) {
	cacti_log(__FUNCTION__ . " called rule id: $rule_id", false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	$sql_filter = '';

	/* create an SQL which queries all host related tables in a huge join
	 * this way, we may add any where clause that might be added via
	 *  'Matching Device' match
	 */
	$rule_items = db_fetch_assoc_prepared('SELECT *
		FROM plugin_neighbor_vrf_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?
		ORDER BY sequence',
		array($rule_id, $rule_type));

	#print '<pre>Items: $sql<br>'; print_r($rule_items); print '</pre>';

	if (count((array) $rule_items)) {
		$sql_filter	= neighbor_build_vrf_rule_item_filter($rule_items);
	} else {
		/* force empty result set if no host matching rule item present */
		$sql_filter = ' (1 != 1)';
	}

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_filter, false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_filter;
}


function neighbor_build_vrf_rule_item_filter($automation_rule_items, $prefix = '') {
	global $automation_op_array, $automation_oper;

	cacti_log(__FUNCTION__ . ' called: ' . serialize($automation_rule_items) . ", prefix: $prefix", false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	$sql_filter = '';
	if (count((array) $automation_rule_items)) {
		$sql_filter = ' ';

		foreach($automation_rule_items as $automation_rule_item) {
			# AND|OR|(|)
			if ($automation_rule_item['operation'] != AUTOMATION_OPER_NULL) {
				$sql_filter .= ' ' . $automation_oper[$automation_rule_item['operation']];
			}

			# right bracket ')' does not come with a field
			if ($automation_rule_item['operation'] == AUTOMATION_OPER_RIGHT_BRACKET) {
				continue;
			}

			# field name
			if ($automation_rule_item['field'] != '') {
				$sql_filter .= (' ' . $prefix . '`' . implode('`.`', explode('.', $automation_rule_item['field'])) . '`');
				#
				$sql_filter .= ' ' . $automation_op_array['op'][$automation_rule_item['operator']] . ' ';
				if ($automation_op_array['binary'][$automation_rule_item['operator']]) {
					$sql_filter .= (db_qstr($automation_op_array['pre'][$automation_rule_item['operator']] . $automation_rule_item['pattern'] . $automation_op_array['post'][$automation_rule_item['operator']]));
				}
			}
		}
	}

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_filter, false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_filter;
}

/* Helper Functions */


// For PHP pre-5.3 put in an array_replace function
if(!function_exists("array_replace")){
		function array_replace(){
			 $args = func_get_args();
			 $ret = array_shift($args);
			 foreach($args as $arg){
					 foreach($arg as $k=>$v){
							$ret[(string)$k] = $v;
					 }
			 }
			 return $ret;
	 }
}

function snipToDots($str,$len) {
    
    if(strlen($str)<=$len) { return "<span>$str</span>";}
    else {
        $snip = strlen($str) > $len ? substr($str,0,$len)."..." : $str;
	return "<span title='$str'> $snip </span>";
    }
}

function pre_print_r($arr,$tag = '',$print = true) {
    
		if ($print) {
			print "<pre>";
			if ($tag) { print "$tag\n";}
			print_r($arr);
			print "</pre>";
		}
		else {
			$buffer = print_r($arr,true);
			$ret = "";
			foreach (explode("\n",$buffer) as $line) {
						$ret.= $tag ? "$tag: ".$line."\n" : "$line\n";
			}
			return($ret);
		}
    
}


# ------------------------------------------------------------
# Automation Rules
# ------------------------------------------------------------
/* file: automation_graph_rules.php, automation_tree_rules.php, action: edit */
$fields_neighbor_match_rule_item_edit = array(
	'operation' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Operation'),
		'description' => __('Logical operation to combine rules.'),
		'array' => $automation_oper,
		'value' => '|arg1:operation|',
		'on_change' => 'toggle_operation()',
	),
	'field' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Field Name'),
		'description' => __('The Field Name that shall be used for this Rule Item.'),
		'array' => array(),			# to be filled dynamically
		'value' => '|arg1:field|',
		'none_value' => __('None'),
	),
	'operator' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Operator'),
		'description' => __('Operator.'),
		'array' => $automation_op_array['display'],
		'value' => '|arg1:operator|',
		'on_change' => 'toggle_operator()',
	),
	'pattern' => array(
		'method' => 'textbox',
		'friendly_name' => __('Matching Pattern'),
		'description' => __('The Pattern to be matched against.'),
		'value' => '|arg1:pattern|',
		'max_length' => '255',
		'size' => '50',
	),
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => __('Sequence'),
		'description' => __('Sequence.'),
		'value' => '|arg1:sequence|',
	)
);

/* file: automation_graph_rules.php, action: edit */
$fields_neighbor_graph_rule_item_edit = array(
	'operation' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Operation'),
		'description' => __('Logical operation to combine rules.'),
		'array' => $automation_oper,
		'value' => '|arg1:operation|',
		'on_change' => 'toggle_operation()',
	),
	'field' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Field Name'),
		'description' => __('The Field Name that shall be used for this Rule Item.'),
		'array' => array(),			# later to be filled dynamically
		'value' => '|arg1:field|',
		'none_value' => __('None'),
	),
	'operator' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Operator'),
		'description' => __('Operator.'),
		'array' => $automation_op_array['display'],
		'value' => '|arg1:operator|',
		'on_change' => 'toggle_operator()',
	),
	'pattern' => array(
		'method' => 'textbox',
		'friendly_name' => __('Matching Pattern'),
		'description' => __('The Pattern to be matched against.'),
		'value' => '|arg1:pattern|',
		'max_length' => '255',
		'size' => '50',
	),
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => __('Sequence'),
		'description' => __('Sequence.'),
		'value' => '|arg1:sequence|',
	)
);

$fields_neighbor_graph_rules_edit1 = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this Mule.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80'
	),
	/*

	*/
	'description' => array(
		'method' => 'textbox',
		'friendly_name' => __('Description'),
		'description' => __('A friendly description of this rule.'),
		'value' => '|arg1:description|',
		'max_length' => '255',
		'size' => '80'
	)
);

$fields_neighbor_graph_rules_edit2 = array(
	'neighbor_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Neighbor Type'),
		'description' => __('Choose the type of neighbor'),
		'value' => '|arg1:neighbor_type|',
		'on_change' => 'applyNeighborTypeChange()',
		'array' => array(
			'interface'	=> 'Interface',
			'routing'	=> 'Routing Protocol',
		),
		'default' => 'interface',
	),
	
);

$fields_neighbor_graph_rules_edit3 = array(
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable Rule'),
		'description' => __('Check this box to enable this rule.'),
		'value' => '|arg1:enabled|',
		'default' => '',
		'form_id' => false
	)
);

/* file: automation_tree_rules.php, action: edit */
$fields_neighbor_tree_rules_edit1 = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this Rule.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80'
	),
	'tree_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Tree'),
		'description' => __('Choose a Tree for the new Tree Items.'),
		'value' => '|arg1:tree_id|',
		'on_change' => 'applyTreeChange()',
		'sql' => 'SELECT id, name FROM graph_tree ORDER BY name'
	),
	'leaf_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Leaf Item Type'),
		'description' => __('The Item Type that shall be dynamically added to the tree.'),
		'value' => '|arg1:leaf_type|',
		'on_change' => 'applyItemTypeChange()',
		'array' => $automation_tree_item_types
	),
	'host_grouping_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Graph Grouping Style'),
		'description' => __('Choose how graphs are grouped when drawn for this particular host on the tree.'),
		'array' => $host_group_types,
		'value' => '|arg1:host_grouping_type|',
		'default' => HOST_GROUPING_GRAPH_TEMPLATE,
	)
);

$fields_neighbor_tree_rules_edit2 = array(
	'tree_item_id' => array(
		'method' => 'drop_tree',
		'friendly_name' => __('Optional: Sub-Tree Item'),
		'description' => __('Choose a Sub-Tree Item to hook in.<br>Make sure, that it is still there when this rule is executed!'),
		'tree_id' => '|arg1:tree_id|',
		'value' => '|arg1:tree_item_id|',
	)
);

$fields_neighbor_tree_rules_edit3 = array(
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable Rule'),
		'description' => __('Check this box to enable this rule.'),
		'value' => '|arg1:enabled|',
		'default' => '',
		'form_id' => false
	)
);

$fields_neighbor_tree_rule_item_edit = array(
	'field' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Header Type'),
		'description' => __('Choose an Object to build a new Sub-header.'),
		'array' => array(),			# later to be filled dynamically
		'value' => '|arg1:field|',
		'none_value' => $automation_tree_header_types[AUTOMATION_TREE_ITEM_TYPE_STRING],
		'on_change' => 'applyHeaderChange()',
	),
	'sort_type' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Sorting Type'),
		'description' => __('Choose how items in this tree will be sorted.'),
		'value' => '|arg1:sort_type|',
		'default' => TREE_ORDERING_NONE,
		'array' => $tree_sort_types,
		),
	'propagate_changes' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Propagate Changes'),
		'description' => __('Propagate all options on this form (except for \'Title\') to all child \'Header\' items.'),
		'value' => '|arg1:propagate_changes|',
		'default' => '',
		'form_id' => false
		),
	'search_pattern' => array(
		'method' => 'textbox',
		'friendly_name' => __('Matching Pattern'),
		'description' => __('The String Pattern (Regular Expression) to match against.<br>Enclosing \'/\' must <strong>NOT</strong> be provided!'),
		'value' => '|arg1:search_pattern|',
		'max_length' => '255',
		'size' => '50',
		),
	'replace_pattern' => array(
		'method' => 'textbox',
		'friendly_name' => __('Replacement Pattern'),
		'description' => __('The Replacement String Pattern for use as a Tree Header.<br>Refer to a Match by e.g. <strong>\${1}</strong> for the first match!'),
		'value' => '|arg1:replace_pattern|',
		'max_length' => '255',
		'size' => '50',
		),
	'sequence' => array(
		'method' => 'view',
		'friendly_name' => __('Sequence'),
		'description' => __('Sequence.'),
		'value' => '|arg1:sequence|',
	)
);

$neighbor_interface_new_graph_fields = array(
	'type'		=> 'Type',
	'hostname'	=>	'A - Hostname',
	'interface_name'	=> 'A - Interface',
	'interface_alias'	=> 'A - Description',
	'interface_status'	=> 'Status',
	'neighbor_hostname' 		=>	'B - Hostname',
	'neighbor_interface_name'	=> 'B - Interface',
	'neighbor_interface_alias'	=> 'B - Description'
);

// VRF Arrays

$neighbor_vrf_object_fields = array(
	'hostname'	=>	'Hostname',
	'ip_address'	=> 'IP Address',
	'ip_netmask'	=> 'IP Netmask',
	'snmp_id'	=> 'SNMP Index',
	'vrf' 		=>	'VRF Name',
	'last_seen'	=> 'Last Seen',
);


$fields_neighbor_vrf_rules_edit1 = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A useful name for this Rule.'),
		'value' => '|arg1:name|',
		'max_length' => '255',
		'size' => '80'
	),
	'description' => array(
		'method' => 'textbox',
		'friendly_name' => __('Description'),
		'description' => __('A friendly description of this Rule.'),
		'value' => '|arg1:description|',
		'max_length' => '255',
		'size' => '80'
	)
);

$fields_neighbor_vrf_rules_edit2 = array(
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable Rule'),
		'description' => __('Check this box to enable this rule.'),
		'value' => '|arg1:enabled|',
		'default' => '',
		'form_id' => false
	)
);


?>
