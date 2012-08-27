<?php
/**
 * @author Erlend Strømsvik - Ny Media AS
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package auth/saml
 * @version 1.0
 *
 * Authentication Plugin: SAML based SSO Authentication
 *
 * Authentication using SAML2 with SimpleSAMLphp.
 *
 * Based on plugins made by Sergio Gómez (moodle_ssp) and Martin Dougiamas (Shibboleth).
 *
 * 2008-10  Created
 * 2009-07  added new configuration options.  Tightened up the session handling
**/

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

require_once($CFG->libdir.'/authlib.php');

/**
 * SimpleSAML authentication plugin.
**/
class auth_plugin_saml extends auth_plugin_base {

    /**
    * Constructor.
    */
    function auth_plugin_saml() {
		$this->authtype = 'saml';
		$this->config = get_config('auth/saml');
    }

    /**
    * Returns true if the username and password work and false if they are
    * wrong or don't exist.
    *
    * @param string $username The username (with system magic quotes)
    * @param string $password The password (with system magic quotes)
    * @return bool Authentication success or failure.
    */
    function user_login($username, $password) {
	    // if true, user_login was initiated by saml/index.php
	    if(isset($GLOBALS['saml_login']) && $GLOBALS['saml_login']) {
	        unset($GLOBALS['saml_login']);
	        return TRUE;
	    }

	    return FALSE;
    }


    /**
    * Returns the user information for 'external' users. In this case the
    * attributes provided by Identity Provider
    *
    * @return array $result Associative array of user data
    */
    function get_userinfo($username) {
	    if($login_attributes = $GLOBALS['saml_login_attributes']) {
	        $attributemap = $this->get_attributes();
	        $result = array();

	        foreach ($attributemap as $key => $value) {
		        if(isset($login_attributes[$value]) && $attribute = $login_attributes[$value][0]) {
		            $result[$key] = $attribute;
		        } else {
		            $result[$key] = '';
		        }
	        }
	        unset($GLOBALS['saml_login_attributes']);

	        $result["username"] = $username;
	        return $result;
	    }

	    return FALSE;
    }

    /*
    * Returns array containg attribute mappings between Moodle and Identity Provider.
    */
    function get_attributes() {
	    $configarray = (array) $this->config;

        if(isset($configarray->userfields)) {
            $fields = $configarray->userfields;
        }
        else {
        	$fields = array("firstname", "lastname", "email", "phone1", "phone2",
			    "department", "address", "city", "country", "description",
			    "idnumber", "lang", "guid");
        }

	    $moodleattributes = array();
	    foreach ($fields as $field) {
	        if (isset($configarray["field_map_$field"])) {
		        $moodleattributes[$field] = $configarray["field_map_$field"];
	        }
	    }

	    return $moodleattributes;
    }

    /**
    * Returns true if this authentication plugin is 'internal'.
    *
    * @return bool
    */
    function is_internal() {
	    return false;
    }

    /**
    * Returns true if this authentication plugin can change the user's
    * password.
    *
    * @return bool
    */
    function can_change_password() {
	    return false;
    }

    function loginpage_hook() {
	    global $SESSION, $CFG;
	    // Prevent username from being shown on login page after logout
	    $CFG->nolastloggedin = true;
	    $GLOBALS['CFG']->nolastloggedin = true;
    }

    function logoutpage_hook() {
	    if(isset($this->config->dosinglelogout) && $this->config->dosinglelogout) {
	        set_moodle_cookie('nobody');
	        require_logout();
	        redirect($GLOBALS['CFG']->wwwroot.'/auth/saml/index.php?logout=1');
	    }
    }

    /**
    * Prints a form for configuring this authentication plugin.
    *
    * This function is called from admin/auth.php, and outputs a full page with
    * a form for configuring this plugin.
    *
    * @param array $page An object containing all the data for this page.
    */

