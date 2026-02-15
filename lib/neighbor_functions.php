<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2017 The Cacti Group                                 |
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

function neighbor_tabs() {
	global $config;
	printf("<link href='%s' rel='stylesheet'>", "js/devexpress/css/dx.common.css");
	printf("<link href='%s' rel='stylesheet'>", "js/devexpress/css/dx.light.css");
	printf("<script type='text/javascript' src='%s'></script>",'js/devexpress/js/cldr.min.js');
	printf("<script type='text/javascript' src='%s'></script>",'js/devexpress/js/dx.all.js');
	printf("<script type='text/javascript' src='%s'></script>",'js/neighbor.js');
	print "<div id='neighbor_tabs'></div>";
	
	return;
	
	/* present a tabbed interface */
	$tabs = array(
		'summary'    => __('Summary', 'neighbor'),
		'xdp'        => __('xDP Neighbors', 'neighbor'),
		'ip_subnet'  => __('IP Neighbors', 'neighbor'),
		'maps'			 => __('Maps','neighbor')
	);

	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));
	load_current_session_value('tab', 'sess_neighbor_tab', 'general');
	$current_tab = get_request_var('action');

	/* draw the tabs */
	print "<div class='tabs'><nav><ul>\n";

	if (sizeof($tabs)) {
		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . htmlspecialchars($config['url_path'] .
				'plugins/neighbor/neighbor.php?' .
				'action=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>\n";
		}
	}

	print "</ul></nav></div>\n";
}



// Get the xDP neighbors
// args:
//	total_rows = pointer to cacti $total_rows for pagination
// 	filterField = filter field (default = '')
// 	filterVal = filter value (default = '')
// 	orderField = order field (default = '')
// 	orderDir = order direction (default = 'asc')
// 	rowStart = start row for pagination (default = 0)
// 	rowEnd = end row for pagination (default = 25)
//	output = output format - either array or json (default = array)
//	cacti = show only hosts known to cacti (default = true)

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
				print "Filter:<br>";
				$searchArray  = array('hostname','neighbor_hostname','interface_name','interface_alias','neighbor_interface_name','neighbor_interface_alias','neighbor_platform', 'neighbor_software');
				$searchFields = array();
				$searchParams = array();
				foreach ($searchArray as $f) { array_push($searchFields,"`$f` LIKE ?"); array_push($searchParams,"%$filterVal%");}
				$searchMerged = "(".implode(" OR ", $searchFields).")";
				//pre_print_r($searchFields);
				//pre_print_r($searchParams);
				//print "searchMerged: $searchMerged<br>";
				array_push($conditions,$searchMerged);
				$params = array_merge($params,$searchParams);
		}
		
    $sqlWhere = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $result = db_fetch_assoc_prepared("select * from plugin_neighbor__xdp xdp $sqlWhere $sqlOrder $sqlLimit", $params);
    $total_rows = db_fetch_cell_prepared("select count(*) as total_rows from plugin_neighbor__xdp xdp $sqlWhere",$params);
    //print "Set total_rows = $total_rows<br>";
    if ($output == 'array') 	{ return($result);}
    elseif ($output == 'json') 	{ return(json_encode($result));}
}


function getXdpNeighborStats(&$total_rows = 0) {
    
    $numHosts = db_fetch_cell("select count(distinct host_id) from plugin_neighbor__xdp");
    $numInterfaces = db_fetch_cell("select count(distinct concat(host_id,':',snmp_id)) from plugin_neighbor__xdp;");
    $lastPolled = db_fetch_cell("select last_seen from plugin_neighbor__xdp order by last_seen desc limit 1");
    
    if ($numHosts || $numInterfaces) { $total_rows++;}
    
    return(array('hosts'=>$numHosts, 'interfaces'=> $numInterfaces, 'last_polled' => $lastPolled));
}

/* Helper and Override functions */

// Emulates perl DBIs fetchall_hashref functionality
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

