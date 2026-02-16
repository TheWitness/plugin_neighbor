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
function neighbor_poller_output(&$rrd_update_array) {
	global $config, $debug;

	$edge_rra = get_edge_rra();
	//db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('',"Edges:".print_r($edge_rra,true)));
	$path_rra = $config['rra_path'];
	foreach ($rrd_update_array as $rrd => $rec) {
		$rra_subst = str_replace($path_rra,"<path_rra>",$rrd);
		//db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('',"$rra_subst:".print_r($rec,true)));
		if (!isset($edge_rra[$rra_subst])) {
			continue;
		}		// No point in storing outputs for everything
		
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
	
	db_execute_prepared("DELETE FROM plugin_neighbor_poller_output where timestamp < ?", array(time() - 900));	// Nothing older than 15 minutes
	return $rrd_update_array;
}

// Process the deltas from the poller_output hook
// Called from poller_bottom hook



function process_poller_deltas() {
	
	cacti_log("process_poller_deltas() is running", true, "NEIGHBOR POLLER");
	
	// Fetch the poller output samples

	db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('','process_poller_deltas() is starting.'));
	$results = db_fetch_assoc("SELECT * from plugin_neighbor_poller_output");
	if (!is_array($results)) {
		return;
	}
	//db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('','process_poller_deltas() has run db_fetch_assoc'));
	$hash = db_fetch_hash($results,array('rrd_file','timestamp','key_name'));
	if (!is_array($hash)) {
		return;
	}
	//db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('','process_poller_deltas() has run db_fetch_hash'));

		
	foreach ($hash as $rrdFile => $data) {
		cacti_log("process_poller_deltas() is processing RRD:$rrdFile,with data:" . print_r($data,1), true, "NEIGHBOR POLLER");
		$timestamps = array_keys($data);
		rsort($timestamps);		// We want the last two timestamps, so order them in reverse
		db_execute_prepared("INSERT into plugin_neighbor_log values (?,NOW(),?)",array('','process_poller_deltas() is running. Timestamps:'.print_r($timestamps,1)));
		
		if (sizeof($timestamps) >= 2) {
			$now = $timestamps[0];
			$before = $timestamps[1];
			$timeDelta = $now - $before;
			$poller_interval = read_config_option('poller_interval') ? read_config_option('poller_interval') : 300;
			$timestamp_cycle = intval($now / $poller_interval) * $poller_interval ;	// Normalise these down to a poller cycle boundary to group them together
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
	db_execute_prepared("DELETE FROM plugin_neighbor_poller_delta where timestamp < ?", array(time() - 900));	// Nothing older than 15 minutes
}


function db_fetch_hash(& $result,$index_keys) {
  $assoc = array();             // The array we're going to be returning
  foreach ($result as $row) {

        $pointer = & $assoc;            // Start the pointer off at the base of the array
        for ($i=0; $i<count($index_keys); $i++) {
                $key_name = $index_keys[$i];
								if (!array_key_exists($key_name,$row)) {
                        error_log("Error: Key [$key_name] is not present in the results output\n");
                        return(false);
                }

                $key_val= isset($row[$key_name]) ? $row[$key_name]  : "";
                if (!isset($pointer[$key_val])) {

                        $pointer[$key_val] = "";                // Start a new node
                        $pointer = & $pointer[$key_val];                // Move the pointer on to the new node
                }
                else {
                        $pointer = & $pointer[$key_val];            // Already exists, move the pointer on to the new node
                }
        } // for $i
        foreach ($row as $key => $val) { $pointer[$key] = $val; }
  } // $row
  return($assoc);
}