    function config_form($config, &$err, $user_fields) {
	    global $CFG, $db;

	    $tables = $db->MetaTables('TABLE',true);
	    if(isset($config->supportcourses) &&  $config->supportcourses == 'internal') {
	        $tables = $db->MetaTables('TABLE',true);
	        if(!in_array('course_mapping',$tables)) {
		        $this->create_course_mapping_db($db, $err);
	        }
	        if(!in_array('role_mapping',$tables)) {
		        $this->create_role_mapping_db($db, $err);
	        }
	    }

	    $tables = $db->MetaTables('TABLE',true);
	    $course_mapping = array();
	    $role_mapping = array();
	    if(in_array('course_mapping',$tables)) {
	        require_once ($CFG->dirroot . "/auth/saml/courses.php");
	        $course_mapping = get_course_mapping($db, $err);
	    }
	    if(in_array('role_mapping',$tables)) {
	        require_once ($CFG->dirroot . "/auth/saml/roles.php");
	        $role_mapping = get_role_mapping($db, $err);
	    }
	    require_once ($CFG->dirroot . "/auth/saml/config.php");
    }

    /**
     * A chance to validate form data, and last chance to
     * do stuff before it is inserted in config_plugin
     */
    function validate_form(&$form, &$err) {

	    if(!isset($form->auth_saml_db_reset) && !isset($form->initialize_roles)) {
	        if (!isset ($form->samllib) || !file_exists($form->samllib.'/_autoload.php')) {
		        $err['samllib'] = get_string('auth_saml_errorbadlib', 'auth_saml', $form->samllib);
	        }

            if (isset($form->samlhookfile) && $form->samlhookfile != '' && !file_exists($form->samlhookfile)) {
		        $err['samlhookerror'] = get_string('auth_saml_errorbadhook', 'auth_saml', $form->samlhookfile);
	        }

	        if ($form->supportcourses == 'external') {
		        if ($form->externalcoursemappingdsn == '' || $form->externalcoursemappingsql == '' || $form->externalrolemappingdsn == '' || $form->externalrolemappingsql == '') {   
		            $err['samlexternal'] = get_string('auth_saml_errorsamlexternal', 'auth_saml', $form->samllib);
		        }		 
	        }
	        else if($form->supportcourses == 'internal') {

		        if(!isset($form->deletecourses)) {
		            $lms_course_form_id = array();
		            $saml_course_form_id = array();
		            if (isset($form->update_courses_id)) {
			            foreach ($form->update_courses_id as $course_id) {
			                $course = $form->{'course_' . $course_id};
			                if (!empty($course[1]) && !empty($course[2])) {			    
				                $lms_course_form_id[$course_id] = $course[0];
				                $saml_course_form_id[$course_id] = $course[1] . '_' . $course[2];
			                }
			                else {
				                $err['missed_course_mapping'][$course_id] = $course[0];
			                }
			            }
		            }
		            if (isset($form->new_courses_total)) {
			            for ($i = 0; $i <= $form->new_courses_total; $i++) {
			                $new_course = $form->{'new_course' . $i};
			                if (!empty($new_course[1]) && !empty($new_course[2])) {
				                $lms_course_form_id[$i] = $new_course[0];
				                $saml_course_form_id[$i] = $new_course[1] . '_' . $new_course[2];
			                }
			            }		 
		            }
		            //Comment the next line if you want let duplicate lms mapping
		            $err['course_mapping']['lms'] = array_diff_key($lms_course_form_id, array_unique($lms_course_form_id));

		            $err['course_mapping']['saml'] = array_diff_key($saml_course_form_id, array_unique($saml_course_form_id));
		            if (empty($err['course_mapping']['lms']) && empty($err['course_mapping']['saml'])) {
			            unset($err['course_mapping']);
		            }
		        }

		        if(!isset($form->deleteroles)) {
		            $lms_role_form_id = array();
		            $saml_role_form_id = array();
		            if (isset($form->update_roles_id)) {
			        foreach ($form->update_roles_id as $role_id) {
			            $role = $form->{'role_' . $role_id};		     
			            if (!empty($role[0]) && !empty($role[1])) {
				            $lms_role_form_id[] = $role[0];
				            $saml_role_form_id[] = $role[1];
			            }
			            else {
				            if(!isset($form->deleteroles)) {
				                $err['missed_role_mapping'][$role_id] = $role[0];
				            }
			            }
			        }
		            }
		            if (isset($form->new_roles_total)) {
			            for ($i=0; $i <= $form->new_roles_total; $i++) {
			                $new_course = $form->{'new_role' . $i};		       
			                if (!empty($new_role[1])) {
				                $lms_role_form_id[] = $new_role[0];
				                $saml_role_form_id[] = $new_role[1];
			                }
			            }		       
		            }
		            //$err['role_mapping']['lms'] = array_diff_key($lms_role_form_id, array_unique($lms_role_form_id));
		            $err['role_mapping']['saml'] = array_diff_key($saml_role_form_id, array_unique($saml_role_form_id));
		        }

		        if (empty($err['role_mapping']['lms']) && empty($err['role_mapping']['saml'])) {
		            unset($err['role_mapping']);
		        }
            }
	    }   
    }