function plugin_cacti_snmp_walk($hostname, $community, $oid, $version, $username, $password,
        $auth_proto, $priv_pass, $priv_proto, $context,
        $port = 161, $timeout = 500, $retries = 0, $max_oids = 10, $environ = SNMP_POLLER,
        $engineid = '', $value_output_format = SNMP_STRING_OUTPUT_GUESS) {

        global $config, $banned_snmp_strings, $snmp_error;

        $snmp_oid_included = true;
        $snmp_auth             = '';
        $snmp_array        = array();
        $temp_array        = array();

        if (!cacti_snmp_options_sanitize($version, $community, $port, $timeout, $retries, $max_oids)) {
                return array();
        }

        $path_snmpbulkwalk = read_config_option('path_snmpbulkwalk');

        if (snmp_get_method('walk', $version, $context, $engineid, $value_output_format) == SNMP_METHOD_PHP) {
                /* make sure snmp* is verbose so we can see what types of data
                we are getting back */

                /* force php to return numeric oid's */
                cacti_oid_numeric_format();

                if (function_exists('snmprealwalk')) {
                        $snmp_oid_included = false;
                }

                snmp_set_quick_print(0);

                if ($version == '1') {
                        $temp_array = snmprealwalk($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
                } elseif ($version == 2) {
                        $temp_array = snmp2_real_walk($hostname . ':' . $port, $community, $oid, ($timeout * 1000), $retries);
                } else {
                        if ($priv_proto == '[None]' || $priv_pass == '') {
                                $sec_level = 'authNoPriv';
                                $priv_proto = '';
                        } else {
                                $sec_level = 'authPriv';
                        }

                        $temp_array = snmp3_real_walk($hostname . ':' . $port, $username, $sec_level, $auth_proto, $password, $priv_proto, $priv_pass, $oid, ($timeout * 1000), $retries);
                }

                if ($temp_array === false) {
                        if ($temp_array === false) {
				// currently exists at this OID
				if (!preg_match('/No Such Object available on this agent at this OID/',$snmp_error) && !preg_match('/currently exists at this OID/',$snmp_error)) {
                                	cacti_log("WARNING: SNMP Error:'$snmp_error', Device:'$hostname', OID:'$oid'", false);
				}
                        } elseif ($oid == '.1.3.6.1.2.1.47.1.1.1.1.2' ||
                                $oid == '.1.3.6.1.4.1.9.9.68.1.2.2.1.2' ||
                                $oid == '.1.3.6.1.4.1.9.9.46.1.6.1.1.5' ||
                                $oid == '.1.3.6.1.4.1.9.9.46.1.6.1.1.14' ||
                                $oid == '.1.3.6.1.4.1.9.9.23.1.2.1.1.6') {
                                /* do nothing */
                        } else {
                                cacti_log("WARNING: SNMP Error, Device:'$hostname', OID:'$oid'", false);
                        }
                }

                /* check for bad entries */
                if ($temp_array !== false && sizeof($temp_array)) {
                        foreach($temp_array as $key => $value) {
                                foreach($banned_snmp_strings as $item) {
                                        if (strstr($value, $item) != '') {
                                                unset($temp_array[$key]);
                                                continue 2;
                                        }
                                }
                        }

                        $o = 0;
                        for (reset($temp_array); $i = key($temp_array); next($temp_array)) {
                                if ($temp_array[$i] != 'NULL') {
                                        $snmp_array[$o]['oid'] = preg_replace('/^\./', '', $i);
                                        $snmp_array[$o]['value'] = format_snmp_string($temp_array[$i], $snmp_oid_included, $value_output_format);
                                }
                                $o++;
                        }
                }
        } else {
                /* ucd/net snmp want the timeout in seconds */
                $timeout = ceil($timeout / 1000);

                if ($version == '1') {
                        $snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
                } elseif ($version == '2') {
                        $snmp_auth = '-c ' . snmp_escape_string($community); /* v1/v2 - community string */
                        $version = '2c'; /* ucd/net snmp prefers this over '2' */
                } elseif ($version == '3') {
                        if ($priv_proto == '[None]' || $priv_pass == '') {
                                $sec_level = 'authNoPriv';
                                $priv_proto = '';
                        } else {
                                $sec_level = 'authPriv';
                        }

                        if ($priv_pass != '') {
                                $priv_pass = '-X ' . snmp_escape_string($priv_pass) . ' -x ' . snmp_escape_string($priv_proto);
                        } else {
                                $priv_pass = '';
                        }

                        if ($context != '') {
                                $context = '-n ' . snmp_escape_string($context);
                        } else {
                                $context = '';
                        }

                        if ($engineid != '') {
                                $engineid = '-e ' . snmp_escape_string($engineid);
                        } else {
                                $engineid = '';
                        }

                        $snmp_auth = trim('-u ' . snmp_escape_string($username) .
                                ' -l ' . snmp_escape_string($sec_level) .
                                ' -a ' . snmp_escape_string($auth_proto) .
                                ' -A ' . snmp_escape_string($password) .
                                ' '    . $priv_pass .
                                ' '    . $context .
                                ' '    . $engineid);
                }

                if (read_config_option('oid_increasing_check_disable') == 'on') {
                        $oidCheck = '-Cc';
                } else {
                        $oidCheck = '';
                }

                if (file_exists($path_snmpbulkwalk) && ($version > 1) && ($max_oids > 1)) {
                        $temp_array = exec_into_array(cacti_escapeshellcmd($path_snmpbulkwalk) .
                                ' -O QnU'  . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
                                ' -v '     . $version .
                                ' -t '     . $timeout .
                                ' -r '     . $retries .
                                ' -Cr'     . $max_oids .
                                ' '        . $oidCheck . ' ' .
                                cacti_escapeshellarg($hostname) . ':' . $port . ' ' .
                                cacti_escapeshellarg($oid));
                } else {
                        $temp_array = exec_into_array(cacti_escapeshellcmd(read_config_option('path_snmpwalk')) .
                                ' -O QnU ' . ($value_output_format == SNMP_STRING_OUTPUT_HEX ? 'x ':' ') . $snmp_auth .
                                ' -v '     . $version .
                                ' -t '     . $timeout .
                                ' -r '     . $retries .
                                ' '        . $oidCheck . ' ' .
                                ' '        . cacti_escapeshellarg($hostname) . ':' . $port .
                                ' '        . cacti_escapeshellarg($oid));
                }

                if (substr_count(implode(' ', $temp_array), 'Timeout:')) {
                        cacti_log("WARNING: SNMP Error:'Timeout', Device:'$hostname', OID:'$oid'", false);
                }

                /* check for bad entries */
                if (is_array($temp_array) && sizeof($temp_array)) {
                        foreach($temp_array as $key => $value) {
                                foreach($banned_snmp_strings as $item) {
                                        if (strstr($value, $item) != '') {
                                                unset($temp_array[$key]);
                                                continue 2;
                                        }
                                }
                        }

                        $i = 0;
                        foreach($temp_array as $index => $value) {
                                if (preg_match('/(.*) =.*/', $value)) {
                                        $snmp_array[$i]['oid']   = trim(preg_replace('/(.*) =.*/', "\\1", $value));
                                        $snmp_array[$i]['value'] = format_snmp_string($value, true, $value_output_format);
                                        $i++;
                                } else {
                                        $snmp_array[$i-1]['value'] .= $value;
                                }
                        }
                }
        }

        return $snmp_array;
}

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
    $result = db_fetch_assoc_prepared("select * from plugin_neighbor__rules rules $sqlWhere $sqlOrder $sqlLimit", $params);
    $total_rows = db_fetch_cell_prepared("select count(*) as total_rows from plugin_neighbor__rules rules $sqlWhere",$params);
    //print "Set total_rows = $total_rows<br>";
    if ($output == 'array') 	{ return($result);}
    elseif ($output == 'json') 	{ return(json_encode($result));}
	
}

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
				<td> <input type='text' id='filter' size='25' value='<?php print get_request_var('filter');?>' onChange='applyFilter()'></td>
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
			//if (elem.attr('id') == 'cacti_only') {
			//	console.log("Current cacti_only:",elem.val());
			//	cacti_only = elem.val() == 'on' ? 'off' : 'on';	// Toggle the value
			//}
			popFired = true;
			strURL  = 'neighbor.php?header=false&action=xdp';
		  strURL += '&filter=' + $('#filter').val();
			strURL += '&xdp_type=' + $('#xdp_type').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&page=' + $('#page').val();
			strURL += '&host_id=' + ($('#host_id').val() > 0 ? $('#host_id').val() : '');
			strURL += '&cacti_only=' + ($('#cacti_only').is(':checked') ? 'on' : 'off');
			//strURL += '&cacti_only=' + cacti_only;
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
// Back to PHP






