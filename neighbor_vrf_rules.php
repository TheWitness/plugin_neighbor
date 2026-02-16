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
include_once($config['base_path'] .'/plugins/neighbor/lib/neighbor_functions.php');

$neighbor_rules_actions = array(
	AUTOMATION_ACTION_GRAPH_DUPLICATE => __('Duplicate'),
	AUTOMATION_ACTION_GRAPH_ENABLE    => __('Enable'),
	AUTOMATION_ACTION_GRAPH_DISABLE   => __('Disable'),
	AUTOMATION_ACTION_GRAPH_DELETE    => __('Delete'),
);

/* set default action */
set_default_action();
error_log("ACTION:".get_request_var('action'));
switch (get_request_var('action')) {
	case 'save':
		save_vrf_rule();
		break;
	case 'actions':
		neighbor_rules_form_actions();
		break;
	case 'item_movedown':
		neighbor_rules_item_movedown();
		header('Location: neighbor_vrf_rules.php?action=edit&id=' . get_filter_request_var('id'));
		break;
	case 'item_moveup':
		neighbor_rules_item_moveup();
		header('Location: neighbor_vrf_rules.php?action=edit&id=' . get_filter_request_var('id'));
		break;
	case 'item_remove':
		neighbor_rules_item_remove();
		header('Location: neighbor_vrf_rules.php?action=edit&id=' . get_filter_request_var('id'));
		break;
	case 'item_edit':
		top_header();
		neighbor_vrf_rules_item_edit();
		bottom_footer();
		break;
	case 'qedit':
		neighbor_change_query_type();
		header('Location: neighbor_vrf_rules.php?header=false&action=edit'. '&id=' . get_filter_request_var('id'));
		break;
	case 'remove':
		neighbor_rules_remove();
		header ('Location: neighbor_vrf_rules.php');
		break;
	case 'edit':
		top_header();
		neighbor_vrf_rules_edit();
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
		error_log("Calling ajax_interface_map()...");
		header('Content-Type: application/json');
		ajax_interface_nodes();
		break;
	default:
		top_header();
		neighbor_vrf_rules();
		bottom_footer();
		break;
}

/* --------------------------
 The Save Function
 -------------------------- */