    /**
    * Processes and stores configuration data for this authentication plugin.
    *
    *
    * @param object $config Configuration object
    */
    function process_config($config) {
	    global $err, $db, $CFG;

	    if(isset($config->auth_saml_db_reset)) {
	        $sql = "DELETE FROM ".$CFG->prefix."config_plugins WHERE plugin = 'auth/saml';";
            try {
	            $db->execute($sql);
            }
            catch (Exception $e) {
                $err['reset'] = get_string("auth_saml_db_reset_error", "auth_saml");
		        return false;
	        }
	        header('Location: ' . $CFG->wwwroot . '/admin/auth_config.php?auth=saml');
	        exit();
	    }

	    if(isset($config->initialize_roles)) {  
	        global $CFG;
	        $this->initialize_roles($db, $err);
	        header('Location: ' . $CFG->wwwroot . '/admin/auth_config.php?auth=saml#rolemapping');
	        exit();
	    }

        // SAML parameters are in the config variable due all form data is there.
        // We create a new variable and set the values there.
        $saml_param = new stdClass();

	    if (!isset ($config->samllib)) {
	        $saml_param->samllib = '';
	    }
        else {
            $saml_param->samllib = $config->samllib;
        }
	    if (!isset ($config->sp_source)) {
	        $saml_param->sp_source = 'saml';
	    }
        else {
            $saml_param->sp_source = $config->sp_source;
        }
	    if (!isset ($config->dosinglelogout)) {
	        $saml_param->dosinglelogout = false;
	    }
        else {
            $saml_param->dosinglelogout = $config->dosinglelogout;
        }

	    // set to defaults if undefined
	    if (!isset ($config->username)) {
	        $config->username = 'eduPersonPrincipalName';
	    }
        if (!isset ($config->supportcourses)) {
	        $config->supportcourses = 'nosupport';
	    }
	    if (!isset ($config->samlcourses)) {
	        $config->samlcourses = 'schacUserStatus';
	    }
	    if (!isset ($config->samllogoimage) || $config->samllogoimage == NULL) {
	        $config->samllogoimage = 'logo.gif';
	    }
	    if (!isset ($config->samllogoinfo)) {
	        $config->samllogoinfo = 'SAML login';
	    }
	    if (!isset ($config->samllogfile)) {
	        $config->samllogfile = '';
	    }
        if (!isset ($config->samlhookfile)) {
            $config->samlhookfile = $CFG->dirroot . '/auth/saml/custom_hook.php';
        }
	    if (!isset ($config->moodlecoursefieldid)) {
	        $config->moodlecoursefieldid = 'shortname';
	    }
	    if (!isset ($config->ignoreinactivecourses)) {
	        $config->ignoreinactivecourses = '';
	    }
	    if (!isset ($config->externalcoursemappingdsn)) { 
	        $config->externalcoursemappingdsn = ''; 
	    }
	    if (!isset ($config->externalrolemappingdsn)) { 
	        $config->externalrolemappingdsn = ''; 
	    }
	    if (!isset ($config->externalcoursemappingsql)) { 
	        $config->externalcoursemappingsql = ''; 
	    }
	    if (!isset ($config->externalrolemappingsql)) { 
	        $config->externalrolemappingsql = ''; 
	    }

        // Save saml settings in a file        
    	$saml_param_encoded = json_encode($saml_param);

        file_put_contents(dirname(__FILE__) . '/saml_config.php', $saml_param_encoded);

        // Also adding this parameters in database but no need it really.
	    set_config('samllib',	      $saml_param->samllib,	        'auth/saml');
	    set_config('sp_source',       $saml_param->sp_source,	    'auth/saml');
	    set_config('dosinglelogout',  $saml_param->dosinglelogout,	'auth/saml');

        // Save plugin settings
	    set_config('username',	      $config->username,	'auth/saml');
	    set_config('supportcourses',  $config->supportcourses,	'auth/saml');
	    set_config('samlcourses',     $config->samlcourses,	'auth/saml');
	    set_config('samllogoimage',   $config->samllogoimage,	'auth/saml');
	    set_config('samllogoinfo',    $config->samllogoinfo,	'auth/saml');
	    set_config('samllogfile',         $config->samllogfile,	'auth/saml');
	    set_config('samlhookfile',        $config->samlhookfile,	'auth/saml');
	    set_config('moodlecoursefieldid',   $config->moodlecoursefieldid,   'auth/saml');
	    set_config('ignoreinactivecourses', $config->ignoreinactivecourses, 'auth/saml');

	    if($config->supportcourses == 'external') {
	        set_config('externalcoursemappingdsn',  $config->externalcoursemappingdsn,	'auth/saml');
	        set_config('externalrolemappingdsn',    $config->externalrolemappingdsn,	'auth/saml');
	        set_config('externalcoursemappingsql',  $config->externalcoursemappingsql,	'auth/saml');
	        set_config('externalrolemappingsql',    $config->externalrolemappingsql,	'auth/saml');
	    }
	    else if($config->supportcourses == 'internal') {

	    $tables = $db->MetaTables('TABLE',true);
		if(!in_array('course_mapping',$tables)) {
		    $this->create_course_mapping_db($db, $err);
		}
		if(!in_array('role_mapping',$tables)) {
		    $this->create_role_mapping_db($db, $err);
		}

		//COURSE MAPPINGS
		//Delete mappings
		if (isset($config->deletecourses)) {
		    if(isset($config->course_mapping_id)) {
			    foreach ($config->course_mapping_id as $course => $value) {
			        $sql = "DELETE FROM course_mapping WHERE course_mapping_id='". $value ."';";
			        $rs = $db->Execute($sql);
			        if($rs === false) {
				        $err['course_mapping_db'][] = get_string("auth_saml_error_executing", "auth_saml").$sql;
			        }
			    }
		    }
		} else {
		    //Update mappings
		    if (isset($config->update_courses_id) && empty($err['course_mapping'])) {
			    foreach($config->update_courses_id as $course_id) {
			        $course = $config->{'course_' . $course_id};
			        $sql = "UPDATE course_mapping SET lms_course_id='".$course[0]."', saml_course_id='".$course[1]."', saml_course_period='".$course[2]."' where course_mapping_id='". $course_id ."';"; 
			        $rs = $db->Execute($sql);
			        if($rs === false) {
				        $err['course_mapping_db'][] = get_string("auth_saml_error_executing", "auth_saml").$sql;
			        }
			    }
		    }

		    //New courses mapping
		    if (isset($config->new_courses_total) && empty($err['course_mapping'])) {
			    for ($i = 0; $i <= $config->new_courses_total; $i++) {
			        $new_course = $config->{'new_course' . $i};
			        if (!empty($new_course[1]) && !empty($new_course[2])) {
				        $sql = "INSERT INTO course_mapping (lms_course_id, saml_course_id, saml_course_period) values('".$new_course[0]."', '".$new_course[1]."', '".$new_course[2]."');";
				        $rs = $db->Execute($sql);
				        if($rs === false) {
				            $err['course_mapping_db'][] = get_string("auth_saml_error_executing", "auth_saml").$sql;			
				        }
			        }
			    }
		    }
		}
		//END-COURSE MAPPINGS

		//ROLE MAPPINGS
		//Deleting roles
		if (isset($config->deleteroles)) {
		    if(isset($config->role_mapping_id)) {
 		        foreach ($config->role_mapping_id as $role => $value) {
			        $sql = "DELETE FROM role_mapping WHERE saml_role='" . $value . "';";
			        $rs = $db->Execute($sql);
			        if($rs === false) {
			            $err['role_mapping_db'][] = get_string("auth_saml_error_executing", "auth_saml").$sql;
			        }
			    }
		    }
		} else {
		    //Updating roles
		    if (isset($config->update_roles_id) && empty($err['roles_mapping'])) {
			    foreach($config->update_roles_id as $role_id) {
			        $role = $config->{'role_' . $role_id};
			        $sql = "UPDATE role_mapping SET lms_role='" . $role[0] . "', saml_role='" . $role[1] . "' where saml_role='" . $role_id . "';"; 
			        $rs = $db->Execute($sql);
			        if($rs === false) {
				        $err['role_mapping_db'][] = get_string("auth_saml_error_executing", "auth_saml").$sql;
			        }
			    }
		    }
		    //New roles mapping
		    if (isset($config->new_roles_total) && empty($err['roles_mapping'])) {
			    for ($i = 0; $i <= $config->new_roles_total; $i++) {
			        $new_role = $config->{'new_role' . $i};
			        if (!empty($new_role[0]) && !empty($new_role[1])) {
				        $sql = "INSERT INTO role_mapping (lms_role, saml_role) values('".$new_role[0]."', '".$new_role[1]."');";
				        $rs = $db->Execute($sql);
				        if($rs === false) {
				            $err['role_mapping_db'][] = get_string("auth_saml_error_executing", "auth_saml").$sql;
				        }
			        }
			    }
		    }
		}
		if(isset($rs)) {
		    unset($rs);
		}

		if(isset($err['role_mapping_db']) || isset($err['course_mapping_db'])) {
		    return false;
		}
	
		//END-COURSE MAPPINGS
	}
	return true;
    }