?>
	<?php
}

// Fetch all the neighbor hosts
// Copied from thold_plugin

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

// Get the VRF maps for all vrf rules defined
function get_neighbor_vrf_maps() {
	
	$rules = get_vrf_rules();
	foreach ($rules as $rule) {
		
		$rule_id 		= isset($rule['id']) ? $rule['id'] : 0;
		$rule_name	= isset($rule['name']) ? $rule['name'] : "";
		$vrf 				= isset($rule['vrf']) ? $rule['vrf'] : "";
		// Mapping will be an associative array indexed on $mapping[host_id][ip_address] = record
		$mapping = [];
		if ($rule_id) {
			
				$sql_query = neighbor_build_vrf_data_query_sql($rule);
				$result = db_fetch_assoc($sql_query);
				$result_vrf = [];
				// Merge the VRF name into the results
				foreach ($result as $result) { $result['vrf'] = $vrf; $result_vrf[] = $result;}
				$hash = db_fetch_hash($result_vrf,array("host_id","ip_address"));
				$mapping = array_replace($mapping,$hash);				
				//cacti_log("get_neighbor_vrf_maps(): Rule '$rule_name'=".pre_print_r($mapping,"VRF:",false),false, 'NEIGHBOR TRACE');
		}
		
	}
	return($mapping);
}

