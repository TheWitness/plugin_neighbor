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

chdir('../../');
include('./include/auth.php');
include_once('./lib/data_query.php');
// include_once('./plugins/neighbor/lib/neighbor_functions.php');
include_once($config['base_path'] . '/plugins/neighbor/lib/neighbor_functions.php');

$neighbor_rules_actions = [
	AUTOMATION_ACTION_GRAPH_DUPLICATE => __('Duplicate'),
	AUTOMATION_ACTION_GRAPH_ENABLE    => __('Enable'),
	AUTOMATION_ACTION_GRAPH_DISABLE   => __('Disable'),
	AUTOMATION_ACTION_GRAPH_DELETE    => __('Delete'),
];

// set default action
set_default_action();

switch (get_request_var('action')) {
	case 'save':
		save_rule();

		break;
	case 'actions':
		neighbor_rules_form_actions();

		break;
	case 'item_movedown':
		neighbor_rules_item_movedown();
		header('Location: neighbor_rules.php?action=edit&id=' . get_filter_request_var('id'));

		break;
	case 'item_moveup':
		neighbor_rules_item_moveup();
		header('Location: neighbor_rules.php?action=edit&id=' . get_filter_request_var('id'));

		break;
	case 'item_remove':
		neighbor_rules_item_remove();
		header('Location: neighbor_rules.php?action=edit&id=' . get_filter_request_var('id'));

		break;
	case 'item_edit':
		top_header();
		neighbor_rules_item_edit();
		bottom_footer();

		break;
	case 'qedit':
		neighbor_change_query_type();
		header('Location: neighbor_rules.php?header=false&action=edit' . '&id=' . get_filter_request_var('id'));

		break;
	case 'remove':
		neighbor_rules_remove();
		header('Location: neighbor_rules.php');

		break;
	case 'edit':
		top_header();
		neighbor_rules_edit();
		bottom_footer();

		break;
	case 'rule_json':
		neighbor_rule_to_json();

		break;
	case 'interface_map':
		top_header();
		display_interface_map();
		bottom_footer();

		break;
	case 'ajax_interface_map':
		header('Content-Type: application/json');
		ajax_interface_nodes();

		break;
	default:
		top_header();
		neighbor_rules();
		bottom_footer();

		break;
}

/* --------------------------
 The Save Function
 -------------------------- */