    /**
    * Cleans and returns first of potential many values (multi-valued attributes)
    *
    * @param string $string Possibly multi-valued attribute from Identity Provider
    */
    function get_first_string($string) {
	    $list = split( ';', $string);
	    $clean_string = trim($list[0]);

	    return $clean_string;
    }


    function sync_roles($user) {

	    global $CFG, $SAML_COURSE_INFO, $err;

	    if($this->config->supportcourses != 'nosupport' ) {

	            if(!isset($this->config->moodlecoursefieldid)) {
		    $this->config->moodlecoursefieldid = 'shortname';
	        }
	        try {  
	            foreach($SAML_COURSE_INFO->mapped_roles as $role) {		       
	                $moodle_role = get_record('role', 'shortname', $role);
	                if($moodle_role) {
		                $new_course_ids_with_role = array();
		                $delete_course_ids_with_role = array();
		                if (isset($SAML_COURSE_INFO->mapped_courses[$role])) {
		                    if(isset($SAML_COURSE_INFO->mapped_courses[$role]['active'])) {
			                    $new_course_ids_with_role = array_keys($SAML_COURSE_INFO->mapped_courses[$role]['active']);
		                    }
		                    if(isset($SAML_COURSE_INFO->mapped_courses[$role]['inactive'])) {
			                    $delete_course_ids_with_role = array_keys($SAML_COURSE_INFO->mapped_courses[$role]['inactive']);
		                    }
		                }
		                  
		                foreach($new_course_ids_with_role as $course_identify) {
		                    if($course = get_record('course', $this->config->moodlecoursefieldid, $course_identify)) {
			                    $course_context = get_context_instance(CONTEXT_COURSE, $course->id);
			                    role_assign($moodle_role->id, $user->id, 0, $course_context->id, 0, 0, 0, 'saml');
		                    }    
		                }
		                if(!$this->config->ignoreinactivecourses) {
		                    foreach($delete_course_ids_with_role as $course_identify) {
			                    if($course = get_record('course', $this->config->moodlecoursefieldid, $course_identify)) {
			                        $course_context = get_context_instance(CONTEXT_COURSE, $course->id);
			                        role_unassign($moodle_role->id, $user->id, 0, $course_context->id);
			                    }
		                    }
		                }
	                }
	                else {
		                $err['enrollment'][] = get_string("auth_saml_error_role_not_found", "auth_saml", $role);
	                }
	            }
	        }
	        catch (Exception $e) {
		        $err['enrollment'][] = $e->getMessage();
	        }
	        unset($SAML_COURSE_INFO);
	    }
    }