// Get a list of the vrf rules
function get_vrf_rules() {
	$rules = db_fetch_assoc("SELECT * from plugin_neighbor__vrf_rules");
	return($rules);
}

function neighbor_build_vrf_data_query_sql($rule,$host_filter = '',$edge_filter='') {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule), false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	/*
	$field_names = get_field_names($rule['snmp_query_id']);
	$sql_query = 'SELECT h.description AS automation_host, host_id, h.disabled, h.status, snmp_query_id, snmp_index ';
	$i = 0;
	*/
	
	$sql_query = 'SELECT h.description AS automation_host, h.disabled, h.status ';
	$neighbor_options = isset($rule['neighbor_options']) ? explode(",",$rule['neighbor_options']) : array();
		
	$tables = array("plugin_neighbor__ipv4_cache as cache");
	$table_join = array("LEFT JOIN plugin_neighbor__ipv4_cache cache ON cache.host_id=h.id");
	$cols = db_get_table_column_types("plugin_neighbor__ipv4_cache");
	foreach ($cols as $col => $rec) {
		$sql_query .= ", cache.$col";
	}
						

	/* take matching hosts into account */
	$rule_id = isset($rule['id']) ? $rule['id'] : '';
	$sql_where = "(".neighbor_build_vrf_matching_objects_filter($rule_id, AUTOMATION_RULE_TYPE_GRAPH_MATCH).")";
	$sql_where2 = "(".neighbor_build_vrf_object_rule_item_filter($rule_id).")";
	$sql_where_combined = array($sql_where,$sql_where2);
	
	//if ($host_filter) { array_push($sql_where_combined,"(h.description like '%$host_filter%')");}
	
	$table_list = implode(",",$tables);
	$table_join_list = implode(" ",$table_join);
	$query_where = sizeof($sql_where_combined) ? "WHERE ".implode(" AND ",$sql_where_combined) : "";
	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query .= " FROM host as h
		$table_join_list
	    $query_where
	";

	error_log("neighbor_build_data_query_sql():".$sql_query);
	cacti_log(__FUNCTION__ . ' returns: ' . $sql_query, false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_query;
}


function neighbor_build_vrf_object_rule_item_filter($rule_id, $prefix = '') {
	
	global $automation_op_array, $automation_oper;
	$sql_filter = '';
	
	if ($rule_id) {
		
		$object_rule_items = db_fetch_assoc_prepared("SELECT * from plugin_neighbor__vrf_rule_items where rule_id=?",array($rule_id));
	
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
		FROM plugin_neighbor__vrf_match_rule_items
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
