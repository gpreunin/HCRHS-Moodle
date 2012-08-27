<?php
function get_moodle_courses() {
    $moodle_courses = array();
    foreach(get_courses() as $course) {
        if(isset($config->moodlecoursefieldid) && $config->moodlecoursefieldid == 'idnumber') {
            $course_identify = $course->idnumber;
        }
        else {
            $course_identify = $course->shortname;
        }
        $moodle_courses[] = $course_identify;
    }
    return $moodle_courses;
}

function get_course_mapping(&$db, &$err) {

    $course_mapping = array();
    $rs = $db->Execute('SELECT course_mapping_id, saml_course_id, saml_course_period, lms_course_id FROM course_mapping');
    if ($rs === false){
        $err['course_mapping_db'][] = get_string("auth_saml_error_executing_course_mapping_query", "auth_saml");
    } else {
        $saml_courses = $rs->GetAll();
        //creating the courses mapping
        foreach ($saml_courses as $tuple){
            $tuple = array_change_key_case($tuple,CASE_LOWER);
            $course_mapping[$tuple['lms_course_id']] = array(
                'course_mapping_id' => $tuple['course_mapping_id'],
                'saml_course_id' => $tuple['saml_course_id'],
                'saml_course_period' => $tuple['saml_course_period'],
            );
        }
		unset($rs);
		unset($saml_courses);
    }
    return $course_mapping;
}

function get_course_mapping_for_sync(&$err, $config) {

    $course_mapping = array();
    if($config->supportcourses == 'external') {
        require_once ("DBNewConnection.php"); 
        $db_mapping = DBNewConnection($config->externalcoursemappingdsn);
        $rs = false;
        if($db_mapping) {
            $db_mapping->SetFetchMode(ADODB_FETCH_ASSOC);
            $rs = $db_mapping->Execute($config->externalcoursemappingsql);
            $db_mapping->Disconnect();
        }
    }
    else {
        global $db;
        $rs = $db->Execute('SELECT saml_course_id, saml_course_period, lms_course_id FROM course_mapping');
    }    
    if ($rs === false){
        $err['course_mapping_db'][] = get_string("auth_saml_error_executing_course_mapping_query", "auth_saml");
    } else {
        $res_array = $rs->GetAll();
        //creating the courses mapping
        foreach ($res_array as $tuple) {
            $tuple = array_change_key_case($tuple,CASE_LOWER);
            if(empty($tuple['saml_course_id']) || empty($tuple['saml_course_period']) || empty($tuple['lms_course_id']))  {
                $err['role_mapping_db'][] = "<p>" . get_string("auth_saml_error_attribute_course_mapping", "auth_saml") . "</p><p>saml_course_id:" . $tuple['saml_course_id'] . " saml_course_period: " . $tuple['saml_course_period'] . " lms_course_id:" . $tuple['lms_course_id'] . "</p>";
            }
            else {
                if(isset($course_mapping[$tuple['saml_course_id']][$tuple['saml_course_period']])) {
                    $err['course_mapping_db'][] = get_string('auth_saml_duplicated_saml_data', "auth_saml").' saml_course_id:'.$tuple['saml_course_id'].' saml_course_period:'.$tuple['saml_course_period'];
                }
                else {
		    $course_mapping[$tuple['saml_course_id']][$tuple['saml_course_period']] = $tuple['lms_course_id']; 
                }
            }		
        }
		unset($res_array);
		unset($rs);
    }
    return $course_mapping;
}