  /**
    * Create course_mapping table in Moodle database.
    *
    */
    function create_course_mapping_db($db, &$err) {
	if($db->databaseType == 'postgres7') {
	    $rs = $db->Execute('CREATE TABLE course_mapping (
							course_mapping_id    serial  PRIMARY KEY,
							saml_course_id	     varchar(100)    NOT NULL,
							saml_course_period   varchar(20)     NOT NULL,
								lms_course_id	     varchar(100)    NOT NULL);
				');
	}
	else if($db->databaseType == 'oci8' || $db->databaseType == 'oci8po') {
	    $rs = $db->Execute('CREATE TABLE course_mapping (
							course_mapping_id    numeric(10),
							saml_course_id	     varchar(100)    NOT NULL,
							saml_course_period   varchar(20)     NOT NULL,
								lms_course_id	     varchar(100)    NOT NULL,
				    CONSTRAINT course_mapping_pk PRIMARY KEY (course_mapping_id));
						');
	    if($rs !== false) {
		$rs = $db->Execute('CREATE SEQUENCE course_mapping_seq 
					start with 1 
					increment by 1 
					nomaxvalue; 
			    ');
	    }
	    if($rs !== false) {
		$rs = $db->Execute('CREATE TRIGGER course_mapping_trigger
					before insert on course_mapping
					for each row
					begin
					select course_mapping_seq.nextval into :new.course_mapping_id from dual;
					end;
			    ');
	    }
	}
	else {
	    $rs = $db->Execute('CREATE TABLE course_mapping (
								course_mapping_id	INTEGER AUTO_INCREMENT UNIQUE NOT NULL,
								saml_course_id	    varchar(100) NOT NULL,
								saml_course_period  varchar(20) NOT NULL,
								lms_course_id	 varchar(100) NOT NULL,
								PRIMARY KEY(course_mapping_id));
							');
	}
	if ($rs === false){
	    $err['course_mapping_db'][] = get_string("auth_saml_error_creating_course_mapping", "auth_saml");
	}	
	else {
	    echo '<span class="notifysuccess">';
	    print_string("auth_saml_sucess_creating_course_mapping", "auth_saml");
	    echo '</span><br>';	
	}
	return $rs;
    }

    /**
    * Create role_mapping table in Moodle database.
    *
    */
    function create_role_mapping_db($db, &$err) {
	if($db->databaseType == 'postgres7') {
	    $rs = $db->Execute('CREATE TABLE role_mapping (
				saml_role   varchar(50) PRIMARY KEY,
				lms_role    varchar(50) NOT NULL)
		');
	}
	else if($db->databaseType == 'oci8' || $db->databaseType == 'oci8po') {
	    $rs = $db->Execute('CREATE TABLE role_mapping (
				saml_role   varchar(50) NOT NULL,
				lms_role    varchar(50) NOT NULL,
				CONSTRAINT role_mapping_pk PRIMARY KEY (saml_role));
			');
	}
	else {
	    $rs = $db->Execute('CREATE TABLE role_mapping (
				saml_role   varchar(50) NOT NULL,
				lms_role    varchar(50) NOT NULL,
				PRIMARY KEY (saml_role)); 
			');
	}
	if ($rs === false){
	    $err['role_mapping_db'][] = get_string("auth_saml_error_creating_role_mapping", "auth_saml");
	}
	else { 
	    echo '<span class="notifysuccess">';
	    print_string("auth_saml_sucess_creating_role_mapping", "auth_saml");
	    echo '</span><br>';		
	}
	return $rs;
    }


    function initialize_roles($db, &$err) {
	$sqls = array();
	$sqls[] = "DELETE FROM role_mapping;";
	$sqls[] = "INSERT INTO role_mapping (lms_role, saml_role) values ('editingteacher','teacher')";
	$sqls[] = "INSERT INTO role_mapping (lms_role, saml_role) values ('editingteacher','instructor')";
	$sqls[] = "INSERT INTO role_mapping (lms_role, saml_role) values ('editingteacher','mentor')";
	$sqls[] = "INSERT INTO role_mapping (lms_role, saml_role) values ('student','student')";
	$sqls[] = "INSERT INTO role_mapping (lms_role, saml_role) values ('student','learner')";
	$sqls[] = "INSERT INTO role_mapping (lms_role, saml_role) values ('user','member')";
	$sqls[] = "INSERT INTO role_mapping (lms_role, saml_role) values ('admin','admin')";
	foreach($sqls as $sql) {
	    $rs = $db->Execute($sql);
	    if($rs === false) {
		$err['role_mapping_db'][] = get_string("auth_saml_error_creating_role_mapping", "auth_saml");
		break;
	    }
	}
	return $rs;
    }	   
}

?>
