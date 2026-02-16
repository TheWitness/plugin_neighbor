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


function neighbor_display_graph_rule_items($title, $rule_id, $rule_type, $module) {
	
	global $automation_op_array, $automation_oper, $automation_tree_header_types;
	$items = db_fetch_assoc_prepared('SELECT * FROM plugin_neighbor_graph_rule_items WHERE rule_id = ? ORDER BY sequence', array($rule_id));
	html_start_box($title, '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	$display_text = array(
		array('display' => __('Item'),      'align' => 'left'),
		array('display' => __('Sequence'),  'align' => 'left'),
		array('display' => __('Operation'), 'align' => 'left'),
		array('display' => __('Field'),     'align' => 'left'),
		array('display' => __('Operator'),  'align' => 'left'),
		array('display' => __('Pattern'),   'align' => 'left'),
		array('display' => __('Actions'),   'align' => 'right')
	);

	html_header($display_text, 2);

	$i = 0;
	if (sizeof($items)) {
		foreach ($items as $item) {
			$operation = ($item['operation'] != 0) ? $automation_oper[$item['operation']] : '&nbsp;';

			form_alternate_row();
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars($module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type) . '">Item#' . ($i+1) . '</a></td>';
			$form_data .= '<td>' . 	$item['sequence'] . '</td>';
			$form_data .= '<td>' . 	$operation . '</td>';
			$form_data .= '<td>' . 	$item['field'] . '</td>';
			$form_data .= '<td>' . 	(($item['operator'] > 0 || $item['operator'] == '') ? $automation_op_array['display'][$item['operator']] : '') . '</td>';
			$form_data .= '<td>' . 	$item['pattern'] . '</td>';

			$form_data .= '<td class="right nowrap">';

			if ($i != sizeof($items)-1) {
				$form_data .= '<a class="pic fa fa-awwow-down moveArrow" href="' . htmlspecialchars($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$form_data .= '<a class="pic fa fa-caret-up moveArrow" href="' . htmlspecialchars($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}
			$form_data .= '</td>';

			$form_data .= '<td class="right nowrap">
				<a class="pic deleteMarker fa fa-remove" href="' . htmlspecialchars($module . '?action=item_remove&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Delete') . '"></a></td>
			</tr>';

			print $form_data;
			$i++;
		}
	} else {
		print "<tr><td colspan='8'><em>" . __('No Graph Creation Criteria') . "</em></td></tr>\n";
	}

	html_end_box(true);

}

function neighbor_display_tree_rule_items($title, $rule_id, $item_type, $rule_type, $module) {
	
	global $automation_tree_header_types, $tree_sort_types, $host_group_types;
	$items = db_fetch_assoc_prepared('SELECT * FROM plugin_neighbor_tree_rule_items WHERE rule_id = ? ORDER BY sequence', array($rule_id));
	html_start_box($title, '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	$display_text = array(
		array('display' => __('Item'),             'align' => 'left'),
		array('display' => __('Sequence'),         'align' => 'left'),
		array('display' => __('Field Name'),       'align' => 'left'),
		array('display' => __('Sorting Type'),     'align' => 'left'),
		array('display' => __('Propagate Change'), 'align' => 'left'),
		array('display' => __('Search Pattern'),   'align' => 'left'),
		array('display' => __('Replace Pattern'),  'align' => 'left'),
		array('display' => __('Actions'),          'align' => 'right')
	);

	html_header($display_text, 2);

	$i = 0;
	if (sizeof($items)) {
		foreach ($items as $item) {
			#print '<pre>'; print_r($item); print '</pre>';
			$field_name = ($item['field'] === AUTOMATION_TREE_ITEM_TYPE_STRING) ? $automation_tree_header_types[AUTOMATION_TREE_ITEM_TYPE_STRING] : $item['field'];

			form_alternate_row();
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars($module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type) . '">' . __('Item') . '#' . ($i+1) . '</a></td>';
			$form_data .= '<td>' . 	$item['sequence'] . '</td>';
			$form_data .= '<td>' . 	$field_name . '</td>';
			$form_data .= '<td>' . 	$tree_sort_types[$item['sort_type']] . '</td>';
			$form_data .= '<td>' . 	($item['propagate_changes'] ? 'Yes' : 'No') . '</td>';
			$form_data .= '<td>' . 	$item['search_pattern'] . '</td>';
			$form_data .= '<td>' . 	$item['replace_pattern'] . '</td>';

			$form_data .= '<td class="right">';
			if ($i != sizeof($items)-1) {
				$form_data .= '<a class="pic fa fa-caret-down moveArrow" href="' . htmlspecialchars($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$form_data .= '<a class="pic fa fa-caret-up moveArrow" href="' . htmlspecialchars($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}
			$form_data .= '</td>';

			$form_data .= '<td class="nowrap" style="width:1%;">
				<a class="pic deleteMarker fa fa-remove" href="' . htmlspecialchars($module . '?action=item_remove&item_id=' . $item['id'] . '&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Delete') . '"></a></td>
			</tr>';

			print $form_data;

			$i++;
		}
	} else {
		print "<tr><td><em>" . __('No Tree Creation Criteria') . "</em></td></tr>\n";
	}

	html_end_box(true);
}


function neighbor_global_item_edit($rule_id, $rule_item_id, $rule_type) {
	global $config, $fields_neighbor_match_rule_item_edit, $fields_neighbor_graph_rule_item_edit;
	global $fields_neighbor_tree_rule_item_edit, $automation_tree_header_types;
	global $automation_op_array;

	switch ($rule_type) {
	case AUTOMATION_RULE_TYPE_GRAPH_MATCH:
		$title = __('Device Match Rule');
		$item_table = 'plugin_neighbor_match_rule_items';
		$sql_and = ' AND rule_type=' . $rule_type;
		$tables = array ('host', 'host_templates');
		$neighbor_rule = db_fetch_row_prepared('SELECT * FROM plugin_neighbor_graph_rules WHERE id = ?', array($rule_id));

		$_fields_rule_item_edit = $fields_neighbor_match_rule_item_edit;
		$query_fields  = get_query_fields('host_template', array('id', 'hash'));
		$query_fields += get_query_fields('host', array('id', 'host_template_id'));

		$_fields_rule_item_edit['field']['array'] = $query_fields;
		$module = 'neighbor_graph_rules.php';

		break;
	case AUTOMATION_RULE_TYPE_GRAPH_ACTION:
		$title      = __('Create Graph Rule');
		$tables     = array(AUTOMATION_RULE_TABLE_XML);
		$item_table = 'plugin_neighbor_graph_rule_items';
		$sql_and    = '';

		$neighbor_rule = db_fetch_row_prepared('SELECT *
			FROM plugin_neighbor_rules
			WHERE id = ?',
			array($rule_id));

		$_fields_rule_item_edit = $fields_neighbor_graph_rule_item_edit;
		$fields = array();
		foreach ($neighbor_options as $opt) {
				$cols = db_get_table_column_types("plugin_neighbor_".$opt);
				foreach ($cols as $col => $rec) {
					if (preg_match("/^id$|_id|_hash|last_seen|_changed/",$col)) { continue;}
					$fields["$opt.$col"] = $opt . " - " . $col;
				}
		}
		$_fields_rule_item_edit['field']['array'] = $fields;
		//sort($_fields_rule_item_edit['field']['array']);
		/*		
		
		$_fields_rule_item_edit = $fields_neighbor_graph_rule_item_edit;
		$fields = array();
		$xml_array = get_data_query_array($neighbor_rule['snmp_query_id']);

		if (sizeof($xml_array['fields'])) {
			foreach($xml_array['fields'] as $key => $value) {
				# ... work on all input fields
				if (isset($value['direction']) && ($value['direction'] == 'input' || $value['direction'] == 'input-output')) {
					$fields[$key] = $key . ' - ' . $value['name'];
				}
			}
			$_fields_rule_item_edit['field']['array'] = $fields;
		}
		*/
		
		$module = 'neighbor_graph_rules.php';

		break;
	case AUTOMATION_RULE_TYPE_TREE_MATCH:
		$item_table = 'plugin_neighbor_match_rule_items';
		$sql_and = ' AND rule_type=' . $rule_type;
		$neighbor_rule = db_fetch_row_prepared('SELECT * FROM plugin_neighbor_tree_rules WHERE id = ?', array($rule_id));
		$_fields_rule_item_edit = $fields_neighbor_match_rule_item_edit;
		$query_fields  = get_query_fields('host_template', array('id', 'hash'));
		$query_fields += get_query_fields('host', array('id', 'host_template_id'));

		if ($neighbor_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
			$title = __('Device Match Rule');
			$tables = array ('host', 'host_templates');
			#print '<pre>'; print_r($query_fields); print '</pre>';
		} elseif ($neighbor_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
			$title = __('Graph Match Rule');
			$tables = array ('host', 'host_templates');
			# add some more filter columns for a GRAPH match
			$query_fields += get_query_fields('graph_templates', array('id', 'hash'));
			$query_fields += array('gtg.title' => 'GTG: title - varchar(255)');
			$query_fields += array('gtg.title_cache' => 'GTG: title_cache - varchar(255)');
			#print '<pre>'; print_r($query_fields); print '</pre>';
		}
		$_fields_rule_item_edit['field']['array'] = $query_fields;
		$module = 'neighbor_tree_rules.php';

		break;
	case AUTOMATION_RULE_TYPE_TREE_ACTION:
		$item_table = 'plugin_neighbor_tree_rule_items';
		$sql_and = '';
		$neighbor_rule = db_fetch_row_prepared('SELECT * FROM plugin_neighbor_tree_rules WHERE id = ?', array($rule_id));

		$_fields_rule_item_edit = $fields_neighbor_tree_rule_item_edit;
		$query_fields  = get_query_fields('host_template', array('id', 'hash'));
		$query_fields += get_query_fields('host', array('id', 'host_template_id'));

		/* list of allowed header types depends on rule leaf_type
		 * e.g. for a Device Rule, only Device-related header types make sense
		 */
		if ($neighbor_rule['leaf_type'] == TREE_ITEM_TYPE_HOST) {
			$title = __('Create Tree Rule (Device)');
			$tables = array ('host', 'host_templates');
			#print '<pre>'; print_r($query_fields); print '</pre>';
		} elseif ($neighbor_rule['leaf_type'] == TREE_ITEM_TYPE_GRAPH) {
			$title = __('Create Tree Rule (Graph)');
			$tables = array ('host', 'host_templates');
			# add some more filter columns for a GRAPH match
			$query_fields += get_query_fields('graph_templates', array('id', 'hash'));
			$query_fields += array('gtg.title' => 'GTG: title - varchar(255)');
			$query_fields += array('gtg.title_cache' => 'GTG: title_cache - varchar(255)');
		}
		$_fields_rule_item_edit['field']['array'] = $query_fields;
		$module = 'neighbor_tree_rules.php';

		break;
	}

	if (!empty($rule_item_id)) {
		$neighbor_item = db_fetch_row("SELECT *
			FROM $item_table
			WHERE id=$rule_item_id
			$sql_and");

		$header_label = __('Rule Item [edit rule item for %s: %s]', $title, $neighbor_rule['name']);
	} else {
		$header_label = __('Rule Item [new rule item for %s: %s]', $title, $neighbor_rule['name']);
		$neighbor_item = array();
		$neighbor_item['sequence'] = get_sequence('', 'sequence', $item_table, 'rule_id=' . $rule_id . $sql_and);
	}

	form_start($module, 'form_neighbor_global_item_edit');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($_fields_rule_item_edit, (isset($neighbor_item) ? $neighbor_item : array()), (isset($neighbor_rule) ? $neighbor_rule : array()))
		)
	);

	html_end_box(true, true);
}


function neighbor_display_matching_hosts($rule, $rule_type, $url) {
	global $device_actions, $item_rows;

	if (isset_request_var('cleard')) {
		set_request_var('clear', 'true');
	}

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rowsd' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'paged' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'host_status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'host_template_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'filterd' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'has_graphs' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'pageset' => true,
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_auto');
	/* ================= input validation ================= */

	if (isset_request_var('cleard')) {
		unset_request_var('clear');
	}

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rowsd') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rowsd');
	}

	if ((!empty($_SESSION['sess_neighbor_host_status'])) && (!isempty_request_var('host_status'))) {
		if ($_SESSION['sess_neighbor_host_status'] != get_request_var('host_status')) {
			set_request_var('paged', '1');
		}
	}

	?>
	<script type='text/javascript'>
	function applyDeviceFilter() {
		strURL  = '<?php print $url;?>' + '&host_status=' + $('#host_status').val();
		strURL += '&host_template_id=' + $('#host_template_id').val();
		strURL += '&rowsd=' + $('#rowsd').val();
		strURL += '&filterd=' + $('#filterd').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function clearDeviceFilter() {
		strURL = '<?php print $url;?>' + '&cleard=true&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#refresh').click(function() {
			applyDeviceFilter();
		});

		$('#clear').click(function() {
			clearDeviceFilter();
		});

		$('#form_neighbor_host').submit(function(event) {
			event.preventDefault();
			applyDeviceFilter();
		});

		setupSpecialKeys('filterd');
	});
	</script>
	<?php

	html_start_box(__('Matching Devices'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form method='post' id='form_neighbor_host' action='<?php print htmlspecialchars($url);?>'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' id='filterd' size='25' value='<?php print html_escape_request_var('filterd');?>'>
						</td>
						<td>
							<?php print __('Type');?>
						</td>
						<td>
							<select id='host_template_id' onChange='applyDeviceFilter()'>
								<option value='-1'<?php if (get_request_var('host_template_id') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<option value='0'<?php if (get_request_var('host_template_id') == '0') {?> selected<?php }?>><?php print __('None');?></option>
								<?php
								$host_templates = db_fetch_assoc('SELECT id,name FROM host_template ORDER BY name');

								if (sizeof($host_templates)) {
									foreach ($host_templates as $host_template) {
										print "<option value='" . $host_template['id'] . "'"; if (get_request_var('host_template_id') == $host_template['id']) { print ' selected'; } print '>' . $host_template['name'] . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Status');?>
						</td>
						<td>
							<select id='host_status' onChange='applyDeviceFilter()'>
								<option value='-1'<?php if (get_request_var('host_status') == '-1') {?> selected<?php }?>><?php print __('Any');?></option>
								<option value='-3'<?php if (get_request_var('host_status') == '-3') {?> selected<?php }?>><?php print __('Enabled');?></option>
								<option value='-2'<?php if (get_request_var('host_status') == '-2') {?> selected<?php }?>><?php print __('Disabled');?></option>
								<option value='-4'<?php if (get_request_var('host_status') == '-4') {?> selected<?php }?>><?php print __('Not Up');?></option>
								<option value='3'<?php if (get_request_var('host_status') == '3') {?> selected<?php }?>><?php print __('Up');?></option>
								<option value='1'<?php if (get_request_var('host_status') == '1') {?> selected<?php }?>><?php print __('Down');?></option>
								<option value='2'<?php if (get_request_var('host_status') == '2') {?> selected<?php }?>><?php print __('Recovering');?></option>
								<option value='0'<?php if (get_request_var('host_status') == '0') {?> selected<?php }?>><?php print __('Unknown');?></option>
							</select>
						</td>
						<td>
							<?php print __('Devices');?>
						</td>
						<td>
							<select id='rowsd' onChange='applyDeviceFilter()'>
								<option value='-1'<?php if (get_request_var('rowsd') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
								if (sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='". $key . "'"; if (get_request_var('rowsd') == $key) { print ' selected'; } print '>' . $value . '</option>\n';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input id='refresh' type='button' value='<?php print __esc('Go');?>'>
								<input id='clear' type='button' value='<?php print __esc('Clear');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* form the 'where' clause for our main sql query using prepared statements */
	$where_conditions = array();
	$where_params = array();
	
	if (get_request_var('filterd') != '') {
		$filter_value = '%' . get_request_var('filterd') . '%';
		$where_conditions[] = "(h.hostname LIKE ? OR h.description LIKE ? OR ht.name LIKE ?)";
		array_push($where_params, $filter_value, $filter_value, $filter_value);
	}

	if (get_request_var('host_status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_status') == '-2') {
		$where_conditions[] = "h.disabled='on'";
	} elseif (get_request_var('host_status') == '-3') {
		$where_conditions[] = "h.disabled=''";
	} elseif (get_request_var('host_status') == '-4') {
		$where_conditions[] = "(h.status!='3' OR h.disabled='on')";
	} else {
		$where_conditions[] = "(h.status = ? AND h.disabled = '')";
		$where_params[] = get_request_var('host_status');
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_template_id') == '0') {
		$where_conditions[] = 'h.host_template_id=0';
	} elseif (!isempty_request_var('host_template_id')) {
		$where_conditions[] = 'h.host_template_id = ?';
		$where_params[] = get_request_var('host_template_id');
	}

	$sql_where = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

	$host_graphs       = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id'), 'host_id', 'graphs');
	$host_data_sources = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id'), 'host_id', 'data_sources');

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query = 'SELECT h.id AS host_id, h.hostname, h.description, h.disabled,
		h.status, ht.name AS host_template_name
		FROM host AS h
		LEFT JOIN host_template AS ht
		ON (h.host_template_id=ht.id) ';

	/* get the WHERE clause for matching hosts */
	if ($sql_where != '') {
		$sql_filter = ' AND (' . neighbor_build_matching_objects_filter($rule['id'], $rule_type) . ')';
	} else {
		$sql_filter = ' WHERE (' . neighbor_build_matching_objects_filter($rule['id'], $rule_type) .')';
	}

	/* now we build up a new query for counting the rows */
	$rows_query = $sql_query . $sql_where . $sql_filter;
	
	if (count($where_params) > 0) {
		$total_rows = count((array) db_fetch_assoc_prepared($rows_query, $where_params));
	} else {
		$total_rows = count((array) db_fetch_assoc($rows_query, false));
	}

	$sortby = get_request_var('sort_column');
	if ($sortby=='hostname') {
		$sortby = 'INET_ATON(hostname)';
	}

	$sql_query = $rows_query .
		' ORDER BY ' . $sortby . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . ($rows*(get_request_var('paged')-1)) . ',' . $rows;
	
	if (count($where_params) > 0) {
		$hosts = db_fetch_assoc_prepared($sql_query, $where_params);
	} else {
		$hosts = db_fetch_assoc($sql_query, false);
	}

	$nav = html_nav_bar($url, MAX_DISPLAY_PAGES, get_request_var('paged'), $rows, $total_rows, 7, 'Devices', 'paged', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'description'        => array(__('Description'), 'ASC'),
		'hostname'           => array(__('Hostname'), 'ASC'),
		'status'             => array(__('Status'), 'ASC'),
		'host_template_name' => array(__('Device Template Name'), 'ASC'),
		'id'                 => array(__('ID'), 'ASC'),
		'nosort1'            => array(__('Graphs'), 'ASC'),
		'nosort2'            => array(__('Data Sources'), 'ASC'),
	);

	html_header_sort(
		$display_text,
		get_request_var('sort_column'),
		get_request_var('sort_direction'),
		'1',
		$url . '?action=edit&id=' . get_request_var('id') . '&paged=' . get_request_var('paged')
	);

	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			form_alternate_row('line' . $host['host_id'], true);
			form_selectable_cell(filter_value($host['description'], get_request_var('filterd'), 'host.php?action=edit&id=' . $host['host_id']), $host['host_id']);
			form_selectable_cell(filter_value($host['hostname'], get_request_var('filterd')), $host['host_id']);
			form_selectable_cell(get_colored_device_status(($host['disabled'] == 'on' ? true : false), $host['status']), $host['host_id']);
			form_selectable_cell(filter_value($host['host_template_name'], get_request_var('filterd')), $host['host_id']);
			form_selectable_cell(round(($host['host_id']), 2), $host['host_id']);
			form_selectable_cell((isset($host_graphs[$host['host_id']]) ? $host_graphs[$host['host_id']] : 0), $host['host_id']);
			form_selectable_cell((isset($host_data_sources[$host['host_id']]) ? $host_data_sources[$host['host_id']] : 0), $host['host_id']);
			form_end_row();
		}
	} else {
		print "<tr><td colspan='8'><em>" . __('No Matching Devices') . "</em></td></tr>";
	}

	html_end_box(false);

	if (sizeof($hosts)) {
		print $nav;
	}

	form_end();
}


function neighbor_display_match_rule_items($title, $rule_id, $rule_type, $module) {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$items = db_fetch_assoc_prepared('SELECT *
		FROM plugin_neighbor_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?
		ORDER BY sequence',
		array($rule_id, $rule_type));

	html_start_box($title, '100%', '', '3', 'center', $module . '?action=item_edit&id=' . $rule_id . '&rule_type=' . $rule_type);

	$display_text = array(
		array('display' => __('Item'),      'align' => 'left'),
		array('display' => __('Sequence'),  'align' => 'left'),
		array('display' => __('Operation'), 'align' => 'left'),
		array('display' => __('Field'),     'align' => 'left'),
		array('display' => __('Operator'),  'align' => 'left'),
		array('display' => __('Pattern'),   'align' => 'left'),
		array('display' => __('Actions'),   'align' => 'right')
	);

	html_header($display_text, 2);

	$i = 0;
	if (sizeof($items)) {
		foreach ($items as $item) {
			$operation = ($item['operation'] != 0) ? $automation_oper[$item['operation']] : '&nbsp;';

			form_alternate_row();
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars($module . '?action=item_edit&id=' . $rule_id. '&item_id=' . $item['id'] . '&rule_type=' . $rule_type) . '">Item#' . ($i+1) . '</a></td>';
			$form_data .= '<td>' . 	$item['sequence'] . '</td>';
			$form_data .= '<td>' . 	$operation . '</td>';
			$form_data .= '<td>' . 	$item['field'] . '</td>';
			$form_data .= '<td>' . 	((isset($item['operator']) && $item['operator'] > 0) ? $automation_op_array['display'][$item['operator']] : '') . '</td>';
			$form_data .= '<td>' . 	$item['pattern'] . '</td>';

			$form_data .= '<td class="right nowrap">';

			if ($i != sizeof($items)-1) {
				$form_data .= '<a class="pic fa fa-caret-down moveArrow" href="' . htmlspecialchars($module . '?action=item_movedown&item_id=' . $item['id'] . '&id=' . $rule_id . '&rule_type=' . $rule_type) . '" title="' . __esc('Move Down') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}

			if ($i > 0) {
				$form_data .= '<a class="pic fa fa-caret-up moveArrow" href="' . htmlspecialchars($module . '?action=item_moveup&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Move Up') . '"></a>';
			} else {
				$form_data .= '<span class="moveArrowNone"></span>';
			}
			$form_data .= '</td>';

			$form_data .= '<td style="width:1%;">
				<a class="pid deleteMarker fa fa-remove" href="' . htmlspecialchars($module . '?action=item_remove&item_id=' . $item['id'] .	'&id=' . $rule_id .	'&rule_type=' . $rule_type) . '" title="' . __esc('Delete') . '"></a></td>
			</tr>';

			print $form_data;

			$i++;
		}
	} else {
		print "<tr><td colspan='8'><em>" . __('No Device Selection Criteria') . "</em></td></tr>\n";
	}

	html_end_box(true);
}

function neighbor_build_matching_objects_filter($rule_id, $rule_type) {
	cacti_log(__FUNCTION__ . " called rule id: $rule_id", false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	$sql_filter = '';

	/* create an SQL which queries all host related tables in a huge join
	 * this way, we may add any where clause that might be added via
	 *  'Matching Device' match
	 */
	$rule_items = db_fetch_assoc_prepared('SELECT *
		FROM plugin_neighbor_match_rule_items
		WHERE rule_id = ?
		AND rule_type = ?
		ORDER BY sequence',
		array($rule_id, $rule_type));

	#print '<pre>Items: $sql<br>'; print_r($rule_items); print '</pre>';

	if (sizeof($rule_items)) {
		$sql_filter	= neighbor_build_rule_item_filter($rule_items);
	} else {
		/* force empty result set if no host matching rule item present */
		$sql_filter = ' (1 != 1)';
	}

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_filter, false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_filter;
}

function neighbor_build_rule_item_filter($automation_rule_items, $prefix = '') {
	global $automation_op_array, $automation_oper;

	cacti_log(__FUNCTION__ . ' called: ' . serialize($automation_rule_items) . ", prefix: $prefix", false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	$sql_filter = '';
	if (sizeof($automation_rule_items)) {
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

/*
 * build_sort_order
 * @arg $index_order	sort order given by e.g. xml_array[index_order_type]
 * @arg $default_order	default order if any
 * return				sql sort order string
 */
function neighbor_build_sort_order($index_order, $default_order = '') {
	cacti_log(__FUNCTION__ . " called: $index_order/$default_order", false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	$sql_order = $default_order;

	/* determine the sort order */
	/*
	if (isset($index_order)) {
		if ($index_order == 'numeric') {
			$sql_order .= ', CAST(snmp_index AS unsigned)';
		}else if ($index_order == 'alphabetic') {
			$sql_order .= ', snmp_index';
		}else if ($index_order == 'natural') {
			$sql_order .= ', INET_ATON(snmp_index)';
		}
	}
	*/
	/* if ANY order is requested */
	if ($sql_order != '') {
		$sql_order = 'ORDER BY ' . $sql_order;
	}

	cacti_log(__FUNCTION__ . " returns: $sql_order", false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_order;
}

function neighbor_display_new_graphs($rule, $url) {
	
	global $config, $item_rows, $config;
	global $neighbor_interface_new_graph_fields;
	
	if (isset_request_var('oclear')) { set_request_var('clear', 'true'); }

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'description',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_autog');
	/* ================= input validation ================= */

	if (isset_request_var('oclear')) {
		unset_request_var('clear');
	}

	/* if the number of rows is -1, set it to the default */
	if (get_request_var('rows') == -1) { $rows = read_config_option('num_rows_table'); }
	else { $rows = get_request_var('rows'); }

	?>
		<script type='text/javascript'>
		function applyObjectFilter() {
			strURL  = '<?php print $url;?>';
			strURL += '&rows=' + $('#rows').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&header=false';
			loadPageNoHeader(strURL);
		}
	
		function clearObjectFilter() {
			strURL = '<?php print $url;?>' + '&oclear=true&header=false';
			loadPageNoHeader(strURL);
		}
	
		$(function() {
			$('#orefresh').click(function() {
				applyObjectFilter();
			});
	
			$('#oclear').click(function() {
				clearObjectFilter();
			});
	
			$('#form_automation_objects').submit(function(event) {
				event.preventDefault();
				applyObjectFilter();
			});
		});
		</script>
	<?php

	html_start_box(__('Matching Objects'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form_automation_objects' action='<?php print htmlspecialchars($url);?>'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Search');?>
						</td>
						<td>
							<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
						</td>
						<td>
							<?php print __('Objects');?>
						</td>
						<td>
							<select id='rows' onChange='applyFilter()'>
								<option value='-1'<?php if (get_request_var('rows') == '-1') {?> selected<?php }?>><?php print __('Default');?></option>
								<?php
								if (sizeof($item_rows)) {
									foreach ($item_rows as $key => $value) {
										print "<option value='". $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . '</option>\n';
									}
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input id='orefresh' type='button' value='<?php print __esc('Go');?>'>
								<input id='oclear' type='button' value='<?php print __esc('Clear');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
		</td>
	</tr>
	<?php

	html_end_box();

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('snmp_query_id');
	/* ==================================================== */

	$total_rows         = 0;
	$num_input_fields   = 0;
	$num_visible_fields = 0;

	$name = isset($rule['neighbor_type']) ? $rule['neighbor_type'] : 'Neighbor';
	$total_rows = isset($total_rows) ? $total_rows : 0;

	$sort_column = get_request_var('sort_column') ? get_request_var('sort_column') : '';
	$sort_direction = get_request_var('sort_direction') ? get_request_var('sort_direction') : 'ASC';
	$rule_id = isset($rule['id']) ? $rule['id'] : '';
	
	html_start_box(__('Matching Objects [ %s ]', htmlspecialchars($name, ENT_QUOTES)) . display_tooltip(__('A blue font color indicates that the rule will be applied to the objects in question.  Other objects will not be subject to the rule.')), '100%', '', '3', 'center', '');

		$html_dq_header     = '';
		$sql_filter         = '';
		$sql_having         = '';
		$neighbor_objects = array();

		$sql_order = "";
		
		$rule_options = isset($rule['neighbor_options']) ? $rule['neighbor_options'] : '';
		if($rule_options && $sort_column && !($sort_column == 'type' || $sort_column == 'interface_status')) {
			$sql_order = "ORDER by $sort_column $sort_direction";
		}
		elseif($rule_options && $sort_column && ($sort_column == 'type' || $sort_column == 'interface_status')) {
			$sql_order = "ORDER by $sort_column $sort_direction";
		}
		
		if ($sql_order) {
			$sql_query = neighbor_build_data_query_sql($rule) . ' ' . $sql_order;
		}
		else {
			$sql_query = neighbor_build_data_query_sql($rule);
		}
		
		$start_rec = $rows*(get_request_var('page')-1);
		$all_neighbor_objects = db_fetch_assoc($sql_query);
		$all_neighbor_objects = dedup_by_hash($all_neighbor_objects);
		$total_rows = count((array) $all_neighbor_objects);
		$neighbor_objects = array_slice($all_neighbor_objects,$start_rec,$rows);
		
		// Get heading text
		
		$nav = html_nav_bar('neighbor_rules.php?action=edit&id=' . $rule['id'], MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 30, __('Matching Objects'), 'page', 'main');
		print $nav;

		$display_text = array();
		$field_names = array();
		foreach ($neighbor_interface_new_graph_fields as $field => $title) {
			$display_text[$field][0] = $title;
			$display_text[$field][1] = "ASC";
			$field_names[] = $field;
		}

		html_header_sort($display_text,$sort_column,$sort_direction,'',$config['url_path']."plugins/neighbor/neighbor_rules.php?action=edit&id=$rule_id");

		if (!sizeof($neighbor_objects)) {
			print "<tr colspan='6'><td>" . __('There are no Objects that match this rule.') . "</td></tr>\n";
		}
		else {
			print "<tr colspan='6'>" . $html_dq_header . "</tr>\n";
		}

		/* list of all entries */
		$row_counter    = 0;
		$column_counter = 0;
		
		foreach ($neighbor_objects as $row) {
			form_alternate_row("line$row_counter", true);
			$style = ' ';
			foreach ($field_names as $field_name) {
				
				if (isset($row[$field_name])) {
					if ($field_name == 'status') {
						form_selectable_cell(get_colored_device_status(($row['disabled'] == 'on' ? true : false), $row['status']), 'status');
					}
					else {
						print "<td><span id='text$row_counter" . '_' . $column_counter . "' $style>" . filter_value($row[$field_name], get_request_var('filter')) . "</span></td>";
					}
				}
				else {
					print "<td><span id='text$row_counter" . '_' . $column_counter . "' $style></span></td>";
				}
				$column_counter++;
			}
			print "</tr>\n";
			$row_counter++;
		}

	print '</table>';
	print '<br>';
}

function neighbor_rule_to_json($rule_id) { 

	$rule_id = $rule_id ? $rule_id : (isset_request_var('rule_id') ? get_request_var('rule_id') 	: '');
	$ajax    = isset_request_var('ajax')    ? get_request_var('ajax') 		: '';
	$format  = isset_request_var('format')  ? get_request_var('format') 	: 'json';
	$rule = db_fetch_row_prepared('SELECT * FROM plugin_neighbor_rules WHERE id = ?',array($rule_id));
	$sql_query = neighbor_build_data_query_sql($rule);
		
	$neighbor_objects = db_fetch_assoc($sql_query);
	$json = json_encode($neighbor_objects,JSON_PRETTY_PRINT);
	
	if ($ajax) 	{
		header('Content-Type: application/json');
		$callback = get_request_var('callback', 'Callback');
		print $format == 'jsonp' ? $callback . '(' . $json . ')' : $json;
	}
	else {
		return($json);
	}
}



function ajax_interface_nodes($rule_id = '', $ajax = true, $format = 'jsonp') {
	
	$rule_id = $rule_id 					? $rule_id  				: (isset_request_var('rule_id') ? get_request_var('rule_id') 	: '');
	$ajax 	= isset_request_var('ajax') 	? get_request_var('ajax') 	: $ajax;
	$format = isset_request_var('format') 	? get_request_var('format') : $format;
	$last_seen = isset_request_var('last_seen') ? get_request_var('last_seen') : "";
	$host_filter = isset_request_var('host_filter') ? get_request_var('host_filter') : "";
	$edge_filter = isset_request_var('edge_filter') ? get_request_var('edge_filter') : "";
	$host_filter = "";
		
	$rule = db_fetch_row_prepared('SELECT * FROM plugin_neighbor_rules WHERE id = ?',array($rule_id));
	$sql_query = neighbor_build_data_query_sql($rule,$host_filter,$edge_filter);
	$results = db_fetch_assoc($sql_query);
	$neighbor_objects = db_fetch_hash($results,array('hostname','neighbor_hostname','interface_name'));			 // Organise the results into a tree
	
	// Get the unique hosts from the results so that we can query the site data for GPS.
	$hosts_arr = array();
	foreach ($neighbor_objects as $h1 => $rec1) {
		foreach ($rec1 as $h2 => $rec2 ) {
			foreach ($rec2 as $interface => $rec3) {
				$hosts_arr[$rec3['host_id']] = 1;
				$hosts_arr[$rec3['neighbor_host_id']] = 1;
			}
		}
 	}
	
	$hosts_arr = array_keys($hosts_arr);
	$sites = get_site_coords($hosts_arr);
	
	// Create the visjs nodes array
	$nodes = [];
	$projected = [];
	$data = [];
	
	// We need the resolution to map the coords from earth to box
	$res_x = isset_request_var('res_x') ? get_request_var('res_x') 	: 1280;
	$res_y = isset_request_var('res_y') ? get_request_var('res_y') 	: 1080;
	
	$min_x = -1;
	$min_y = -1;

	// First check if we have a stored map for this user
	
	$user_id = isset($_SESSION['sess_user_id']) ? $_SESSION['sess_user_id'] : 0;
	error_log("Looking for saved map positions for user: $user_id, map: $rule_id");
	$stored_map = array();
	$has_user_map_table = db_fetch_cell("SHOW TABLES LIKE 'plugin_neighbor_user_map'");
	if ($has_user_map_table) {
		$stored_map = db_fetch_assoc_prepared("SELECT * from plugin_neighbor_user_map where user_id=? AND rule_id=?",array($user_id,$rule_id));
	}

	if (!is_array($stored_map)) {
		$stored_map = array();
	}

	if (count($stored_map)) {

			foreach ($stored_map as $row) {
				// error_log("Using saved coordinates:".print_r($row,true));		
				$projected[] = array(
					'id' 	=> $row['item_id'],
					'label' => $row['item_label'],
					'x'		=> $row['item_x'],
					'y'		=> $row['item_y'],
					'mass'	=> 2,
					'physics' => false
				);
				$data['seed'] = (int) $row['random_seed'];
			}
			$data['physics'] = true;
			//error_log("Nodes[]: ".print_r($projected,true));
	}
	else {
	
		foreach ($hosts_arr as $host_id ) {
				
				$label = isset($sites[$host_id]['description']) ? $sites[$host_id]['description'] 	: "";
				$lat = isset($sites[$host_id]['latitude']) ? $sites[$host_id]['latitude'] : "";
				$lng = isset($sites[$host_id]['longitude']) ? $sites[$host_id]['longitude'] : "";
				
				$screen_coords = degrees_to_screen($lat,$lng,$res_x,$res_y);
				$x = $screen_coords['x'];
				$y = $screen_coords['y'];
				$min_x = $min_x == -1 ? $x : min($min_x,$x);
				$min_y = $min_y == -1 ? $y : min($min_y,$y);
				error_log("$label: $res_x,$res_y => $lat,$lng => $x, $y");
	
				$mass = preg_match("/PTN|HLC/",$label) ? 5 : 1;
				$nodes[] = array(
					'id' 	=> $host_id,
					'label' => $label,
					'x'		=> $x,
					'y'		=> $y,
					//'mass'	=> $mass,
				);
				
				// Now we need to project these onto the screen resolution surface to get a more natural looking map
		}
		
		$projected = project_nodes($nodes,$res_x,$res_y,0);
	}
	
	// Create the edges next
	$edges = array();
	$edge_data = get_edges_poller($rule_id);
	$seen = [];
	$interface_data_template_id = get_data_template("Interface - Traffic");
	$interface_graph_template_id = get_graph_template("Interface - Traffic (bits/sec)");
	foreach ($neighbor_objects as $h1 => $rec1) {
		foreach ($rec1 as $h2 => $rec2 ) {
			foreach ($rec2 as $interface => $rec3) {
				
				$neighbor_hash = isset($rec3['neighbor_hash']) ? $rec3['neighbor_hash'] : "";
				if (isset($seen[$neighbor_hash])) { continue; }	// We only want one edge per pair
				$seen[$neighbor_hash] = 1;
				
				$from = $rec3['host_id'];
				$to = $rec3['neighbor_host_id'];
				$site_a = $sites[$from];
				$site_b = $sites[$to];
				$coords_a = sprintf("%s,%s",$site_a['latitude'],$site_a['longitude']);
				$coords_b = sprintf("%s,%s",$site_b['latitude'],$site_b['longitude']);
				$length = get_distance($coords_a,$coords_b) /1000;
				if ($length < 15) { $length = $length * 1.5;}
				//$label = get_speed_label($rec3['interface_speed']) . " (".sprintf("%.1f",$length)."km)";
				$label = get_speed_label($rec3['interface_speed']);
				$title = sprintf("%s - %s to %s - %s", $rec3['hostname'], $rec3['interface_name'], $rec3['neighbor_hostname'],$rec3['neighbor_interface_name']);
				
				//$value_scaled = log_scale($rec3['interface_speed']);
				$rrd_file = get_rra_file($from,$rec3['snmp_id'],$interface_data_template_id);
				
				$graph_local_id = get_interface_graph_local($from,$rec3['snmp_id'],$interface_graph_template_id);
				$rec3['graph_local_id'] = $graph_local_id;
				
				$poller_json = isset($edge_data[$from][$to][$rrd_file]) ? $edge_data[$from][$to][$rrd_file] : "{}";
				// Store the edge
				$edges[] = array(
					'from' 	=> $from,
					'to'	=> $to,
					'label'	=> $label,
					'title'	=> $title,
					'smooth'=> true,
					'poller' => $poller_json,
					'rrd_file'=> $rrd_file,
					'graph_id'=> $graph_local_id,
					'value'	=> $rec3['interface_speed'],
					'last_seen' => $rec3['last_seen']
					//'length'=> $length
				);
			}
		}
 	}
	// We need to store the edges into a DB so we can integrate the poller_output values, RRD files etc. etc.
	update_edges_db($rule_id,$edges);

	$query_callback = get_request_var('callback', 'Callback');
	$data['nodes'] = $projected;
	$data['edges'] = $edges;
	
	$jsonp = sprintf("%s({\"Response\":[%s]})", $query_callback,json_encode($data,JSON_PRETTY_PRINT));	
	$json  = json_encode($data, JSON_PRETTY_PRINT);
	
	if ($ajax) 	{
		header('Content-Type: application/json');
		print $format == 'jsonp' ? $jsonp : $json;
	}
	else {
		return($json);
	}
}

// Update the plugin_neighbor_edge table

function update_edges_db($rule_id,$edges) {
	db_execute_prepared("DELETE FROM plugin_neighbor_edge where rule_id = ? and edge_updated < DATE_SUB(NOW(), INTERVAL 1 DAY)",array(1));
	foreach ($edges as $edge) {
		$edge_json = json_encode($edge);
		db_execute_prepared("REPLACE INTO plugin_neighbor_edge (rule_id,from_id,to_id,rrd_file,edge_json,edge_updated)
							 VALUES (?,?,?,?,?,NOW())",
							 array($rule_id,$edge['from'],$edge['to'],$edge['rrd_file'],$edge_json));
	}
}

// Fetch the latest poller results from plugin_neighbor_edge
function get_edges_poller($rule_id) {
	
	$results = db_fetch_assoc_prepared("SELECT * from plugin_neighbor_edge
									    LEFT JOIN plugin_neighbor_poller_delta on plugin_neighbor_edge.rrd_file = plugin_neighbor_poller_delta.rrd_file
										WHERE plugin_neighbor_edge.rule_id =?",array($rule_id));
	$hash = db_fetch_hash($results,array('from_id','to_id','rrd_file','key_name'));
	return($hash);
}



function get_interface_graph_local($host_id,$snmp_id,$graph_template_id) {

	$graph_local_id = db_fetch_cell_prepared("SELECT graph_templates_graph.local_graph_id as id
											 FROM (graph_local,graph_templates_graph)
											 LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id)
											 WHERE graph_local.id=graph_templates_graph.local_graph_id
											 AND graph_local.host_id = ?
											 AND graph_local.snmp_index = ?
											 AND graph_local.graph_template_id = ?",
											 array($host_id,$snmp_id,$graph_template_id));

	return($graph_local_id);
	
}


function get_rra_file($host_id,$snmp_id,$graph_template_id) {

	$rra_file = db_fetch_cell_prepared("SELECT data_template_data.data_source_path
										FROM data_template_data
										LEFT JOIN data_local ON data_local.id = data_template_data.local_data_id
										WHERE data_local.host_id = ?
										AND data_local.snmp_index = ?
										AND data_local.data_template_id =?",
										array($host_id,$snmp_id,$graph_template_id));

	return($rra_file);	
}


// Get the ID of a graph template
function get_graph_template($name) {
	$template_id = db_fetch_cell_prepared("SELECT id from graph_templates where name = ?", array($name));
	return($template_id);	
}

// Get the ID of a graph template
function get_data_template($name) {
	$template_id = db_fetch_cell_prepared("SELECT id from data_template where name = ?", array($name));
	return($template_id);	
}


function log_scale($value, $min = 1, $max = 4, $min_v = 1, $max_v = 4) {
	
	$log_min_v = log($min_v);
	$log_max_v = log($max_v);
	
	$scale = ($log_max_v-$log_min_v) / ($max-$min);
	return((log($value)-$log_min_v) / $scale + $min);
}

function display_interface_map($rule_id = 1) {
	
	$rule_id = $rule_id ? $rule_id  : (isset_request_var('rule_id') ? get_request_var('rule_id') 	: '');
	$user_id = isset($_SESSION['sess_user_id']) ? $_SESSION['sess_user_id'] : 0;
	
	// Toolbar with map options
	print "<div id='neighbor_map_toolbar'></div>\n";
	// Load the visjs JS libraries
	printf("<link href='%s' rel='stylesheet'>",'js/visjs/vis.min.css');
	printf("<script type='text/javascript' src='%s'></script>",'js/visjs/vis.min.js');
	printf("<script type='text/javascript' src='%s'></script>",'js/moment.min.js');
	printf("<script type='text/javascript' src='%s'></script>",'js/map.js');
	
	// Print the div to hold the map in
	print "<form><input type='hidden' id='rule_id' name='rule_id' value='$rule_id'></form>\n";
	print "<form><input type='hidden' id='user_id' name='user_id' value='$user_id'></form>\n";
	print "<div id='map_container' style='width:100%; height:95%'></div>\n";
	
}


function get_nodes_min($nodes) {
	
	$min_x = -1;
	$min_y = -1;
	
	foreach ($nodes as $node) {
		
		$x = $node['x'];
		$y = $node['y'];
		$min_x = $min_x == -1 ? $x : min($min_x,$x);
		$min_y = $min_y == -1 ? $y : min($min_y,$y);
	}
	return(array($min_x,$min_y));	
}

// Taken from solution on remapping coordinates by limc on SO
// https://stackoverflow.com/a/14330009

function project_nodes($nodes,$res_x,$res_y,$rotate_degrees = 0, $flip_x = 0, $flip_y = 0) {
	
	// error_log("Projecting nodes to $res_x,$res_y");
	// error_log("Projecting Nodes:".print_r($nodes,true));
	
	
	$max_x = -1;
	$max_y = -1;
	$padding = 50;
	
	// Get the $min_x and $min_y values from $nodes
	
	list($min_x,$min_y) = get_nodes_min($nodes);
	error_log("Min_x: $min_x, Min_y: $min_y");
	
	// Readjust values to the min boundary
	
	foreach ($nodes as &$node) {
		
		$node['x'] = $node['x'] - $min_x;
		$node['y'] = $node['y'] - $min_y;
		
		$max_x = $max_x == -1 ? $node['x'] : max($max_x,$node['x']);
		$max_y = $max_y == -1 ? $node['y'] : max($max_y,$node['y']);
		unset($node);	// destroy lingering reference in PHP
	}

	$map_width = $res_x - ($padding *2);		// Put in 50px padding on both sides
	$map_height = $res_y - ($padding *2);		// Put in 50px padding on both sides
	
	$map_width_ratio = $map_width / $max_x;
	$map_height_ratio = $map_height / $max_y;
	
	$global_ratio = min($map_width_ratio,$map_height_ratio);
	
	$width_padding  = ($res_x - ($global_ratio * $max_x)) / 2;
	$height_padding = ($res_y - ($global_ratio * $max_y)) / 2;
	
	foreach ($nodes as &$node) {
		
		$node['x'] = (int) ($padding + $width_padding + ($node['x'] * $global_ratio));
		$node['y'] = (int) ($res_y - $padding - $height_padding - ($node['y'] * $global_ratio));
		
		// Rotate Points
		
		if ($rotate_degrees) {
			$rotate_radians = deg2rad($rotate_degrees);
			$cos = cos($rotate_radians);
			$sin = sin($rotate_radians);
			$x = $node['x'] * $cos - $node['y'] * $sin;
			$y = $node['x'] * $sin + $node['y'] * $cos;
			
			$node['x'] = (int) ($x/4) ;
			$node['y'] = (int) ($y/4);
		}
		if ($flip_x) { $node['x'] = ($node['x'] * -1) + $res_x; }													// Flip on X axis
		if ($flip_y) { $node['y'] = ($node['y'] * -1) + $res_y;	}													// Flip on Y axis
		
		$max_x = $max_x == -1 ? $node['x'] : max($max_x,$node['x']);
		$max_y = $max_y == -1 ? $node['y'] : max($max_y,$node['y']);
		unset($node);	// destroy lingering reference in PHP
	}
		
	return($nodes);
}

function degrees_to_screen($lat,$lng, $width,$height) {
	
	    $lat = doubleval( $lat );
        $lng = doubleval( $lng );
        $width = intval( $width );
        $height = intval( $height );
	
		return array(
           'x' => ($lng+180)*($width/360),
           'y' => ($height/2)-($width*log(tan((M_PI/4)+(($lat*M_PI/180)/2)))/(2*M_PI))
        );
	
}

function get_site_coords($host_arr = array()) {
	
	$sql_query = "SELECT h.id, h.description, h.hostname, h.site_id, s.name, s.address1, s.address2, s.latitude, s.longitude
					FROM host h
					LEFT JOIN sites s ON s.id = h.site_id";
	if (is_array($host_arr) && sizeof($host_arr)) {
		$host_arr = array_filter(array_map('intval', $host_arr), function($id) { return $id > 0; });
		if (sizeof($host_arr)) {
			$sql_query.= " WHERE h.id IN (".implode(",",$host_arr).")";				// For very large installations it may be better to pass an array of hosts to filter by
		}
	}
	$results = db_fetch_assoc($sql_query);
	$sites = db_fetch_hash($results,array('id'));
	return($sites);	
}

function get_distance($a,$b) {

        list($lat1,$lon1) = explode(",",$a);
        list($lat2,$lon2) = explode(",",$b);

        $p = pi() / 180;
        $calc = 0.5 - cos(($lat2 - $lat1) * $p)/2 + cos($lat1 * $p) * cos($lat2 * $p) * (1 - cos(($lon2 - $lon1) * $p))/2;
        $distance = sprintf("%.2d",(12742 * asin(sqrt($calc)) * 1000));
        return($distance);
}

function get_speed_label($speed) {
	
	switch($speed) {
		case 10:
			return "10M";
			break;
		case 100:
			return "100M";
			break;
		case 1000:
			return "1G";
			break;
		case 10000:
			return "10G";
			break;
		case 40000:
			return "40G";
			break;
		case 100000:
			return "100G";
			break;
	}
	
}


function dedup_by_hash($neighbor_objects) {
	
	$seen = array();
	$dedup = array();
	$neighbor_objects = is_array($neighbor_objects) ? $neighbor_objects : array();
	
	foreach ($neighbor_objects as $rec) {
		$neighbor_hash = isset($rec['neighbor_hash']) ? $rec['neighbor_hash'] : "";
		$neighbor_type = isset($rec['type']) ? $rec['type'] : "";
		if (isset($seen[$neighbor_hash])) { continue;}
		if (!$neighbor_type) { continue;}
		$seen[$neighbor_hash]=1;
		$dedup[] = $rec;
	}
	return($dedup);
}

function neighbor_build_data_query_sql($rule,$host_filter,$edge_filter) {
	cacti_log(__FUNCTION__ . ' called: ' . serialize($rule), false, 'NEIGHBOR TRACE', POLLER_VERBOSITY_HIGH);
	
	$sql_query = 'SELECT h.description AS automation_host, h.disabled, h.status ';
	$neighbor_options = isset($rule['neighbor_options']) ? explode(",", $rule['neighbor_options']) : array();
	$neighbor_options = array_filter(array_map('trim', $neighbor_options));

	if (!count($neighbor_options)) {
		$neighbor_options = array('xdp');
	}
		
	$tables = array();
	$table_join = array();
	foreach ($neighbor_options as $opt) {
		if (!preg_match('/^[a-z0-9_]+$/i', $opt)) {
			continue;
		}

		$table = "plugin_neighbor_".$opt;
		array_push($table_join,"LEFT JOIN $table $opt ON $opt.host_id=h.id");
		array_push($tables,"$table as $opt");
		$cols = db_get_table_column_types($table);
		if (!is_array($cols) || !count($cols)) {
			continue;
		}
		foreach ($cols as $col => $rec) {
			$sql_query .= ", $opt.$col";
		}
	}

	/* take matching hosts into account */
	$rule_id = isset($rule['id']) ? $rule['id'] : '';
	$sql_where_combined = array();
	$sql_where = trim(neighbor_build_matching_objects_filter($rule_id, AUTOMATION_RULE_TYPE_GRAPH_MATCH));
	$sql_where2 = trim(neighbor_build_graph_rule_item_filter($rule_id));

	if ($sql_where !== '') {
		$sql_where_combined[] = "($sql_where)";
	}

	if ($sql_where2 !== '') {
		$sql_where_combined[] = "($sql_where2)";
	}

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

function neighbor_build_graph_rule_item_filter($rule_id, $prefix = '') {
	
	global $automation_op_array, $automation_oper;
	$sql_filter = '';
	
	if ($rule_id) {
		
		$graph_rule_items = db_fetch_assoc_prepared("SELECT * from plugin_neighbor_graph_rule_items where rule_id=?",array($rule_id));
	
		if (sizeof($graph_rule_items)) {
			$sql_filter = ' ';
	
			foreach($graph_rule_items as $graph_rule_item) {
				# AND|OR|(|)
				if ($graph_rule_item['operation'] != AUTOMATION_OPER_NULL) {
					$sql_filter .= ' ' . $automation_oper[$graph_rule_item['operation']];
				}
	
				# right bracket ')' does not come with a field
				if ($graph_rule_item['operation'] == AUTOMATION_OPER_RIGHT_BRACKET) {
					continue;
				}
	
				# field name
				if ($graph_rule_item['field'] != '') {
					
					$sql_filter .= (' ' . $prefix . '`' . implode('`.`', explode('.', $graph_rule_item['field'])) . '`');
					#
					$sql_filter .= ' ' . $automation_op_array['op'][$graph_rule_item['operator']] . ' ';
					if ($automation_op_array['binary'][$graph_rule_item['operator']]) {
						$sql_filter .= (db_qstr($automation_op_array['pre'][$graph_rule_item['operator']] . $graph_rule_item['pattern'] . $automation_op_array['post'][$graph_rule_item['operator']]));
					}
				}
			}
		}
	}

	cacti_log(__FUNCTION__ . ' returns: ' . $sql_filter, false, 'AUTOM8 TRACE', POLLER_VERBOSITY_HIGH);

	return $sql_filter;
}



?>
