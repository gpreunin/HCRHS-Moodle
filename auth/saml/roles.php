<?php

function get_moodle_roles() {
    $moodle_roles = array();
    foreach (get_all_roles() as $role) {
        $moodle_roles[] = $role->shortname;
    }
    return $moodle_roles;
}

function get_role_mapping(&$db, &$err) {
    $role_mapping = array();
    $rs = $db->Execute('SELECT * from role_mapping');
    if ($rs === false) {
        $err['role_mapping_db'][] = get_string("auth_saml_error_executing_role_mapping_query", "auth_saml");
    } else {
        $saml_roles = $rs->GetAll();
        foreach ($saml_roles as $role){
            $role = array_change_key_case($role,CASE_LOWER);
            $role_mapping[$role['saml_role']] = $role['lms_role'];
        }
		unset($rs);
		unset($saml_roles);
    }
    return $role_mapping;
}


function get_role_mapping_for_sync(&$err, $config) {

    $role_mapping = array();
    if($config->supportcourses == 'external') {
        require_once ("DBNewConnection.php"); 
        $db_mapping = DBNewConnection($config->externalrolemappingdsn);
        $rs = false;
        if($db_mapping) {
            $db_mapping->SetFetchMode(ADODB_FETCH_ASSOC);
            $rs = $db_mapping->Execute($config->externalrolemappingsql);
            $db_mapping->Disconnect();
        }
    }
    else {
        global $db;
        $rs = $db->Execute('SELECT lms_role, saml_role from role_mapping');
    }
    if ($rs === false) {
        $err['role_mapping_db'][] = get_string("auth_saml_error_executing_role_mapping_query", "auth_saml");
    } else {
        $saml_roles = $rs->GetAll();

        foreach ($saml_roles as $role){
            $role = array_change_key_case($role,CASE_LOWER);
            if(empty($role['lms_role']) || empty($role['saml_role'])) {
                $err['role_mapping_db'][] = "<p>" . get_string("auth_saml_error_attribute_role_mapping", "auth_saml") . "</p><p>saml_role: " . $role['saml_role'] . " lms_role: " . $role['lms_role'] . "</p>";
            }
            else {
                if(isset($role_mapping[$role['saml_role']])) {
                    $err['role_mapping_db'][] = get_string('auth_saml_duplicated_saml_data', "auth_saml").' saml_role:'.$role['saml_role'];
                }
                else {
                    $role_mapping[$role['saml_role']] = $role['lms_role'];
                }
            }
        }
		unset($rs);
		unset($saml_roles);
    }
    return $role_mapping;
}


function print_role_mapping_options($role_mapping, $config, $err) {
    
    if(isset($err['role_mapping_db'])) {
        foreach ($err['role_mapping_db'] as $value) {
            echo '<tr><td colspan="4" style="color: red;text-align:center">';
            echo $value;
            echo '</td></tr>';
        }
    }

    if (array_key_exists('role_mapping', $err)) {
        echo '<tr><td colspan="4" style="color: red;text-align:center">';
        if (!empty($err['role_mapping']['saml'])) {
            echo "<p>" . get_string("auth_saml_duplicated_saml_data", "auth_saml") . implode(', ', $err['role_mapping']['saml']) . "</p>";
        }
        if (!empty($err['role_mapping']['lms'])) {
            echo get_string("auth_saml_duplicated_lms_data", "auth_saml") . implode(', ', $err['role_mapping']['lms']);
        }	
        echo '</td></tr>';
    }

    if (array_key_exists('missed_role_mapping', $err)) {
        echo '<tr><td colspan="4" style="color: red;text-align:center">';
        echo get_string("auth_saml_missed_data", "auth_saml") . implode(', ', array_unique($err['missed_role_mapping']));
        echo '</td></tr>';
    }

    echo '<tr><td></td><td>Moodle Role</td><td>SAML Role</td></tr>';

    //if this is a GET (no errors) only read the values from the database
    $read_from_db_only = !isset($_POST['new_roles_total']);

    //existing roles
    $moodle_roles = get_moodle_roles();
    foreach ($role_mapping as $saml_role => $mrole) {
        	echo '<tr '.((isset($err['role_mapping']['lms']) && in_array($_POST['role_' . $saml_role][0], $err['role_mapping']['lms']))
                || (isset($err['role_mapping']['saml']) && in_array($_POST['role_' . $saml_role][1], $err['role_mapping']['saml']))
                || (isset($err['missed_role_mapping']) && array_key_exists($saml_role, $err['missed_role_mapping'])) ? 'style="background:red;"' : '').'>';
            echo '<td colspan="2"><input style="margin-right: 20px;" type="checkbox" name="role_mapping_id[]" value="' . $saml_role . '">';
            echo '<input type="hidden" name="update_roles_id[]" value="' . $saml_role . '">';
            echo '<select name="role_'. $saml_role .'[]" >';
            foreach ($moodle_roles as $mrole2) {
                echo '<option value="'. $mrole2 .'" '.((!$read_from_db_only && $_POST['role_' . $saml_role][0] == $mrole2) || ($read_from_db_only && $mrole2 == $mrole) ? 'selected="selected"' : '') .' >' . $mrole2 . '</option>';
            }
            echo '</select></td>';
            $role_name = ($read_from_db_only ? $saml_role : $_POST['role_' . $saml_role][1]);
            echo '<td><input type="text" name="role_'. $saml_role .'[]" value="' . $role_name . '" /></td>';
		    echo '<td></td>';
        	echo '</tr>';
    }

    //New roles
    echo '<tr><td colspan="4"><hr /></td></tr>';
    $i = 0;
    if (!$read_from_db_only) {

        //we have a POST request
        while ($i <= $_POST['new_roles_total']) {
	    echo '<tr '.(empty($_POST['new_role' . $i][1])? 'style="display:none;"': ((isset($err['role_mapping']['lms']) && in_array($_POST['new_role' . $i][0], $err['role_mapping']['lms'])) || (isset($err['role_mapping']['saml']) && in_array($_POST['new_role' . $i][1], $err['role_mapping']['saml'])) ? 'style="background:red;"' : '')) .'>';
            echo '<td colspan="2" style="padding-left: 38px;"><select id="newrole_select" name="new_role' . $i . '[]">';
            foreach ($moodle_roles as $mrole) {
                $is_selected = $_POST['new_role' . $i][0] === $mrole; 
                echo '<option value="'. $mrole .'" ' . ($is_selected ? 'selected="selected"' : '') . ' >' . $mrole . '</option>';
            }
            echo '</select>';
            echo '<input id="new_roles_total" type="hidden" name="new_roles_total" value="' . $i . '" /></td>';
            echo '<td><input id="newrole_saml_id" type="text" name="new_role' . $i . '[]" value="' . $_POST['new_role' . $i][1] . '" /></td>';
	    echo '<td></td>';
	    echo '</tr>';
           $i++;
           }
    }

    echo '<tr><td colspan="2" style="padding-left: 39px;"><select id="newrole_select" name="new_role' . $i . '[]">';
    foreach ($moodle_roles as $mrole) {
        echo '<option value="' . $mrole . '"  >' . $mrole . '</option>';
    }
    echo '</select>';
    echo '<input id="new_roles_total" type="hidden" name="new_roles_total" value="' . $i . '" /></td>';
    echo '<td><input id="newrole_saml_id" type="text" name="new_role' . $i . '[]" value="" />';
    echo '<input type="button" name="new" value="+" onclick="addNewField(\'newroles\',\'new_role\',\'role\')" /></td></tr>';
    echo '<td></td>';
}

?>