function print_course_mapping_options($course_mapping, $config, $err) {

    if(isset($err['course_mapping_db'])) {
        foreach ($err['course_maping_db'] as $value) {
           echo '<tr><td colspan="4" style="color: red;text-align:center">';
           echo $value;  
           echo '</td></tr>';         
        }
    }

    if (array_key_exists('course_mapping', $err)) {
        echo '<tr><td colspan="4" style="color: red;text-align:center">';
        if (!empty($err['course_mapping']['saml'])) {
            echo "<p>" . get_string("auth_saml_duplicated_saml_data", "auth_saml") . implode(', ', $err['course_mapping']['saml']) . "</p>";
        }
        if (!empty($err['course_mapping']['lms'])) {
            echo get_string("auth_saml_duplicated_lms_data", "auth_saml") . implode(', ', $err['course_mapping']['lms']);
        }	
        echo '</td></tr>';
    }
    if (array_key_exists('missed_course_mapping', $err)) {
        echo '<tr><td colspan="4" style="color: red;text-align:center">';
        echo get_string("auth_saml_missed_data", "auth_saml") . implode(', ', array_unique($err['missed_course_mapping']));
        echo '</td></tr>';
    }

    echo '<tr><td colspan="2" style="padding-left: 44px;">Moodle Course Id</td><td>SAML Course Id</td><td>SAML Course Period</td></tr>';

    //if this is a GET (no errors) read the values from the database
    $read_from_db_only = !isset($_POST['new_courses_total']);

    $moodle_courses = get_moodle_courses();
    foreach ($moodle_courses as $mcourse) {
        if (array_key_exists($mcourse, $course_mapping)) {
            $course_mapping_id = $course_mapping[$mcourse]['course_mapping_id'];
            $saml_course_id = $course_mapping[$mcourse]['saml_course_id'];
            $saml_course_period = $course_mapping[$mcourse]['saml_course_period'];
	        echo '<tr '.((isset($err['course_mapping']['lms']) && in_array($_POST['course_' . $course_mapping_id][0], $err['course_mapping']['lms']))
            || (isset($err['course_mapping']['saml']) && in_array($_POST['course_' . $course_mapping_id][1].'_'.$_POST['course_' . $course_mapping_id][2], $err['course_mapping']['saml']))
            || (isset($err['missed_course_mapping']) && array_key_exists($course_mapping_id, $err['missed_course_mapping']))
            ? 'style="background:red;"' : '').'>';

            echo '<td colspan="2"><input style="margin-right: 20px;" type="checkbox"';
			echo 'name="course_mapping_id[]" value="'.$course_mapping_id.'">';
            echo('<input type="hidden" name="update_courses_id[]" value="' . $course_mapping_id . '">');
            echo '<select name="course_'. $course_mapping_id .'[]" >';
            foreach ($moodle_courses as $mcourse2) {
                echo '<option value="'. $mcourse2 .'" '.((!$read_from_db_only && $_POST['course_' . $course_mapping_id][0] == $mcourse2) || ($read_from_db_only && $mcourse2 == $mcourse) ? 'selected="selected"' : '') .' >'.$mcourse2.'</option>';
            }
            echo '</select></td>';
            $course_name = ($read_from_db_only ? $saml_course_id : $_POST['course_' . $course_mapping_id][1]);
            $course_period = ($read_from_db_only ? $saml_course_period : $_POST['course_' . $course_mapping_id][2]);
            echo '<td><input type="text" name="course_'. $course_mapping_id .'[]" value="' . $course_name . '" /></td>';
            echo '<td><input type="text" name="course_'. $course_mapping_id .'[]" value="' . $course_period . '" /></td>';
        	echo '</tr>';
        }
    }

    //New mappings
    echo '<tr><td colspan="4"><hr /></td></tr>';
    $i = 0;
    if (!$read_from_db_only) {
	    while ($i <= $_POST['new_courses_total']) {
		echo '<tr '.((empty($_POST['new_course'.$i][1]) && empty($_POST['new_course' . $i][2]))? 'style="display:none;"' : ((isset($err['course_mapping']['lms']) && in_array($_POST['new_course' . $i][0], $err['course_mapping']['lms'])) 
        || (isset($err['course_mapping']['saml']) && in_array($_POST['new_course' . $i][1].'_'.$_POST['new_course' . $i][2], $err['course_mapping']['saml'])) ? 'style="background:red;"' : '')) .' >';
	        echo '<td colspan="2" style="padding-left: 38px;"><select id="newcourse_select" name="new_course' . $i . '[]">';
	        foreach ($moodle_courses as $mcourse) {
	            $is_selected = $_POST['new_course' . $i][0] === $mcourse; 
	            echo '<option value="'. $mcourse .'" ' . ($is_selected ? 'selected="selected"' : '') . ' >'.$mcourse.'</option>';
	        }
	        echo '</select>';
	        echo '<input id="new_courses_total" type="hidden" name="new_courses_total" value="' . $i . '" /></td>';
	        echo '<td><input id="newcourse_saml_id" type="text" name="new_course' . $i . '[]" value="' . $_POST['new_course' . $i][1] . '" /></td>';
	        echo '<td><input id="newcourse_saml_period" type="text" name="new_course' . $i . '[]" value="'. $_POST['new_course' . $i][2] . '" /></td>'; 
	        echo '</tr>';
		$i++;
	    }
    }

    echo '<tr><td colspan="2" style="padding-left: 38px;"><select id="newcourse_select" name="new_course' . $i . '[]">';
    foreach ($moodle_courses as $mcourse) {
        echo '<option value="' . $mcourse . '"  >' . $mcourse . '</option>';
    }
    echo '</select>';
    echo '<input id="new_courses_total" type="hidden" name="new_courses_total" value="' . $i . '" /></td>';
    echo '<td><input id="newcourse_saml_id" type="text" name="new_course' . $i . '[]" value="" /></td>';
    echo '<td><input id="newcourse_saml_period" type="text" name="new_course' . $i . '[]" value="" />'; 
    echo '<input type="button" name="new" value="+" onclick="addNewField(\'newcourses\',\'new_course\',\'course\')" /></td></tr>';
}
?>