function save_vrf_rule() {
	if (isset_request_var('save_component_neighbor_graph_rule')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */
		error_log("Saving Neighbor Rule...");
		$save['id'] = get_nfilter_request_var('id');
		$save['name'] = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['description'] = form_input_validate(get_nfilter_request_var('description'), 'description', '', false, 3);
		$save['vrf'] = form_input_validate(get_nfilter_request_var('vrf'), 'vrf', '', false, 3);
		
		if ($save['id']) {
			$save['enabled'] = (isset_request_var('enabled') ? 'on' : '');
		}
		
		error_log("save_vrf_rule(): SAVE is=".print_r($save,1));
		if (!is_error_message()) {
			error_log("SQL Saving..");
			$rule_id = sql_save($save, 'plugin_neighbor_vrf_rules');
			if ($rule_id) 	{ raise_message(1); }
			else 			{ raise_message(2); }
		}
		else {
			global $messages;
			error_log("Validation errors\nDEBUG Sessions:".print_r($_SESSION,1));
			
		}

		header('Location: neighbor_vrf_rules.php?header=false&action=edit&id=' . (empty($rule_id) ? get_nfilter_request_var('id') : $rule_id));
	}
	elseif (isset_request_var('save_component_neighbor_vrf_rule_item')) {
		error_log("Saving record with request: save_component_neighbor_vrf_rule_item");
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('item_id');
		/* ==================================================== */

		$save = array();
		$save['id']        = form_input_validate(get_request_var('item_id'), 'item_id', '^[0-9]+$', false, 3);
		$save['rule_id']   = form_input_validate(get_request_var('id'), 'id', '^[0-9]+$', false, 3);
		$save['sequence']  = form_input_validate(get_nfilter_request_var('sequence'), 'sequence', '^[0-9]+$', false, 3);
		$save['operation'] = form_input_validate(get_nfilter_request_var('operation'), 'operation', '^[-0-9]+$', true, 3);
		$save['field']     = form_input_validate(((isset_request_var('field') && get_nfilter_request_var('field') != '0') ? get_nfilter_request_var('field') : ''), 'field', '', true, 3);
		$save['operator']  = form_input_validate((isset_request_var('operator') ? get_nfilter_request_var('operator') : ''), 'operator', '^[0-9]+$', true, 3);
		$save['pattern']   = form_input_validate((isset_request_var('pattern') ? get_nfilter_request_var('pattern') : ''), 'pattern', '', true, 3);

		if (!is_error_message()) {
			error_log("Saving record with save:".print_r($save,1));
			$item_id = sql_save($save, 'plugin_neighbor_vrf_rule_items');
			if ($item_id) 	{ raise_message(1); }
			else 			{ raise_message(2); }
		}
		else {
			error_log("Form validation error encountered, not saving...");
		}

		if (is_error_message()) {
			header('Location: neighbor_vrf_rules.php?header=false&action=item_edit&id=' . get_request_var('id') . '&item_id=' . (empty($item_id) ? get_request_var('item_id') : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		} else {
			header('Location: neighbor_vrf_rules.php?header=false&action=edit&id=' . get_request_var('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_ACTION);
		}
	} elseif (isset_request_var('save_component_neighbor_vrf_match_item')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		get_filter_request_var('item_id');
		/* ==================================================== */

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
			$item_id = sql_save($save, 'plugin_neighbor_vrf_match_rule_items');
			if ($item_id) 	{ raise_message(1); }
			else 			{ raise_message(2); }
		}

		if (is_error_message()) {
			header('Location: neighbor_vrf_rules.php?header=false&action=item_edit&id=' . get_request_var('id') . '&item_id=' . (empty($item_id) ? get_request_var('item_id') : $item_id) . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		} else {
			header('Location: neighbor_vrf_rules.php?header=false&action=edit&id=' . get_request_var('id') . '&rule_type=' . AUTOMATION_RULE_TYPE_GRAPH_MATCH);
		}
	} else {
		raise_message(2);
		header('Location: neighbor_vrf_rules.php?header=false');
	}
}

/* ------------------------
 The 'actions' function
 ------------------------ */

function neighbor_rules_form_actions() {
	global $config, $neighbor_rules_actions;

        /* ================= input validation ================= */
        get_filter_request_var('drp_action');
        /* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DELETE) { /* delete */
				db_execute('DELETE FROM plugin_neighbor_vrf_rules WHERE ' . array_to_sql_or($selected_items, 'id'));
				db_execute('DELETE FROM plugin_neighbor_plugin_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
				db_execute('DELETE FROM plugin_network__match_rule_items WHERE ' . array_to_sql_or($selected_items, 'rule_id'));
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DUPLICATE) { /* duplicate */
				for ($i=0;($i<count($selected_items));$i++) {
					cacti_log('form_actions duplicate: ' . $selected_items[$i] . ' name: ' . get_nfilter_request_var('name_format'), true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);
					duplicate_neighbor_vrf_rules($selected_items[$i], get_nfilter_request_var('name_format'));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_ENABLE) { /* enable */
				for ($i=0;($i<count($selected_items));$i++) {
					cacti_log('form_actions enable: ' . $selected_items[$i], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE neighbor_rules
						SET enabled='on'
						WHERE id = ?",
						array($selected_items[$i]));
				}
			} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DISABLE) { /* disable */
				for ($i=0;($i<count($selected_items));$i++) {
					cacti_log('form_actions disable: ' . $selected_items[$i], true, 'AUTOM8 TRACE', POLLER_VERBOSITY_MEDIUM);

					db_execute_prepared("UPDATE neighbor_rules
						SET enabled=''
						WHERE id = ?",
						array($selected_items[$i]));
				}
			}
		}

		header('Location: neighbor_vrf_rules.php?header=false');

		exit;
	}

	/* setup some variables */
	$neighbor_rules_list = ''; $i = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$neighbor_rules_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_neighbor_vrf_rules WHERE id = ?', array($matches[1])) . '</li>';
			$neighbor_rules_array[] = $matches[1];
		}
	}

	top_header();

	form_start('neighbor_vrf_rules.php', 'neighbor_rules');

	html_start_box($neighbor_rules_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DELETE) { /* delete */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Press \'Continue\' to delete the following VRF Mapping Rules.') . "</p>
				<ul>$neighbor_rules_list</ul>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DUPLICATE) { /* duplicate */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to duplicate the following Rule(s). You can optionally change the title format for the new VRF Mapping Rules.') . "</p>
				<div class='itemlist'><ul>$neighbor_rules_list</ul></div>
				<p>" . __('Title Format') . '<br>'; form_text_box('name_format', '<' . __('rule_name') . '> (1)', '', '255', '30', 'text'); print "</p>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_ENABLE) { /* enable */
		print "<tr>
			<td class='textArea'>
				<p>" . __('Click \'Continue\' to enable the following Rule(s).') . "</p>
				<div class='itemlist'><ul>$neighbor_rules_list</ul></div>
				<p>" . __('Make sure, that those rules have successfully been tested!') . "</p>
			</td>
		</tr>\n";
	} elseif (get_nfilter_request_var('drp_action') == AUTOMATION_ACTION_GRAPH_DISABLE) { /* disable */
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
	}else {
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
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_down('plugin_neighbor_match_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id') . ' AND rule_type=' . get_request_var('rule_type'));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_down('neighbor_plugin__rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id'));
	}
}