function save_rule() {
	if (isset_request_var('save_component_neighbor_graph_rule')) {
		// ================= input validation =================
		get_filter_request_var('id');
		// ====================================================
		$save['id']          = get_nfilter_request_var('id');
		$save['name']        = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['description'] = form_input_validate(get_nfilter_request_var('description'), 'description', '', false, 3);

		if ($save['id']) {
			$save['neighbor_type']    = form_input_validate(get_nfilter_request_var('neighbor_type'), 'neighbor_type', '', false, 3);
			$save['neighbor_options'] = form_input_validate(get_nfilter_request_var('neighbor_options'), 'neighbor_options', '', false, 3);
			$save['enabled']          = (isset_request_var('enabled') ? 'on' : '');
		}

		if (!is_error_message()) {
			$rule_id = sql_save($save, 'plugin_neighbor_rules');

			if ($rule_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		} else {
			global $messages;
		}

		header('Location: neighbor_rules.php?header=false&action=edit&id=' . (empty($rule_id) ? get_nfilter_request_var('id') : $rule_id));
	} elseif (isset_request_var('save_component_neighbor_graph_rule_item')) {
		// ================= input validation =================
		get_filter_request_var('id');
		get_filter_request_var('item_id');
		// ====================================================

		$save              = [];
		$save['id']        = form_input_validate(get_request_var('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['rule_id']   = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['sequence']  = form_input_validate(get_nfilter_request_var('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate(get_nfilter_request_var('operation'), 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isset_request_var('field') && get_nfilter_request_var('field') != '0') ? get_nfilter_request_var('field') : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isset_request_var('operator') ? get_nfilter_request_var('operator') : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isset_request_var('pattern') ? get_nfilter_request_var('pattern') : ''), 'pattern', '', true, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'neighbor_plugin__graph_rule_items');

			if ($item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: neighbor_rules.php?header=false&action=item_edit&id=' . get_request_var('id') . '&item_id=' . (empty($item_id) ? get_request_var('item_id') : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		} else {
			header('Location: neighbor_rules.php?header=false&action=edit&id=' . get_request_var('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		}
	} elseif (isset_request_var('save_component_neighbor_match_item')) {
		// ================= input validation =================
		get_filter_request_var('id');
		get_filter_request_var('item_id');
		// ====================================================

		unset($save);
		$save['id']        = form_input_validate(get_request_var('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['rule_id']   = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['rule_type'] = AUTOMATION_RULE_TYPE_GRAPH_MATCH;
		$save['sequence']  = form_input_validate(get_nfilter_request_var('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate(get_nfilter_request_var('operation'), 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isset_request_var('field') && get_nfilter_request_var('field') != '0') ? get_nfilter_request_var('field') : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isset_request_var('operator') ? get_nfilter_request_var('operator') : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isset_request_var('pattern') ? get_nfilter_request_var('pattern') : ''), 'pattern', '', true, 3);

		if (!is_error_message()) {
			$item_id = sql_save($save, 'plugin_neighbor_match_rule_items');

			if ($item_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: neighbor_rules.php?header=false&action=item_edit&id=' . get_request_var('id') . '&item_id=' . (empty($item_id) ? get_request_var('item_id') : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		} else {
			header('Location: neighbor_rules.php?header=false&action=edit&id=' . get_request_var('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		}
	} else {
		raise_message(2);
		header('Location: neighbor_rules.php?header=false');
	}
}

/* ------------------------
 The 'actions' function
 ------------------------ */

function neighbor_rules_form_actions() {
	global $config, $neighbor_rules_actions;

	// ================= input validation =================
	get_filter_request_var('drp_action');
	// ====================================================

	// if we are to save this form, instead of display it
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DELETE) { // delete
				db_execute('DELETE FROM plugin_neighbor_rules WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM plugin_neighbor_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
				db_execute('DELETE FROM plugin_neighbor_match_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DUPLICATE) { // duplicate
				for ($i = 0; ($i < count($selected_items)); $i++) {
					cacti_log('form_actions duplicate: ' . $selected_items[$i] . ' name: ' . get_nfilter_request_var('name_format'), true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);
					duplicate_neighbor_rules($selected_items[$i], get_nfilter_request_var('name_format'));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_ENABLE) { // enable
				for ($i = 0; ($i < count($selected_items)); $i++) {
					cacti_log('form_actions enable: ' . $selected_items[$i], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE plugin_neighbor_rules
						SET enabled='on'
						WHERE id = ?",
						[$selected_items[$i]]);
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DISABLE) { // disable
				for ($i = 0; ($i < count($selected_items)); $i++) {
					cacti_log('form_actions disable: ' . $selected_items[$i], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE plugin_neighbor_rules
						SET enabled=''
						WHERE id = ?",
						[$selected_items[$i]]);
				}
			}
		}

		header('Location: neighbor_rules.php?header=false');

		exit;
	}

	// setup some variables
	$neighbor_rules_list = '';
	$i                   = 0;

	// loop through each of the graphs selected on the previous page and get more info about them
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			// ================= input validation =================
			input_validate_input_number($matches[1]);
			// ====================================================

			$neighbor_rules_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_neighbor_rules WHERE id = ?', [$matches[1]]) . '</li>';
			$neighbor_rules_array[] = $matches[1];
		}
	}

	top_header();

	form_start('neighbor_rules.php', 'neighbor_rules');

	html_start_box($neighbor_rules_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DELETE) { // delete
		print "<tr>
			<td class='textArea'>
				<p>" . __('Press \'Continue\' to delete the following Neighbor Rules.') . "</p>
				<ul>$neighbor_rules_list</ul>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DUPLICATE) { // duplicate
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to duplicate the following Rule(s). You can optionally change the title format for the new Neighbor Rules.') . "</p>
				<div class='itemlist'><ul>$neighbor_rules_list</ul></div>
				<p>" . __('Title Format') . '<br>';
		form_text_box('name_format', '<' . __('rule_name') . '> (1)', '', '255', '30', 'text');
		print "</p>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_ENABLE) { // enable
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to enable the following Rule(s).') . "</p>
				<div class='itemlist'><ul>$neighbor_rules_list</ul></div>
				<p>" . __('Make sure, that those rules have successfully been tested!') . "</p>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DISABLE) { // disable
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to disable the following Rule(s).') . "</p>
				<div class='itemlist'><ul>$neighbor_rules_list</ul></div>
			</td>
		</tr>\n";
	}

	if (!isset($neighbor_rules_array)) {
		print "<tr class='even'><td><span class='textError'>" . __('You must select at least one Rule.') . "</span></td></tr>\n";
		$save_html = "<input type='button' value='" . __esc('Return') . "' onClick='cactiReturnTo()'>";
	} else {
		$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc('Apply requested action') . "'>";
	}

	print "	<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($neighbor_rules_array) ? serialize($neighbor_rules_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

/* --------------------------
 Rule Item Functions
 -------------------------- */
function neighbor_rules_item_movedown() {
	// ================= input validation =================
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	// ====================================================

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_down('plugin_neighbor_match_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id') . ' AND rule_type=' . get_request_var('rule_type'));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_down('plugin_neighbor_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id'));
	}
}

function neighbor_rules_item_moveup() {
	// ================= input validation =================
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	// ====================================================

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_up('plugin_neighbor_match_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id') . ' AND rule_type=' . get_request_var('rule_type'));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_up('plugin_neighbor_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id'));
	}
}

function neighbor_rules_item_remove() {
	// ================= input validation =================
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	// ====================================================

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		db_execute_prepared('DELETE FROM plugin_neighbor_match_rule_items WHERE id = ?', [get_request_var('item_id')]);
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		db_execute_prepared('DELETE FROM plugin_neighbor_graph_rule_items WHERE id = ?', [get_request_var('item_id')]);
	}
}

function neighbor_rules_item_edit() {
	global $config;

	// ================= input validation =================
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	// ====================================================

	neighbor_global_item_edit(get_request_var('id'), get_request_var('item_id'), get_request_var('rule_type'));

	form_hidden_box('rule_type', get_request_var('rule_type'), get_request_var('rule_type'));
	form_hidden_box('id', (isset_request_var('id') ? get_request_var('id') : '0'), '');
	form_hidden_box('item_id', (isset_request_var('item_id') ? get_request_var('item_id') : '0'), '');

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		form_hidden_box('save_component_neighbor_match_item', '1', '');
	} else {
		form_hidden_box('save_component_neighbor_graph_rule_item', '1', '');
	}

	form_save_button('neighbor_rules.php?action=edit&id=' . get_request_var('id'));

	?>
	<script type='text/javascript'>

	$(function() {
		toggle_operation();
		toggle_operator();
	});

	function toggle_operation() {
		if ($('#operation').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET; ?>') {
			$('#field').val('');
			$('#field').prop('disabled', true);
			$('#operator').val(0);
			$('#operator').prop('disabled', true);
			$('#pattern').val('');
			$('#pattern').prop('disabled', true);
		} else {
			$('#field').prop('disabled', false);
			$('#operator').prop('disabled', false);
			$('#pattern').prop('disabled', false);
		}
	}

	function toggle_operator() {
		if ($('#operator').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET; ?>') {
		} else {
		}
	}
	</script>
	<?php
}

/* ---------------------
 Rule Functions
 --------------------- */

function neighbor_rules_remove() {
	// ================= input validation =================
	get_filter_request_var('id');
	// ====================================================

	if ((read_config_option('deletion_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __("Are you sure you want to delete the Rule '%s'?", db_fetch_cell_prepared('SELECT name FROM plugin_neighbor_rules WHERE id = ?', [get_request_var('id')])), 'neighbor_rules.php', 'neighbor_rules.php?action=remove&id=' . get_request_var('id'));
		bottom_footer();
		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset_request_var('confirm'))) {
		db_execute_prepared('DELETE FROM plugin_neighbor_match_rule_items
			WHERE rule_id = ?
			AND rule_type = ?',
			[get_request_var('id'), AUTOMATION_RULE_TYPE_GRAPH_MATCH]);

		db_execute_prepared('DELETE FROM plugin_neighbor_rule_items
			WHERE rule_id = ?',
			[get_request_var('id')]);

		db_execute_prepared('DELETE FROM plugin_neighbor_rules
			WHERE id = ?',
			[get_request_var('id')]);
	}
}

function neighbor_change_query_type() {
	$id = get_filter_request_var('id');

	if (isset_request_var('snmp_query_id') && $id > 0) {
		$snmp_query_id = get_filter_request_var('snmp_query_id');
		$name          = get_nfilter_request_var('name');

		db_execute_prepared('UPDATE plugin_neighbor_rules
			SET snmp_query_id = ?, name = ?
			WHERE id = ?',
			[$snmp_query_id, $name, $id]);
		raise_message(1);
	} elseif (isset_request_var('neighbor_type') && $id > 0) {
		$name             = get_nfilter_request_var('name');
		$neighbor_type    = get_nfilter_request_var('neighbor_type');
		$neighbor_options = get_nfilter_request_var('neighbor_options');
		db_execute_prepared('UPDATE plugin_neighbor_rules
			SET neighbor_type = ?, name = ?, neighbor_options = ?
			WHERE id = ?',
			[$neighbor_type, $name, $neighbor_options, $id]);
		raise_message(1);
	} elseif (isset_request_var('neighbor_options') && $id > 0) {
		$name             = get_nfilter_request_var('name');
		$neighbor_type    = get_nfilter_request_var('neighbor_type');
		$neighbor_options = get_nfilter_request_var('neighbor_options');
		db_execute_prepared('UPDATE plugin_neighbor_rules
			SET neighbor_type = ?, name = ?, neighbor_options = ?
			WHERE id = ?',
			[$neighbor_type, $name, $neighbor_options, $id]);
		raise_message(1);
	}
}

function neighbor_rules_edit() {
	global $config;
	global $fields_neighbor_graph_rules_edit1, $fields_neighbor_graph_rules_edit2, $fields_neighbor_graph_rules_edit3;

	// ================= input validation =================
	get_filter_request_var('id');
	get_filter_request_var('show_neighbors');
	get_filter_request_var('show_hosts');
	get_filter_request_var('show_rule');
	// ====================================================

	// clean up rule name
	if (isset_request_var('name')) {
		set_request_var('name', sanitize_search_string(get_request_var('name')));
	}

	if (isset_request_var('description')) {
		set_request_var('description', sanitize_search_string(get_request_var('description')));
	}

	if (isset_request_var('neighbor_type')) {
		set_request_var('neighbor_type', sanitize_search_string(get_request_var('neighbor_type')));
	}

	if (isset_request_var('neighbor_options')) {
		set_request_var('neighbor_options', sanitize_search_string(get_request_var('neighbor_options')));
	}

	// handle show_rule mode
	if (isset_request_var('show_rule')) {
		if (get_request_var('show_rule') == '0') {
			kill_session_var('neighbor_rules_show_rule');
			$_SESSION['neighbor_rules_show_rule'] = false;
		} elseif (get_request_var('show_rule') == '1') {
			$_SESSION['neighbor_rules_show_rule'] = true;
		}
	} elseif (!isset($_SESSION['neighbor_rules_show_rule'])) {
		$_SESSION['neighbor_rules_show_rule'] = true;
	}

	// handle show_neighbors mode
	if (isset_request_var('show_neighbors')) {
		if (get_request_var('show_neighbors') == '0') {
			kill_session_var('neighbor_rules_show_neighbors');
		} elseif (get_request_var('show_neighbors') == '1') {
			$_SESSION['neighbor_rules_show_neighbors'] = true;
		}
	}

	// handle show_hosts mode
	if (isset_request_var('show_hosts')) {
		if (get_request_var('show_hosts') == '0') {
			kill_session_var('neighbor_rules_show_hosts');
		} elseif (get_request_var('show_hosts') == '1') {
			$_SESSION['neighbor_rules_show_hosts'] = true;
		}
	}

	// display the rule -------------------------------------------------------------------------------------
	$rule = [];

	if (!isempty_request_var('id')) {
		$rule = db_fetch_row_prepared('SELECT * FROM plugin_neighbor_rules where id = ?', [get_request_var('id')]);

		if (!isempty_request_var('graph_type_id')) {
			$rule['graph_type_id'] = get_request_var('graph_type_id'); // set query_type for display
		}

		// setup header
		$header_label = __('Rule Selection [edit: %s]', html_escape($rule['name']));
	} else {
		$rule =  [
				'name'        => get_request_var('name'),
				'description' => get_request_var('description'),
				];
		$header_label = __('Rule Selection [new]');
	}

	// show rule? ------------------------------------------------------------------------------------------
	if (!isempty_request_var('id')) {
		?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='textInfo right' style='vertical-align:top;'><span class='linkMarker'>*</span>
			<a class='linkEditMain' href='<?php print html_escape('neighbor_rules.php?action=edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&show_rule=') . ($_SESSION['neighbor_rules_show_rule'] == true ? '0' : '1'); ?>'>
			<?php print($_SESSION['neighbor_rules_show_rule'] == true ? __('Don\'t Show') : __('Show')); ?> <?php print __('Rule Details.'); ?></a><br>
			</td>
		</tr>
	</table>

	<?php
	}

	// show hosts? ------------------------------------------------------------------------------------------
	if (!isempty_request_var('id')) {
		?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='textInfo right' style='vertical-align:top;'><span class='linkMarker'>*</span>
			<a class='linkEditMain' href='<?php print html_escape('neighbor_rules.php?action=edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&show_hosts=') . (isset($_SESSION['neighbor_rules_show_hosts']) ? '0' : '1'); ?>'>
			<?php print(isset($_SESSION['neighbor_rules_show_hosts']) ? __('Don\'t Show') : __('Show')); ?> <?php print __('Matching Devices.'); ?></a><br>
			</td>
		</tr>
	</table>

	<?php
	}

	// show graphs? -----------------------------------------------------------------------------------------
	if (!isempty_request_var('id')) {
		?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='textInfo right' style='vertical-align:top;'>
				<span class='linkMarker'>*</span>
				<a class='linkEditMain' href='<?php print html_escape('neighbor_rules.php?action=edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&show_neighbors=') . (isset($_SESSION['neighbor_rules_show_neighbors']) ? '0' : '1'); ?>'>
				<?php print(isset($_SESSION['neighbor_rules_show_neighbors']) ? __('Don\'t Show') : __('Show')); ?> <?php print __('Matching Objects.'); ?></a><br>
			</td>
			</tr>
	</table>
	
	<?php
	}

	if ($_SESSION['neighbor_rules_show_rule']) {
		form_start('neighbor_rules.php', 'neighbor_rules');
		html_start_box($header_label, '100%', true, '3', 'center', '');

		if (!isempty_request_var('id')) {
			$neighbor_type = db_fetch_cell_prepared('SELECT neighbor_type from plugin_neighbor_rules where id =?', [get_request_var('id')]);

			if ($neighbor_type == 'routing') {
				$fields_neighbor_graph_rules_edit2['neighbor_options'] = [
				'method'        => 'drop_array',
				'friendly_name' => __('Neighbor Type'),
				'description'   => __('What type of neighbors should be considered?'),
				'array'         => [
					'bgp' 	 => __('BGP'),
					'ospf' 	=> __('OSPF'),
					'is-is'	=> __('IS-IS'),
				],
				'value' => '|arg1:neighbor_options|',
				'class' => 'month',
				];
			} else {
				$fields_neighbor_graph_rules_edit2['neighbor_options'] = [
					'method'        => 'drop_array',
					'friendly_name' => __('Neighbor Type'),
					'description'   => __('What type of neighbors should be considered?'),
					'array'         => [
						'xdp' 	 => __('CDP/LLDP'),
						'ipv4' 	=> __('IPv4 Subnet'),
						'alias'	=> __('Interface Alias'),
					],
					'value' => '|arg1:neighbor_options|',
					'class' => 'month',
				];
			}
			$form_array = $fields_neighbor_graph_rules_edit1 + $fields_neighbor_graph_rules_edit2 + $fields_neighbor_graph_rules_edit3;
			// display whole rule
		} else {
			// display first part of rule only and request user to proceed
			$form_array = [
					'name' => [
						'method'        => 'textbox',
						'friendly_name' => __('Name'),
						'description'   => __('A useful name for this Rule.'),
						'value'         => '|arg1:name|',
						'max_length'    => '64',
						'size'          => '64'
					],
					'description' => [
						'method'        => 'textbox',
						'friendly_name' => __('Description'),
						'description'   => __('A description of this Rule'),
						'value'         => '|arg1:description|',
						'max_length'    => '64',
						'size'          => '64'
					]
			];
		}

		if (isset_request_var('name')) {
			$rule['name']        = get_request_var('name');
			$rule['description'] = get_request_var('description');
		}
		// pre_print_r($form_array,"Form Array");
		// pre_print_r($rule,"Rule Array");

		draw_edit_form([
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($form_array, (isset($rule) ? $rule : []))
		]);

		html_end_box(true, true);

		form_hidden_box('id', (isset($rule['id']) ? $rule['id'] : '0'), '');
		form_hidden_box('save_component_neighbor_graph_rule', '1', '');
	}

	// display the rule items -------------------------------------------------------------------------------
	if (!empty($rule['id'])) {
		// display graph rules for host match
		neighbor_display_match_rule_items(__('Device Selection Criteria'),
			$rule['id'],
			AUTOMATION_RULE_TYPE_GRAPH_MATCH,
			'neighbor_rules.php');

		// fetch graph action rules
		neighbor_display_graph_rule_items(__('Neighbor Creation Criteria'),
			$rule['id'],
			AUTOMATION_RULE_TYPE_GRAPH_ACTION,
			'neighbor_rules.php');
	}

	form_save_button('neighbor_rules.php', 'return');
	print '<br>';

	if (!empty($rule['id'])) {
		// display list of matching hosts
		if (isset($_SESSION['neighbor_rules_show_hosts'])) {
			if ($_SESSION['neighbor_rules_show_hosts']) {
				neighbor_display_matching_hosts($rule, AUTOMATION_RULE_TYPE_GRAPH_MATCH, 'neighbor_rules.php?action=edit&id=' . get_request_var('id'));
			}
		}

		// display list of new graphs
		if (isset($_SESSION['neighbor_rules_show_neighbors'])) {
			if ($_SESSION['neighbor_rules_show_neighbors']) {
				neighbor_display_new_graphs($rule, 'neighbor_rules.php?action=edit&id=' . get_request_var('id'));
			}
		}
	}

	?>
	<script type='text/javascript'>
		
	function applySNMPQueryIdChange() {
		strURL  = 'neighbor_rules.php?action=qedit';
		strURL += '&id=' + $('#id').val();
		strURL += '&name=' + $('#name').val();
		strURL += '&description=' + $('#description').val();
		strURL += '&snmp_query_id=' + $('#snmp_query_id').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function applyNeighborTypeChange() {
		strURL  = 'neighbor_rules.php?action=qedit'
		strURL += '&id=' + $('#id').val();
		strURL += '&name=' + $('#name').val();
		strURL += '&description=' + $('#description').val();
		strURL += '&neighbor_type=' + $('#neighbor_type').val();
		strURL += '&header=false';
		console.log("Applying neighbor type:",strURL);
		loadPageNoHeader(strURL);
	}
	</script>
	<?php
}

function neighbor_rules() {
	global $neighbor_rules_actions, $config, $item_rows;

	// ================= input validation and session storage =================
	$filters = [
		'rows' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			],
		'page' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			],
		'filter' => [
			'filter'  => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => ['options' => 'sanitize_search_string']
			],
		'sort_column' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'name',
			'options' => ['options' => 'sanitize_search_string']
			],
		'sort_direction' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => ['options' => 'sanitize_search_string']
			],
		'status' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			],
		'snmp_query_id' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => ''
			],
	];

	validate_store_request_vars($filters, 'sess_autom_gr');
	// ================= input validation =================

	if (get_request_var('rows') == -1) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$total_rows      = 0;
	$page 			        = get_request_var('page') ? get_request_var('page') : 1;
	$startRow 		     = ($page - 1) * $rows;
	$endRow 		       = (($page - 1) * $rows) + $rows - 1;
	$sortColumn 	    = get_request_var('sort_column');
	$sortDirection 	 = get_request_var('sort_direction');
	$filterVal 		    = get_request_var('filter');

	$neighbor_rules = get_neighbor_rules($total_rows,$startRow, $rows,$filterVal,$sortColumn,$sortDirection);
	get_neighbor_rules_filter();

	$nav = html_nav_bar('neighbor_rules.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 7, __('Neighbor Rules'), 'page', 'main');

	form_start('neighbor_rules.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = [
		'name'            => ['display' => __('Rule Name'),  'align' => 'left', 'sort' => 'ASC', 'tip' => __('The name of this rule.')],
		'id'              => ['display' => __('ID'),         'align' => 'right', 'sort' => 'ASC', 'tip' => __('The internal database ID for this rule.  Useful in performing debugging and automation.')],
		'enabled'         => ['display' => __('Enabled'),    'align' => 'right', 'sort' => 'ASC'],
	];

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (sizeof($neighbor_rules)) {
		foreach ($neighbor_rules as $rule) {
			form_alternate_row('line' . $rule['id'], true);

			form_selectable_cell(filter_value($rule['name'], get_request_var('filter'), 'neighbor_rules.php?action=edit&id=' . $rule['id'] . '&page=1'), $rule['id']);
			form_selectable_cell($rule['id'], $rule['id'], '', 'text-align:right');
			form_selectable_cell($rule['enabled'] ? __('Enabled') : __('Disabled'), $rule['id'], '', 'text-align:right');
			form_checkbox_cell($rule['name'], $rule['id']);
			form_end_row();
		}
	} else {
		print '<tr><td><em>' . __('No Neighbor Rules Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (count($neighbor_rules)) {
		print $nav;
	}

	// draw the dropdown containing a list of available actions for this form
	draw_actions_dropdown($neighbor_rules_actions);
	form_end();
}