function neighbor_rules_item_moveup() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		move_item_up('plugin_neighbor_match_rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id') . ' AND rule_type=' . get_request_var('rule_type'));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		move_item_up('neighbor_plugin__rule_items', get_request_var('item_id'), 'rule_id=' . get_request_var('id'));
	}
}

function neighbor_rules_item_remove() {
	/* ================= input validation ================= */
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	if (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		db_execute_prepared('DELETE FROM plugin_neighbor_match_rule_items WHERE id = ?', array(get_request_var('item_id')));
	} elseif (get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_ACTION) {
		db_execute_prepared('DELETE FROM plugin_neighbor_vrf_rule_items WHERE id = ?', array(get_request_var('item_id')));
	}

}

function neighbor_vrf_rules_item_edit() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('item_id');
	get_filter_request_var('rule_type');
	/* ==================================================== */

	neighbor_global_vrf_item_edit(get_request_var('id'), get_request_var('item_id'), get_request_var('rule_type'));

	form_hidden_box('rule_type', get_request_var('rule_type'), get_request_var('rule_type'));
	form_hidden_box('id', (isset_request_var('id') ? get_request_var('id') : '0'), '');
	form_hidden_box('item_id', (isset_request_var('item_id') ? get_request_var('item_id') : '0'), '');

	if(get_request_var('rule_type') == AUTOMATION_RULE_TYPE_GRAPH_MATCH) {
		form_hidden_box('save_component_neighbor_vrf_match_item', '1', '');
	} else {
		form_hidden_box('save_component_neighbor_vrf_rule_item', '1', '');
	}

	form_save_button('neighbor_vrf_rules.php?action=edit&id=' . get_request_var('id'));

	?>
	<script type='text/javascript'>

	$(function() {
		toggle_operation();
		toggle_operator();
	});

	function toggle_operation() {
		if ($('#operation').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET;?>') {
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
		if ($('#operator').val() == '<?php print AUTOMATION_OPER_RIGHT_BRACKET;?>') {
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
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if ((read_config_option('deletion_verification') == 'on') && (!isset_request_var('confirm'))) {
		top_header();
		form_confirm(__('Are You Sure?'), __("Are you sure you want to delete the Rule '%s'?", db_fetch_cell_prepared('SELECT name FROM plugin_neighbor_vrf_rules WHERE id = ?', array(get_request_var('id')))), 'neighbor_vrf_rules.php', 'neighbor_vrf_rules.php?action=remove&id=' . get_request_var('id'));
		bottom_footer();
		exit;
	}

	if ((read_config_option('deletion_verification') == '') || (isset_request_var('confirm'))) {
		db_execute_prepared('DELETE FROM plugin_network__match_rule_items
			WHERE rule_id = ?
			AND rule_type = ?',
			array(get_request_var('id'), AUTOMATION_RULE_TYPE_GRAPH_MATCH));

		db_execute_prepared('DELETE FROM plugin_neighbor_plugin_rule_items
			WHERE rule_id = ?',
			array(get_request_var('id')));

		db_execute_prepared('DELETE FROM plugin_neighbor_vrf_rules
			WHERE id = ?',
			array(get_request_var('id')));
	}
}

function neighbor_change_query_type() {
	$id = get_filter_request_var('id');

	if (isset_request_var('snmp_query_id') && $id > 0) {
		$snmp_query_id = get_filter_request_var('snmp_query_id');
		$name = get_nfilter_request_var('name');

		db_execute_prepared('UPDATE plugin_neighbor_vrf_rules
			SET snmp_query_id = ?, name = ?
			WHERE id = ?',
			array($snmp_query_id, $name, $id));
		raise_message(1);
	}
	elseif (isset_request_var('neighbor_type') && $id > 0) {
		$name = get_nfilter_request_var('name');
		$neighbor_type = get_nfilter_request_var('neighbor_type');
		$neighbor_options = get_nfilter_request_var('neighbor_options');
		db_execute_prepared('UPDATE plugin_neighbor_vrf_rules
			SET neighbor_type = ?, name = ?, neighbor_options = ?
			WHERE id = ?',
			array($neighbor_type, $name, $neighbor_options, $id));
		raise_message(1);
	}
	elseif (isset_request_var('neighbor_options') && $id > 0) {
		$name = get_nfilter_request_var('name');
		$neighbor_type = get_nfilter_request_var('neighbor_type');
		$neighbor_options = get_nfilter_request_var('neighbor_options');
		db_execute_prepared('UPDATE plugin_neighbor_vrf_rules
			SET neighbor_type = ?, name = ?, neighbor_options = ?
			WHERE id = ?',
			array($neighbor_type, $name, $neighbor_options, $id));
		raise_message(1);
	}
	
}

function neighbor_vrf_rules_edit() {
	global $config;
	global $fields_neighbor_vrf_rules_edit1, $fields_neighbor_graph_rules_edit2;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('show_neighbors');
	get_filter_request_var('show_hosts');
	get_filter_request_var('show_rule');
	/* ==================================================== */

	/* clean up rule name */
	if (isset_request_var('name')) 				{ set_request_var('name', sanitize_search_string(get_request_var('name'))); }
	if (isset_request_var('description')) 		{ set_request_var('description', sanitize_search_string(get_request_var('description')));}
	if (isset_request_var('vrf')) 				{ set_request_var('vrf', sanitize_search_string(get_request_var('vrf')));}

	/* handle show_rule mode */
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

	/* handle show_neighbors mode */
	if (isset_request_var('show_neighbors')) {
		if (get_request_var('show_neighbors') == '0') {
			kill_session_var('neighbor_rules_show_neighbors');
		} elseif (get_request_var('show_neighbors') == '1') {
			$_SESSION['neighbor_rules_show_neighbors'] = true;
		}
	}

	/* handle show_hosts mode */
	if (isset_request_var('show_hosts')) {
		if (get_request_var('show_hosts') == '0') {
			kill_session_var('neighbor_rules_show_hosts');
		} elseif (get_request_var('show_hosts') == '1') {
			$_SESSION['neighbor_rules_show_hosts'] = true;
		}
	}

	/*
	 * display the rule -------------------------------------------------------------------------------------
	 */
	$rule = array();
	if (!isempty_request_var('id')) {
		$rule = db_fetch_row_prepared('SELECT * FROM plugin_neighbor_vrf_rules where id = ?', array(get_request_var('id')));

		if (!isempty_request_var('graph_type_id')) {
			$rule['graph_type_id'] = get_request_var('graph_type_id'); # set query_type for display
		}
		# setup header
		$header_label = __('Rule Selection [edit: %s]', html_escape($rule['name']));
	}
	else {
		$rule = array (
				'name' 			=> get_request_var('name'),
				'description' 	=> get_request_var('description'),
				'vrf'			=> get_request_var('vrf'),
				);
		$header_label = __('VRF Mapping Rule [new]');
	}

	/*
	 * show rule? ------------------------------------------------------------------------------------------
	 */
	if (!isempty_request_var('id')) {
	?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='textInfo right' style='vertical-align:top;'><span class='linkMarker'>*</span>
			<a class='linkEditMain' href='<?php print html_escape('neighbor_vrf_rules.php?action=edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&show_rule=') . ($_SESSION['neighbor_rules_show_rule'] == true ? '0' : '1');?>'>
			<?php print ($_SESSION['neighbor_rules_show_rule'] == true ? __('Don\'t Show'):__('Show'));?> <?php print __('Rule Details.');?></a><br>
			</td>
		</tr>
	</table>

	<?php
	}

	/*
	 * show hosts? ------------------------------------------------------------------------------------------
	 */
	if (!isempty_request_var('id')) {
		?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='textInfo right' style='vertical-align:top;'><span class='linkMarker'>*</span>
			<a class='linkEditMain' href='<?php print html_escape('neighbor_vrf_rules.php?action=edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&show_hosts=') . (isset($_SESSION['neighbor_rules_show_hosts']) ? '0' : '1');?>'>
			<?php print (isset($_SESSION['neighbor_rules_show_hosts']) ? __('Don\'t Show'):__('Show'));?> <?php print __('Matching Devices.');?></a><br>
			</td>
		</tr>
	</table>

	<?php
	}

	/*
	 * show graphs? -----------------------------------------------------------------------------------------
	 */
	if (!isempty_request_var('id')) {
		?>
	<table style='width:100%;text-align:center;'>
		<tr>
			<td class='textInfo right' style='vertical-align:top;'>
				<span class='linkMarker'>*</span>
				<a class='linkEditMain' href='<?php print html_escape('neighbor_vrf_rules.php?action=edit&id=' . (isset_request_var('id') ? get_request_var('id') : 0) . '&show_neighbors=') . (isset($_SESSION['neighbor_rules_show_neighbors']) ? '0' : '1');?>'>
				<?php print (isset($_SESSION['neighbor_rules_show_neighbors']) ? __('Don\'t Show'):__('Show'));?> <?php print __('Matching Objects.');?></a><br>
			</td>
			</tr>
	</table>
	
	<?php
	}

	if ($_SESSION['neighbor_rules_show_rule']) {
		
		form_start('neighbor_vrf_rules.php', 'neighbor_rules');
		html_start_box($header_label, '100%', true, '3', 'center', '');

		if (!isempty_request_var('id')) {
			$vrfDiscovered = get_vrf_list();
			$fields_neighbor_vrf_rules_edit2['vrf'] = array(
				'method' => 'drop_array',
				'friendly_name' => __('VRF Name'),
				'description' => __('Select a VRF to map to'),
				'value' => '|arg1:vrf|',
				'array'	=> $vrfDiscovered,
				'max_length' => '64',
				'size' => '64'
			);
				
			error_log('$fields_neighbor_vrf_rules_edit1:'.print_r($fields_neighbor_vrf_rules_edit1,1));
			error_log('$fields_neighbor_vrf_rules_edit2:'.print_r($fields_neighbor_vrf_rules_edit2,1));
			$form_array = $fields_neighbor_vrf_rules_edit1 + $fields_neighbor_vrf_rules_edit2;
			/* display whole rule */
		} else {
			/* display first part of rule only and request user to proceed */
			
			$vrfDiscovered = get_vrf_list();
			
			$form_array = array(
					'name' => array(
						'method' => 'textbox',
						'friendly_name' => __('Name'),
						'description' => __('A useful name for this Rule.'),
						'value' => '|arg1:name|',
						'max_length' => '64',
						'size' => '64'
					),
					'description' => array(
						'method' => 'textbox',
						'friendly_name' => __('Description'),
						'description' => __('A description of this Rule'),
						'value' => '|arg1:description|',
						'max_length' => '64',
						'size' => '64'
					),
					'vrf' => array(
						'method' => 'drop_array',
						'friendly_name' => __('VRF Name'),
						'description' => __('Select a VRF to map to'),
						'value' => '|arg1:vrf|',
						'array'	=> $vrfDiscovered,
						'max_length' => '64',
						'size' => '64'
					)
					
			);
		}

		if (isset_request_var('name')) {
			$rule['name'] = get_request_var('name');
			$rule['description'] = get_request_var('description');
			$rule['vrf'] = get_request_var('vrf');
		}
		
		draw_edit_form(array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($form_array, (isset($rule) ? $rule : array()))
		));

		html_end_box(true, true);

		form_hidden_box('id', (isset($rule['id']) ? $rule['id'] : '0'), '');
		form_hidden_box('save_component_neighbor_graph_rule', '1', '');
	}
	/*
	 * display the rule items -------------------------------------------------------------------------------
	 */
	if (!empty($rule['id'])) {
		# display graph rules for host match
		neighbor_display_vrf_match_rule_items(__('Device Selection Criteria'),
			$rule['id'],
			AUTOMATION_RULE_TYPE_GRAPH_MATCH,
			'neighbor_vrf_rules.php');

		# fetch graph action rules
		neighbor_display_vrf_rule_items(__('Neighbor Creation Criteria'),
			$rule['id'],
			AUTOMATION_RULE_TYPE_GRAPH_ACTION,
			'neighbor_vrf_rules.php');
	}

	form_save_button('neighbor_vrf_rules.php', 'return');
	print '<br>';
	
	if (!empty($rule['id'])) {
		/* display list of matching hosts */
		if (isset($_SESSION['neighbor_rules_show_hosts'])) {
			if ($_SESSION['neighbor_rules_show_hosts']) {
				neighbor_display_vrf_matching_hosts($rule, AUTOMATION_RULE_TYPE_GRAPH_MATCH, 'neighbor_vrf_rules.php?action=edit&id=' . get_request_var('id'));
			}
		}

		/* display list of new graphs */
		if (isset($_SESSION['neighbor_rules_show_neighbors'])) {
			if ($_SESSION['neighbor_rules_show_neighbors']) {
				neighbor_display_vrf_object_matches($rule, 'neighbor_vrf_rules.php?action=edit&id=' . get_request_var('id'));
			}
		}
	}

	?>
	<script type='text/javascript'>
		
	function applySNMPQueryIdChange() {
		strURL  = 'neighbor_vrf_rules.php?action=qedit';
		strURL += '&id=' + $('#id').val();
		strURL += '&name=' + $('#name').val();
		strURL += '&description=' + $('#description').val();
		strURL += '&snmp_query_id=' + $('#snmp_query_id').val();
		strURL += '&header=false';
		loadPageNoHeader(strURL);
	}

	function applyNeighborTypeChange() {
		strURL  = 'neighbor_vrf_rules.php?action=qedit'
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

function neighbor_vrf_rules() {
	
	global $neighbor_rules_actions, $config, $item_rows;

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
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'snmp_query_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => ''
			),
	);

	validate_store_request_vars($filters, 'sess_autom_gr');
	/* ================= input validation ================= */

	if (get_request_var('rows') == -1) 	{ $rows = read_config_option('num_rows_table'); }
	else 								{ $rows = get_request_var('rows'); }

	$total_rows = 0;
	$page 			= get_request_var('page') ? get_request_var('page') : 1;
	$startRow 		= ($page-1) * $rows;
	$endRow 		= (($page-1) * $rows) + $rows - 1;
	$sortColumn 	= get_request_var('sort_column');
	$sortDirection 	= get_request_var('sort_direction');
	$filterVal 		= get_request_var('filter');

	$neighbor_rules = get_neighbor_vrf_rules($total_rows,$startRow, $rows,$filterVal,$sortColumn,$sortDirection);
	get_neighbor_vrf_rules_filter();

	$nav = html_nav_bar('neighbor_vrf_rules.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 7, __('VRF Mapping Rules'), 'page', 'main');

	form_start('neighbor_vrf_rules.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'name'            => array('display' => __('Rule Name'),  'align' => 'left', 'sort' => 'ASC', 'tip' => __('The name of this rule.')),
		'id'              => array('display' => __('ID'),         'align' => 'right', 'sort' => 'ASC', 'tip' => __('The internal database ID for this rule.  Useful in performing debugging and automation.')),
		'enabled'         => array('display' => __('Enabled'),    'align' => 'right', 'sort' => 'ASC'),
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (count((array) $neighbor_rules)) {
		foreach ($neighbor_rules as $rule) {

			form_alternate_row('line' . $rule['id'], true);

			form_selectable_cell(filter_value($rule['name'], get_request_var('filter'), 'neighbor_vrf_rules.php?action=edit&id=' . $rule['id'] . '&page=1'), $rule['id']);
			form_selectable_cell($rule['id'], $rule['id'], '', 'text-align:right');
			form_selectable_cell($rule['enabled'] ? __('Enabled') : __('Disabled'), $rule['id'], '', 'text-align:right');
			form_checkbox_cell($rule['name'], $rule['id']);
			form_end_row();
		}
	} else {
		print "<tr><td><em>" . __('No VRF Mapping Rules Found') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (count((array) $neighbor_rules)) { print $nav; }

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($neighbor_rules_actions);
	form_end();
}

function get_neighbor_vrf_rules(&$total_rows = 0, $rowStart = 1, $rowEnd = 25, $filterVal = '', $orderField = 'hostname', $orderDir = 'asc', $output = 'array') {
	
	$sqlWhere 	= '';
    $sqlOrder 	= '';
    $sqlLimit 	= sprintf("limit %d,%d",$rowStart,$rowEnd);
    $result 	= '';
    
    $conditions = array();
    $params = array();

	if ($orderField && ($orderDir != ''))   { $sqlOrder = "order by $orderField $orderDir"; }
	if ($filterVal != '')										{ array_push($conditions,"`name` like ?"); array_push($params, $filterVal); }
		
    $sqlWhere = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $result = db_fetch_assoc_prepared("select * from plugin_neighbor_vrf_rules rules $sqlWhere $sqlOrder $sqlLimit", $params);
    $total_rows = db_fetch_cell_prepared("select count(*) as total_rows from plugin_neighbor_vrf_rules rules $sqlWhere",$params);
    //print "Set total_rows = $total_rows<br>";
    if ($output == 'array') 	{ return($result);}
    elseif ($output == 'json') 	{ return(json_encode($result));}
	
}

function get_neighbor_vrf_rules_filter() {
	global $automation_graph_rules_actions, $config, $item_rows;
	
	html_start_box(__('VRF Mapping Rules'), '100%', '', '3', 'center', 'neighbor_vrf_rules.php?action=edit');

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

function get_vrf_list($output = 'array') {
	
	$vrf = array( 'global' => 'Global Table (no VRF)');
	$result = db_fetch_assoc_prepared("SELECT DISTINCT vrf FROM plugin_neighbor_ipv4_cache ORDER BY vrf ASC", array());
	foreach ((array) $result as $row) {
		if ($row['vrf']) {
			$vrf[$row['vrf']] = $row['vrf'];
		}
	}
	return($vrf);
	
}


function neighbor_display_vrf_match_rule_items($title, $rule_id, $rule_type, $module) {
	global $automation_op_array, $automation_oper, $automation_tree_header_types;

	$items = db_fetch_assoc_prepared('SELECT *
		FROM plugin_neighbor_vrf_match_rule_items
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
	if (count((array) $items)) {
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

			if ($i != count((array) $items)-1) {
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

function neighbor_display_vrf_rule_items($title, $rule_id, $rule_type, $module) {
	
	global $automation_op_array, $automation_oper, $automation_tree_header_types;
	$items = db_fetch_assoc_prepared('SELECT * FROM plugin_neighbor_vrf_rule_items WHERE rule_id = ? ORDER BY sequence', array($rule_id));
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
	if (count((array) $items)) {
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

			if ($i != count((array) $items)-1) {
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

function neighbor_global_vrf_item_edit($rule_id, $rule_item_id, $rule_type) {
	global $config, $fields_neighbor_match_rule_item_edit, $fields_neighbor_graph_rule_item_edit;
	global $fields_neighbor_tree_rule_item_edit, $automation_tree_header_types;
	global $automation_op_array;

	switch ($rule_type) {
	case AUTOMATION_RULE_TYPE_GRAPH_MATCH:
		$title = __('Device Match Rule');
		$item_table = 'plugin_neighbor_vrf_match_rule_items';
		$sql_and = ' AND rule_type=' . $rule_type;
		$tables = array ('host', 'host_templates');
		$neighbor_rule = db_fetch_row_prepared('SELECT * FROM plugin_neighbor_vrf_rules WHERE id = ?', array($rule_id));

		$_fields_rule_item_edit = $fields_neighbor_match_rule_item_edit;
		$query_fields  = get_query_fields('host_template', array('id', 'hash'));
		$query_fields += get_query_fields('host', array('id', 'host_template_id'));

		$_fields_rule_item_edit['field']['array'] = $query_fields;
		$module = 'neighbor_vrf_rules.php';

		break;
	case AUTOMATION_RULE_TYPE_GRAPH_ACTION:
		$title      = __('Create Graph Rule');
		$tables     = array(AUTOMATION_RULE_TABLE_XML);
		$item_table = 'plugin_neighbor_vrf_rule_items';
		$sql_and    = '';

		$neighbor_rule = db_fetch_row_prepared('SELECT *
			FROM plugin_neighbor_vrf_rules
			WHERE id = ?',
			array($rule_id));
		
		//pre_print_r($neighbor_rule,"MooOink:");
		
		$cols = db_get_table_column_types("plugin_neighbor_ipv4_cache");
		//pre_print_r($cols,"Cols:");
		$_fields_rule_item_edit = $fields_neighbor_graph_rule_item_edit;
		foreach ($cols as $col => $rec) {
			if (preg_match("/^id$|_id|_hash|last_seen|_changed|_num|hostname/",$col)) { continue;}
			$fields[$col] = $col;
		}
		//pre_print_r($fields,"Fields:");
		$_fields_rule_item_edit['field']['array'] = $fields;
		$module = 'neighbor_vrf_rules.php';

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

function neighbor_display_vrf_matching_hosts($rule, $rule_type, $url) {
	
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

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filterd') != '') {
		$sql_where = "WHERE (h.hostname LIKE '%" . get_request_var('filterd') . "%' OR h.description LIKE '%" . get_request_var('filterd') . "%' OR ht.name LIKE '%" . get_request_var('filterd') . "%')";
	} else {
		$sql_where = '';
	}

	if (get_request_var('host_status') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_status') == '-2') {
		$sql_where .= ($sql_where != '' ? " AND h.disabled='on'" : "WHERE h.disabled='on'");
	} elseif (get_request_var('host_status') == '-3') {
		$sql_where .= ($sql_where != '' ? " AND h.disabled=''" : "WHERE h.disabled=''");
	} elseif (get_request_var('host_status') == '-4') {
		$sql_where .= ($sql_where != '' ? " AND (h.status!='3' or h.disabled='on')" : "WHERE (h.status!='3' or h.disabled='on')");
	}else {
		$sql_where .= ($sql_where != '' ? ' AND (h.status=' . get_request_var('host_status') . " AND h.disabled = '')" : "WHERE (h.status=" . get_request_var('host_status') . " AND h.disabled = '')");
	}

	if (get_request_var('host_template_id') == '-1') {
		/* Show all items */
	} elseif (get_request_var('host_template_id') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND h.host_template_id=0' : 'WHERE h.host_template_id=0');
	} elseif (!isempty_request_var('host_template_id')) {
		$sql_where .= ($sql_where != '' ? ' AND h.host_template_id=' . get_request_var('host_template_id') : 'WHERE h.host_template_id=' . get_request_var('host_template_id'));
	}

	$host_graphs       = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as graphs FROM graph_local GROUP BY host_id'), 'host_id', 'graphs');
	$host_data_sources = array_rekey(db_fetch_assoc('SELECT host_id, count(*) as data_sources FROM data_local GROUP BY host_id'), 'host_id', 'data_sources');

	/* build magic query, for matching hosts JOIN tables host and host_template */
	$sql_query = 'SELECT h.id AS host_id, h.hostname, h.description, h.disabled,
		h.status, ht.name AS host_template_name
		FROM host AS h
		LEFT JOIN host_template AS ht
		ON (h.host_template_id=ht.id) ';

	$hosts = db_fetch_assoc($sql_query);

	/* get the WHERE clause for matching hosts */
	if ($sql_where != '') {
		$sql_filter = ' AND (' . neighbor_build_vrf_matching_objects_filter($rule['id'], $rule_type) . ')';
	} else {
		$sql_filter = ' WHERE (' . neighbor_build_vrf_matching_objects_filter($rule['id'], $rule_type) .')';
	}

	/* now we build up a new query for counting the rows */
	$rows_query = $sql_query . $sql_where . $sql_filter;
	$total_rows = count((array) db_fetch_assoc($rows_query, false));

	$sortby = get_request_var('sort_column');
	if ($sortby=='hostname') {
		$sortby = 'INET_ATON(hostname)';
	}

	$sql_query = $rows_query .
		' ORDER BY ' . $sortby . ' ' . get_request_var('sort_direction') .
		' LIMIT ' . ($rows*(get_request_var('paged')-1)) . ',' . $rows;
	$hosts = db_fetch_assoc($sql_query, false);

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


function neighbor_display_vrf_object_matches($rule, $url) {
	
	global $config, $item_rows, $config;
	global $neighbor_vrf_object_fields;
	
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

		//error_log("RULE:".print_r($rule,1));
		$sql_order = "";
		
		$rule_options = isset($rule['neighbor_options']) ? $rule['neighbor_options'] : '';
		if($rule_options && $sort_column && !($sort_column == 'type' || $sort_column == 'interface_status')) {
			$sql_order = "ORDER by $sort_column $sort_direction";
		}
		elseif($rule_options && $sort_column && ($sort_column == 'type' || $sort_column == 'interface_status')) {
			$sql_order = "ORDER by $sort_column $sort_direction";
		}
		
		if ($sql_order) {
			$sql_query = neighbor_build_vrf_data_query_sql($rule) . ' ' . $sql_order;
		}
		else {
			$sql_query = neighbor_build_vrf_data_query_sql($rule);
		}
		
		print "neighbor_display_vrf_object_matches() sql_query: $sql_query";
		$start_rec = $rows*(get_request_var('page')-1);
		$all_neighbor_objects = db_fetch_assoc($sql_query);
		$total_rows = count((array) $all_neighbor_objects);
		$neighbor_objects = array_slice($all_neighbor_objects,$start_rec,$rows);
		//error_log(print_r($neighbor_objects,1));
		//error_log("Query: $sql_query");
		//pre_print_r($neighbor_objects,"OINK $sql_query:");
		// Get heading text
		
		$nav = html_nav_bar('neighbor_rules.php?action=edit&id=' . $rule['id'], MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 30, __('Matching Objects'), 'page', 'main');
		print $nav;

		$display_text = array();
		$field_names = array();
		foreach ($neighbor_vrf_object_fields as $field => $title) {
			$display_text[$field][0] = $title;
			$display_text[$field][1] = "ASC";
			$field_names[] = $field;
		}
		//pre_print_r($display_text,"Display:");

		html_header_sort($display_text,$sort_column,$sort_direction,'',$config['url_path']."plugins/neighbor/neighbor_rules.php?action=edit&id=$rule_id");
		//html_header($display_text);

		if (!count((array) $neighbor_objects)) {
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



