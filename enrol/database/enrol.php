<?php  // $Id$

require_once($CFG->dirroot.'/enrol/enrol.class.php');

class enrolment_plugin_database {

    var $log;

    /**
     * Courses with enrollments that
     * have been processed during the
     * sync_enrolments() method
     *
     * @var array
     **/
    var $processed = array();

    /**
     * Backup files created
     *
     * @var array
     **/
    var $backupfiles = array();

/**
 * Run any cleanup routines
 *
 * @return void
 **/
function __destruct() {
    $this->delete_backup_files();
}

/*
 * For the given user, let's go out and look in an external database
 * for an authoritative list of enrolments, and then adjust the
 * local Moodle assignments to match.
 */
function setup_enrolments(&$user) {
    global $CFG;

    // NOTE: if $this->enrol_connect() succeeds you MUST remember to call
    // $this->enrol_disconnect() as it is doing some nasty vodoo with $CFG->prefix
    $enroldb = $this->enrol_connect();
    if (!$enroldb) {
        error_log('[ENROL_DB] Could not make a connection');
        return;
    }

    // If we are expecting to get role information from our remote db, then
    // we execute the below code for every role type.  Otherwise we just
    // execute it once with null (hence the dummy array).
    $roles = !empty($CFG->enrol_db_remoterolefield) && !empty($CFG->enrol_db_localrolefield)
        ? get_records('role')
        : array(null);

    //error_log('[ENROL_DB] found ' . count($roles) . ' roles:');

    foreach($roles as $role) {

        //error_log('[ENROL_DB] setting up enrolments for '.$role->shortname);

        /// Get the authoritative list of enrolments from the external database table
        /// We're using the ADOdb functions natively here and not our datalib functions
        /// because we didn't want to mess with the $db global

        $useridfield = $enroldb->quote($user->{$CFG->enrol_localuserfield});

        list($have_role, $remote_role_name, $remote_role_value) = $this->role_fields($enroldb, $role);

        /// Check if a particular role has been forced by the plugin site-wide
        /// (if we aren't doing a role-based select)
        if (!$have_role && $CFG->enrol_db_defaultcourseroleid) {
            $role = get_record('role', 'id', $CFG->enrol_db_defaultcourseroleid);
        }

        /// Whether to fetch the default role on a per-course basis (below) or not.
        $use_default_role = !$role;

        /*
        if ($have_role) {
            error_log('[ENROL_DB] Doing role-specific select from db for role: '.$role->shortname);
        } elseif ($use_default_role) {
            error_log('[ENROL_DB] Using course default for roles - assuming that database lists defaults');
        } else {
            error_log('[ENROL_DB] Using config default for roles: '.$role->shortname);
        }*/

        if ($rs = $enroldb->Execute("SELECT {$CFG->enrol_remotecoursefield} as enrolremotecoursefield".
                                            (!empty($CFG->enrol_db_remotetimestartfield) ? ", $CFG->enrol_db_remotetimestartfield as timestart" : '').
                                            (!empty($CFG->enrol_db_remotetimeendfield) ? ", $CFG->enrol_db_remotetimeendfield as timeend" : '').
                                     " FROM {$CFG->enrol_dbtable}
                                      WHERE {$CFG->enrol_remoteuserfield} = " . $useridfield .
                                        (isset($remote_role_name, $remote_role_value) ? ' AND '.$remote_role_name.' = '.$remote_role_value : ''))) {

            // We'll use this to see what to add and remove
            $existing = $role
                ? get_records_sql("
                    SELECT * FROM {$CFG->prefix}role_assignments
                    WHERE userid = {$user->id}
                     AND roleid = {$role->id}")
                : get_records('role_assignments', 'userid', $user->id);

            if (!$existing) {
                $existing = array();
            }


            if (!$rs->EOF) {   // We found some courses

                //$count = 0;
                $courselist = $enroltimes = array();
                while ($fields_obj = rs_fetch_next_record($rs)) {         // Make a nice little array of courses to process
                    $fields_obj = (object)array_change_key_case((array)$fields_obj , CASE_LOWER);
                    $courselist[] = $fields_obj->enrolremotecoursefield;

                    if (isset($fields_obj->timestart)) {
                        $enroltimes[$fields_obj->enrolremotecoursefield]['timestart'] = $fields_obj->timestart;
                    } else {
                        $enroltimes[$fields_obj->enrolremotecoursefield]['timestart'] = 0;
                    }
                    if (isset($fields_obj->timeend)) {
                        $enroltimes[$fields_obj->enrolremotecoursefield]['timeend'] = $fields_obj->timeend;
                    } else {
                        $enroltimes[$fields_obj->enrolremotecoursefield]['timeend'] = 0;
                    }
                    //$count++;
                }
                rs_close($rs);

                //error_log('[ENROL_DB] Found '.count($existing).' existing roles and '.$count.' in external database');

                foreach ($courselist as $coursefield) {   /// Check the list of courses against existing
                    $course = get_record('course', $CFG->enrol_localcoursefield, addslashes($coursefield));
                    if (!is_object($course)) {
                        if (!empty($CFG->enrol_restore_coursetemplate) and !empty($CFG->enrol_coursetemplate)) {
                            if (debugging('',DEBUG_ALL)) {
                                error_log( "Course $coursefield does not exist and restore is enabled, but not allowed on login, skipping") ;
                            }
                            continue; // next foreach course
                        }
                        if (empty($CFG->enrol_db_autocreate)) { // autocreation not allowed
                            if (debugging('',DEBUG_ALL)) {
                                error_log( "Course $coursefield does not exist, skipping") ;
                            }
                            continue; // next foreach course
                        }
                        // ok, now then let's create it!
                        error_log("Creating Course $coursefield...");
                        if ($courseid = $this->create_course($enroldb, $coursefield, true)
                            and $course = get_record( 'course', 'id', $courseid)) {
                            // we are skipping fix_course_sortorder()
                            error_log("created.");
                        } else {
                            error_log("failed.");
                            continue; // nothing left to do...
                        }
                    } else if (!empty($CFG->enrol_db_autoupdate)) {
                        $this->update_course($enroldb, $course, $coursefield);
                    }

                    // if the course is hidden and we don't want to enrol in hidden courses
                    // then just skip it
                    if (!$course->visible and $CFG->enrol_db_ignorehiddencourse) {
                        continue;
                    }

                    /// If there's no role specified, we get the default course role (usually student)
                    if ($use_default_role) {
                        $role = get_default_course_role($course);
                    }

                    $context = get_context_instance(CONTEXT_COURSE, $course->id);

                    // Couldn't get a role or context, skip.
                    if (!$role || !$context) {
                        continue;
                    }

                    $times = $enroltimes[$coursefield];

                    // Search the role assignments to see if this user
                    // already has this role in this context.  If it is, we
                    // skip to the next course.
                    foreach($existing as $key => $role_assignment) {
                        if ($role_assignment->roleid == $role->id
                            && $role_assignment->contextid == $context->id) {
                            unset($existing[$key]);

                            if (!$CFG->enrol_db_disableunenrol and $times['timeend'] != 0 and $times['timeend'] < time()) {
                                // Timeend expired, unenrol
                                role_unassign($role->id, $user->id, 0, $context->id);
                                continue 2;
                            }
                            if (round($times['timestart'], -2) == $role_assignment->timestart and $times['timeend'] == $role_assignment->timeend) {
                                // No updates needed, skip out of this one
                                //error_log('[ENROL_DB] User is already enroled in course '.$course->idnumber);
                                continue 2;
                            } else {
                                // Perform an update by performing the role_assign below
                                break;
                            }
                        }
                    }

                    //error_log('[ENROL_DB] Enrolling user in course '.$course->idnumber);
                    if ($times['timeend'] == 0 or $times['timeend'] > time()) {
                        role_assign($role->id, $user->id, 0, $context->id, $times['timestart'], $times['timeend'], 0, 'database');
                    }
                }
            } // We've processed all external courses found

            /// We have some courses left that we might need to unenrol from
            /// Note: we only process enrolments that we (ie 'database' plugin) made
            /// Do not unenrol anybody if the disableunenrol option is 'yes'
            if (!$CFG->enrol_db_disableunenrol) {
                foreach ($existing as $role_assignment) {
                    if ($role_assignment->enrol == 'database') {
                        //error_log('[ENROL_DB] Removing user from context '.$role_assignment->contextid);
                        role_unassign($role_assignment->roleid, $user->id, '', $role_assignment->contextid);
                    }
                }
            }
        } else {
            error_log('[ENROL_DB] Couldn\'t get rows from external db: '.$enroldb->ErrorMsg());
        }
    }
    $this->enrol_disconnect($enroldb);
}

/**
 * sync enrolments with database, create courses if required.
 *
 * @param object The role to sync for. If no role is specified, defaults are
 * used.
 */
function sync_enrolments($role = null) {
    global $CFG;
    global $db;
    error_reporting(E_ALL);

    // Connect to the external database
    $enroldb = $this->enrol_connect();
    if (!$enroldb) {
        notify("enrol/database cannot connect to server");
        return false;
    }

    if (isset($role)) {
        echo '=== Syncing enrolments for role: '.$role->shortname." ===\n";
    } else {
        echo "=== Syncing enrolments for default role ===\n";
    }

    // first, pack the sortorder...
    fix_course_sortorder();

    list($have_role, $remote_role_name, $remote_role_value) = $this->role_fields($enroldb, $role);

    if (!$have_role) {
        if (!empty($CFG->enrol_db_defaultcourseroleid)
         and $role = get_record('role', 'id', $CFG->enrol_db_defaultcourseroleid)) {
            echo "=== Using enrol_db_defaultcourseroleid: {$role->id} ({$role->shortname}) ===\n";
        } elseif (isset($role)) {
            echo "!!! WARNING: Role specified by caller, but no (or invalid) role configuration !!!\n";
        }
    }

    // get enrolments per-course
    $sql =  "SELECT DISTINCT {$CFG->enrol_remotecoursefield} " .
        " FROM {$CFG->enrol_dbtable} " .
        " WHERE {$CFG->enrol_remoteuserfield} IS NOT NULL" .
        (isset($remote_role_name, $remote_role_value) ? ' AND '.$remote_role_name.' = '.$remote_role_value : '');

    $rs = $enroldb->Execute($sql);
    if (!$rs) {
        trigger_error($enroldb->ErrorMsg() .' STATEMENT: '. $sql);
        return false;
    }
    if ( $rs->EOF ) { // no courses! outta here...
        return true;
    }

    begin_sql();
    $extcourses = array();
    while ($extcourse_obj = rs_fetch_next_record($rs)) { // there are more course records
        $extcourse_obj = (object)array_change_key_case((array)$extcourse_obj , CASE_LOWER);
        $extcourse = $extcourse_obj->{strtolower($CFG->enrol_remotecoursefield)};
        array_push($extcourses, $extcourse);

        // does the course exist in moodle already?
        $course = false;
        $course = get_record( 'course',
                              $CFG->enrol_localcoursefield,
                              addslashes($extcourse) );

        if (!is_object($course)) {
            if (empty($CFG->enrol_db_autocreate)) { // autocreation not allowed
                if (debugging('', DEBUG_ALL)) {
                    error_log( "Course $extcourse does not exist, skipping");
                }
                continue; // next foreach course
            }
            // Add to list - don't care if it fails
            $this->processed[$extcourse] = $extcourse;

            // ok, now then let's create it!
            error_log("Creating Course $extcourse...");
            if ($courseid = $this->create_course($enroldb, $extcourse, true, true)
                and $course = get_record( 'course', 'id', $courseid)) {
                // we are skipping fix_course_sortorder()
                print "created\n";
            } else {
                print "failed\n";
                continue; // nothing left to do...
            }
        } else if (!empty($CFG->enrol_db_autoupdate)) {
            $this->update_course($enroldb, $course, $extcourse);

            // Course updated, add to list
            $this->processed[$extcourse] = $extcourse;
        }

        $context = get_context_instance(CONTEXT_COURSE, $course->id);

        // If we don't have a proper role setup, then we default to the default
        // role for the current course.
        if (!$have_role) {
            $role = get_default_course_role($course);
        }

        // get a list of the student ids the are enrolled
        // in the external db -- hopefully it'll fit in memory...
        $extenrolments = array();
        $sql = "SELECT {$CFG->enrol_remoteuserfield}" . 
            (!empty($CFG->enrol_db_remotetimestartfield) ? ", $CFG->enrol_db_remotetimestartfield" : '').
            (!empty($CFG->enrol_db_remotetimeendfield) ? ", $CFG->enrol_db_remotetimeendfield" : '').
            " FROM {$CFG->enrol_dbtable} " .
            " WHERE {$CFG->enrol_remotecoursefield} = " . $enroldb->quote($extcourse) .
                ($have_role ? ' AND '.$remote_role_name.' = '.$remote_role_value : '');

        $crs = $enroldb->Execute($sql);
        if (!$crs) {
            trigger_error($enroldb->ErrorMsg() .' STATEMENT: '. $sql);
            return false;
        }
        if ( $crs->EOF ) { // shouldn't happen, but cover all bases
            continue;
        }

        // slurp results into an arrays
        $enroltimes = array();
        while ($crs_obj = rs_fetch_next_record($crs)) {
            $crs_obj = (object)array_change_key_case((array)$crs_obj , CASE_LOWER);
            $extenrolment = $crs_obj->{strtolower($CFG->enrol_remoteuserfield)};
            array_push($extenrolments, $extenrolment);

            $enroltimes[$extenrolment] = array();

            if (isset($crs_obj->{strtolower($CFG->enrol_db_remotetimestartfield)})) {
                $enroltimes[$extenrolment]['timestart'] = $crs_obj->{strtolower($CFG->enrol_db_remotetimestartfield)};
            } else {
                $enroltimes[$extenrolment]['timestart'] = 0;
            }
            if (isset($crs_obj->{strtolower($CFG->enrol_db_remotetimeendfield)})) {
                $enroltimes[$extenrolment]['timeend'] = $crs_obj->{strtolower($CFG->enrol_db_remotetimeendfield)};
            } else {
                $enroltimes[$extenrolment]['timeend'] = 0;
            }
        }
        rs_close($crs); // release the handle

        //
        // prune enrolments to users that are no longer in ext auth
        // hopefully they'll fit in the max buffer size for the RDBMS
        //
        // TODO: This doesn't work perfectly.  If we are operating without
        // roles in the external DB, then this doesn't handle changes of role
        // within a course (because the user is still enrolled in the course,
        // so NOT IN misses the course).
        //
        // When the user logs in though, their role list will be updated
        // correctly.
        //
        if (!$CFG->enrol_db_disableunenrol) {
            $to_prune = get_records_sql("
             SELECT ra.*
             FROM {$CFG->prefix}role_assignments ra
              JOIN {$CFG->prefix}user u ON ra.userid = u.id
             WHERE ra.enrol = 'database'
              AND ra.contextid = {$context->id}
              AND ra.roleid = ". $role->id . ($extenrolments
                ? " AND u.{$CFG->enrol_localuserfield} NOT IN (".join(", ", array_map(array(&$db, 'quote'), $extenrolments)).")"
                : ''));

            if ($to_prune) {
                foreach ($to_prune as $role_assignment) {
                    if (role_unassign($role->id, $role_assignment->userid, 0, $role_assignment->contextid)){
                        error_log( "Unassigned {$role->shortname} assignment #{$role_assignment->id} for course {$course->id} (" . format_string($course->shortname) . "); user {$role_assignment->userid}");
                    } else {
                        error_log( "Failed to unassign {$role->shortname} assignment #{$role_assignment->id} for course {$course->id} (" . format_string($course->shortname) . "); user {$role_assignment->userid}");
                    }
                }
            }
        }

        //
        // insert current enrolments
        // bad we can't do INSERT IGNORE with postgres...
        //
        foreach ($extenrolments as $member) {
            // Get the user id and whether is enrolled in one fell swoop
            $sql = "
                SELECT u.id AS userid, ra.id AS enrolmentid, ra.timestart, ra.timeend, ra.contextid
                FROM {$CFG->prefix}user u
                 LEFT OUTER JOIN {$CFG->prefix}role_assignments ra ON u.id = ra.userid
                  AND ra.roleid = {$role->id}
                  AND ra.contextid = {$context->id}
                 WHERE u.{$CFG->enrol_localuserfield} = ".$db->quote($member) .
                 " AND (u.deleted IS NULL OR u.deleted=0) ";

            $ers = $db->Execute($sql);
            if (!$ers) {
                trigger_error($db->ErrorMsg() .' STATEMENT: '. $sql);
                return false;
            }
            if ( $ers->EOF ) { // if this returns empty, it means we don't have the student record.
                                              // should not happen -- but skip it anyway
                //trigger_error('weird! no user record entry?');
                continue;
            }
            $user_obj = rs_fetch_record($ers);
            $userid      = $user_obj->userid;
            $enrolmentid = $user_obj->enrolmentid;
            $times       = $enroltimes[$member];
            rs_close($ers); // release the handle

            if ($times['timeend'] != 0 and $times['timeend'] < time()) {
                // Timeend expired - don't assign a role
                if (!$CFG->enrol_db_disableunenrol and $enrolmentid) {
                    // Enrolled and timeend has expired - unenrol
                    if (role_unassign($role->id, $user_obj->userid, 0, $user_obj->contextid)){
                        error_log( "Unassigned {$role->shortname} assignment #$enrolmentid for course {$course->id} (" . format_string($course->shortname) . "); user {$user_obj->userid}");
                    } else {
                        error_log( "Failed to unassign {$role->shortname} assignment #$enrolmentid for course {$course->id} (" . format_string($course->shortname) . "); user {$user_obj->userid}");
                    }
                }
                continue;
            }
            if ($enrolmentid and round($times['timestart'], -2) == $user_obj->timestart and $times['timeend'] == $user_obj->timeend) {
                // already enrolled and enrollment dates have not changed - skip (we round timestart because role_assign does)
                continue;
            }

            if (role_assign($role->id, $userid, 0, $context->id, $times['timestart'], $times['timeend'], 0, 'database')){
                error_log( "Assigned role {$role->shortname} to user {$userid} in course {$course->id} (" . format_string($course->shortname) . ")");
            } else {
                error_log( "Failed to assign role {$role->shortname} to user {$userid} in course {$course->id} (" . format_string($course->shortname) . ")");
            }

        } // end foreach member

        // Update cron time
        $this->cron_set_start(false);
    } // end while course records
    rs_close($rs); //Close the main course recordset

    //
    // prune enrolments to courses that are no longer in ext auth
    //
    // TODO: This doesn't work perfectly.  If we are operating without
    // roles in the external DB, then this doesn't handle changes of role
    // within a course (because the user is still enrolled in the course,
    // so NOT IN misses the course).
    //
    // When the user logs in though, their role list will be updated
    // correctly.
    //
    if (!$CFG->enrol_db_disableunenrol) {
        $sql = "
            SELECT ra.roleid, ra.userid, ra.contextid
            FROM {$CFG->prefix}role_assignments ra
                JOIN {$CFG->prefix}context cn ON cn.id = ra.contextid
                JOIN {$CFG->prefix}course c ON c.id = cn.instanceid
            WHERE ra.enrol = 'database'
              AND cn.contextlevel = ".CONTEXT_COURSE." " .
                ($have_role ? ' AND ra.roleid = '.$role->id : '') .
                ($extcourses
                    ? " AND c.{$CFG->enrol_localcoursefield} NOT IN (" . join(",", array_map(array(&$db, 'quote'), $extcourses)) . ")"
                    : '');

        $ers = $db->Execute($sql);
        if (!$ers) {
            trigger_error($db->ErrorMsg() .' STATEMENT: '. $sql);
            return false;
        }
        if ( !$ers->EOF ) {
            while ($user_obj = rs_fetch_next_record($ers)) {
                $user_obj = (object)array_change_key_case((array)$user_obj , CASE_LOWER);
                $roleid     = $user_obj->roleid;
                $user       = $user_obj->userid;
                $contextid  = $user_obj->contextid;
                if (role_unassign($roleid, $user, 0, $contextid)){
                    error_log( "Unassigned role {$roleid} from user $user in context $contextid");
                } else {
                    error_log( "Failed unassign role {$roleid} from user $user in context $contextid");
                }

                // Update cron time
                $this->cron_set_start(false);
            }
            rs_close($ers); // release the handle
        }
    }

    commit_sql();

    // we are done now, a bit of housekeeping
    fix_course_sortorder();

    $this->enrol_disconnect($enroldb);
    $this->delete_backup_files();
    return true;
}

/// Overide the get_access_icons() function
function get_access_icons($course) {
}


/// Overide the base config_form() function
function config_form($frm) {
    global $CFG;

    $vars = array('enrol_dbhost', 'enrol_dbuser', 'enrol_dbpass',
                  'enrol_dbname', 'enrol_dbtable',
                  'enrol_localcoursefield', 'enrol_localuserfield',
                  'enrol_remotecoursefield', 'enrol_remoteuserfield',
                  'enrol_db_autocreate', 'enrol_db_category', 'enrol_db_template',
                  'enrol_autocreate_category', 'enrol_category_separator',
                  'enrol_db_localrolefield', 'enrol_db_remoterolefield',
                  'enrol_db_remotetimestartfield', 'enrol_db_remotetimeendfield',
                  'enrol_remotecoursefield', 'enrol_remoteuserfield',
                  'enrol_coursetable', 'enrol_coursefullname',
                  'enrol_courseshortname', 'enrol_courseid',
                  'enrol_coursesummary', 'enrol_coursecategory',
                  'enrol_coursetemplate', 'enrol_courseenrollable',
                  'enrol_db_ignorehiddencourse', 'enrol_db_defaultcourseroleid',
                  'enrol_db_disableunenrol', 'enrol_coursestartdate',
                  'enrol_courseformat', 'enrol_coursetheme',
                  'enrol_coursepassword', 'enrol_courseguest',
                  'enrol_coursenumsections', 'enrol_courseidnumber',
                  'enrol_coursecost', 'enrol_coursenewsitems',
                  'enrol_courseshowgrades', 'enrol_coursegroupmode',
                  'enrol_coursegroupmodefoce', 'enrol_coursevisible',
                  'enrol_db_autoupdate', 'enrol_restore_coursetemplate',
                  'enrol_restore_usetemplate');

    foreach ($vars as $var) {
        if (!isset($frm->$var)) {
            $frm->$var = '';
        }
    }
    include("$CFG->dirroot/enrol/database/config.html");
}

/// Override the base process_config() function
function process_config($config) {

    if (!isset($config->enrol_dbtype)) {
        $config->enrol_dbtype = 'mysql';
    }
    set_config('enrol_dbtype', $config->enrol_dbtype);

    if (!isset($config->enrol_dbhost)) {
        $config->enrol_dbhost = '';
    }
    set_config('enrol_dbhost', $config->enrol_dbhost);

    if (!isset($config->enrol_dbuser)) {
        $config->enrol_dbuser = '';
    }
    set_config('enrol_dbuser', $config->enrol_dbuser);

    if (!isset($config->enrol_dbpass)) {
        $config->enrol_dbpass = '';
    }
    set_config('enrol_dbpass', $config->enrol_dbpass);

    if (!isset($config->enrol_dbname)) {
        $config->enrol_dbname = '';
    }
    set_config('enrol_dbname', $config->enrol_dbname);

    if (!isset($config->enrol_dbtable)) {
        $config->enrol_dbtable = '';
    }
    set_config('enrol_dbtable', $config->enrol_dbtable);

    if (!isset($config->enrol_localcoursefield)) {
        $config->enrol_localcoursefield = '';
    }
    set_config('enrol_localcoursefield', $config->enrol_localcoursefield);

    if (!isset($config->enrol_localuserfield)) {
        $config->enrol_localuserfield = '';
    }
    set_config('enrol_localuserfield', $config->enrol_localuserfield);

    if (!isset($config->enrol_remotecoursefield)) {
        $config->enrol_remotecoursefield = '';
    }
    set_config('enrol_remotecoursefield', $config->enrol_remotecoursefield);

    if (!isset($config->enrol_remoteuserfield)) {
        $config->enrol_remoteuserfield = '';
    }
    set_config('enrol_remoteuserfield', $config->enrol_remoteuserfield);

    if (!isset($config->enrol_db_autocreate)) {
        $config->enrol_db_autocreate = '';
    }
    set_config('enrol_db_autocreate', $config->enrol_db_autocreate);

    if (!isset($config->enrol_db_autoupdate)) {
        $config->enrol_db_autoupdate = 0;
    }
    set_config('enrol_db_autoupdate', $config->enrol_db_autoupdate);

    if (!isset($config->enrol_db_category)) {
        $config->enrol_db_category = '';
    }
    set_config('enrol_db_category', $config->enrol_db_category);

    if (!isset($config->enrol_autocreate_category)) {
        $config->enrol_autocreate_category = '';
    }
    set_config('enrol_autocreate_category', $config->enrol_autocreate_category);

    if (!isset($config->enrol_category_separator)) {
        $config->enrol_category_separator = '';
    }
    set_config('enrol_category_separator', $config->enrol_category_separator);

    if (!isset($config->enrol_db_template)) {
        $config->enrol_db_template = '';
    }
    set_config('enrol_db_template', $config->enrol_db_template);

    if (!isset($config->enrol_coursetable)) {
        $config->enrol_coursetable = '';
    }
    set_config('enrol_coursetable', $config->enrol_coursetable);

    if (!isset($config->enrol_coursefullname)) {
        $config->enrol_coursefullname = '';
    }
    set_config('enrol_coursefullname', $config->enrol_coursefullname);

    if (!isset($config->enrol_courseshortname)) {
        $config->enrol_courseshortname = '';
    }
    set_config('enrol_courseshortname',
               $config->enrol_courseshortname);

    if (!isset($config->enrol_courseid)) {
        $config->enrol_courseid = '';
    }
    set_config('enrol_courseid',
               $config->enrol_courseid);

    if (!isset($config->enrol_coursesummary)) {
        $config->enrol_coursesummary = '';
    }
    set_config('enrol_coursesummary',
               $config->enrol_coursesummary);

    if (!isset($config->enrol_coursecategory)) {
        $config->enrol_coursecategory = '';
    }
    set_config('enrol_coursecategory',
               $config->enrol_coursecategory);

    if (!isset($config->enrol_coursestartdate)) {
        $config->enrol_coursestartdate = '';
    }
    set_config('enrol_coursestartdate',
               $config->enrol_coursestartdate);

    if (!isset($config->enrol_courseformat)) {
        $config->enrol_courseformat = '';
    }
    set_config('enrol_courseformat',
               $config->enrol_courseformat);

    if (!isset($config->enrol_coursetheme)) {
        $config->enrol_coursetheme = '';
    }
    set_config('enrol_coursetheme',
               $config->enrol_coursetheme);

    if (!isset($config->enrol_coursepassword)) {
        $config->enrol_coursepassword = '';
    }
    set_config('enrol_coursepassword',
               $config->enrol_coursepassword);

    if (!isset($config->enrol_courseguest)) {
        $config->enrol_courseguest = '';
    }
    set_config('enrol_courseguest',
               $config->enrol_courseguest);

    if (!isset($config->enrol_coursenumsections)) {
        $config->enrol_coursenumsections = '';
    }
    set_config('enrol_coursenumsections',
               $config->enrol_coursenumsections);

    if (!isset($config->enrol_courseidnumber)) {
        $config->enrol_courseidnumber = '';
    }
    set_config('enrol_courseidnumber',
               $config->enrol_courseidnumber);

    if (!isset($config->enrol_coursecost)) {
        $config->enrol_coursecost = '';
    }
    set_config('enrol_coursecost',
               $config->enrol_coursecost);

    if (!isset($config->enrol_coursenewsitems)) {
        $config->enrol_coursenewsitems = '';
    }
    set_config('enrol_coursenewsitems',
               $config->enrol_coursenewsitems);

    if (!isset($config->enrol_courseshowgrades)) {
        $config->enrol_courseshowgrades = '';
    }
    set_config('enrol_courseshowgrades',
               $config->enrol_courseshowgrades);

    if (!isset($config->enrol_coursegroupmode)) {
        $config->enrol_coursegroupmode = '';
    }
    set_config('enrol_coursegroupmode',
               $config->enrol_coursegroupmode);

    if (!isset($config->enrol_coursegroupmodefoce)) {
        $config->enrol_coursegroupmodefoce = '';
    }
    set_config('enrol_coursegroupmodefoce',
               $config->enrol_coursegroupmodefoce);

    if (!isset($config->enrol_coursevisible)) {
        $config->enrol_coursevisible = '';
    }
    set_config('enrol_coursevisible',
               $config->enrol_coursevisible);

    if (!isset($config->enrol_courseenrollable)) {
        $config->enrol_courseenrollable = '';
    }
    set_config('enrol_courseenrollable',
               $config->enrol_courseenrollable);

    if (!isset($config->enrol_coursetemplate)) {
        $config->enrol_coursetemplate = '';
    }
    set_config('enrol_coursetemplate',
               $config->enrol_coursetemplate);

    if (!isset($config->enrol_restore_coursetemplate)) {
        $config->enrol_restore_coursetemplate = '';
    }
    set_config('enrol_restore_coursetemplate', $config->enrol_restore_coursetemplate);

    if (!isset($config->enrol_restore_usetemplate)) {
        $config->enrol_restore_usetemplate = '';
    }
    set_config('enrol_restore_usetemplate', $config->enrol_restore_usetemplate);

    if (!isset($config->enrol_db_defaultcourseroleid)) {
        $config->enrol_db_defaultcourseroleid = '';
    }
    set_config('enrol_db_defaultcourseroleid', $config->enrol_db_defaultcourseroleid);

    if (!isset($config->enrol_db_localrolefield)) {
        $config->enrol_db_localrolefield = '';
    }
    set_config('enrol_db_localrolefield', $config->enrol_db_localrolefield);

    if (!isset($config->enrol_db_remoterolefield)) {
        $config->enrol_db_remoterolefield = '';
    }
    set_config('enrol_db_remoterolefield', $config->enrol_db_remoterolefield);

    if (!isset($config->enrol_db_remotetimestartfield)) {
        $config->enrol_db_remotetimestartfield = '';
    }
    set_config('enrol_db_remotetimestartfield', $config->enrol_db_remotetimestartfield);

    if (!isset($config->enrol_db_remotetimeendfield)) {
        $config->enrol_db_remotetimeendfield = '';
    }
    set_config('enrol_db_remotetimeendfield', $config->enrol_db_remotetimeendfield);

    if (!isset($config->enrol_db_ignorehiddencourse)) {
        $config->enrol_db_ignorehiddencourse = '';
    }
    set_config('enrol_db_ignorehiddencourse', $config->enrol_db_ignorehiddencourse );

    if (!isset($config->enrol_db_disableunenrol)) {
        $config->enrol_db_disableunenrol = '';
    }
    set_config('enrol_db_disableunenrol', $config->enrol_db_disableunenrol );

    return true;
}

// will create the moodle course from the template
// course_ext is an array as obtained from ldap -- flattened somewhat
// NOTE: if you pass true for $skip_fix_course_sortorder
// you will want to call fix_course_sortorder() after your are done
// with course creation
function create_course($enroldb, $coursefield, $skip_fix_course_sortorder = 0, $enablerestore = false) {
    global $CFG;

    if (!empty($CFG->enrol_coursetable)) {
        // Using external course information table
        if (!$course = $this->get_course_details($enroldb, $coursefield)) {
            return false;
        }
    } else {
        // Using most basic data
        $course = new StdClass;
        $course->{$CFG->enrol_localcoursefield} = $coursefield;
        $course->fullname  = $coursefield;
        $course->shortname = $coursefield;
    }

    // define a template
    if (!empty($CFG->enrol_coursetemplate) and !empty($course->template)) {
        // We stored the course template in $course->template in get_course_details()
        // so use it and unset() it once we are done with it.
        if ($template = get_record("course", 'shortname', addslashes($course->template))) {
            $templatecourse = clone($template);
            $template = (array)$template;
            unset ($course->template);
        }
    }
    if ((!empty($CFG->enrol_db_template) and (!$template))) {
        if ($template = get_record("course", 'shortname', addslashes($CFG->enrol_db_template))) {
            $template = (array)$template;
        }
    }
    if (empty($template)) {
        $site = get_site();
        $template = array(
                          'startdate'      => time() + 3600 * 24,
                          'summary'        => get_string("defaultcoursesummary"),
                          'format'         => "weeks",
                          'password'       => "",
                          'guest'          => 0,
                          'numsections'    => 10,
                          'idnumber'       => '',
                          'cost'           => '',
                          'newsitems'      => 5,
                          'showgrades'     => 1,
                          'groupmode'      => 0,
                          'groupmodeforce' => 0,
                          'student'  => $site->student,
                          'students' => $site->students,
                          'teacher'  => $site->teacher,
                          'teachers' => $site->teachers,
                          );
    }
    // overlay template
    foreach (array_keys($template) AS $key) {
        if (!isset($course->$key)) {
            $course->$key = $template[$key];
        }
    }

    // Only use default categories if the course doesn't already have one.
    if (empty($course->category)) {
        // the misc 'catch-all' category
        $course->category = 1;

        if (!empty($CFG->enrol_db_category)) {
            //category = 0 or undef will break moodle
            $course->category = $CFG->enrol_db_category;
        }
    }

    // define the sortorder
    $sort = get_field_sql('SELECT COALESCE(MAX(sortorder)+1, 100) AS max ' .
                          ' FROM ' . $CFG->prefix . 'course ' .
                          ' WHERE category=' . $course->category);
    $course->sortorder   = $sort;
    $course->timecreated = time();

    if (!isset($course->startdate)) {
        $course->startdate   = time() + 3600 * 24;
    }
    if (!isset($course->visible)) {
        $course->visible     = 1;
    }

    // clear out id just in case
    unset($course->id);

    // truncate a few key fields
    $course->idnumber  = substr($course->idnumber, 0, 100);
    $course->shortname = substr($course->shortname, 0, 100);

    // store it and log
    if ($newcourseid = insert_record("course", addslashes_object($course))) {  // Set up new course
        if ($enablerestore and !empty($CFG->enrol_restore_coursetemplate) and isset($templatecourse)) {
            $this->backup_and_restore_into_course($templatecourse, $newcourseid);
        } else {
            $section = NULL;
            $section->course = $newcourseid;   // Create a default section.
            $section->section = 0;
            $section->id = insert_record("course_sections", $section);
            $page = page_create_object(PAGE_COURSE_VIEW, $newcourseid);
            blocks_repopulate_page($page); // Return value no
        }


        if(!$skip_fix_course_sortorder){
            fix_course_sortorder();
        }
        add_to_log($newcourseid, "course", "new", "view.php?id=$newcourseid", "enrol/database auto-creation");
    } else {
        trigger_error("Could not create new course $extcourse from database");
        notify("Serious Error! Could not create the new course!");
        return false;
    }

    return $newcourseid;
}

/**
 * Update a Moodle course settings based
 * on the external course settings.
 *
 * @param object $enroldb External database connection
 * @param object $course Moodle course to update
 * @param string $extcourseid External course id to sync the Moodle course to
 * @return void
 **/
function update_course($enroldb, $course, $extcourseid) {
    if ($extcourse = $this->get_course_details($enroldb, $extcourseid)) {
        $update = false;
        $record = new stdClass;
        foreach ($extcourse as $key => $value) {
            if ($key != 'id' and isset($course->$key) and $course->$key != $value) {
                switch ($key) {
                    case 'idnumber':
                    case 'shortname':
                        $record->$key = substr($value, 0, 100);
                        break;
                    default:
                        $record->$key = $value;
                        break;
                }
                $update = true;
            }
        }

        // Explicitly set
        $record->id = $course->id;

        if ($update) {
            if (!update_record('course', addslashes_object($record))) {
                error_log("Failed to update course with id = $course->id");
            }
        }
    }
}

/**
 * Processes courses that have no enrolments.
 *
 * Best to be called after {@link sync_enrolments()}
 * as $this->processed is built so we can skip courses
 * already processed via enrolments.
 *
 * @return boolean
 **/
function process_courses_without_enrolments() {
    global $CFG;

    // Check required config
    if (!empty($CFG->enrol_courseid) and
        !empty($CFG->enrol_coursetable) and
        !empty($CFG->enrol_localcoursefield)) {

        $enroldb = $this->enrol_connect();
        if (!$enroldb) {
            error_log('[ENROL_DB] Could not make a connection');
            return;
        }

        begin_sql();

        if ($rs = $enroldb->Execute("SELECT {$CFG->enrol_courseid} as enrolremotecoursefield
                                       FROM {$CFG->enrol_coursetable}")) {

            while ($fields_obj = rs_fetch_next_record($rs)) {
                $fields_obj  = (object)array_change_key_case((array)$fields_obj , CASE_LOWER);
                $coursefield = $fields_obj->enrolremotecoursefield;

                if (!in_array($coursefield, $this->processed)) {
                    $course = get_record('course', $CFG->enrol_localcoursefield, $coursefield);

                    if (!is_object($course)) {
                        if (empty($CFG->enrol_db_autocreate)) { // autocreation not allowed
                            if (debugging('', DEBUG_ALL)) {
                                error_log( "Course $extcourse does not exist, skipping");
                            }
                            continue; // next foreach course
                        }
                        // ok, now then let's create it!
                        error_log("Creating Course $coursefield...");
                        if ($this->create_course($enroldb, $coursefield, true, true)) {
                            // we are skipping fix_course_sortorder()
                            error_log("created.");
                        } else {
                            error_log("failed.");
                            continue; // nothing left to do...
                        }
                    } else if (!empty($CFG->enrol_db_autoupdate)) {
                        $this->update_course($enroldb, $course, $coursefield);
                    }
                }
            }
            rs_close($rs);
        }
        commit_sql();
        fix_course_sortorder();
        $this->enrol_disconnect($enroldb);
        $this->delete_backup_files();
    }

    return true;
}

/// DB Connect
/// NOTE: You MUST remember to disconnect
/// when you stop using it -- as this call will
/// sometimes modify $CFG->prefix for the whole of Moodle!
function enrol_connect() {
    global $CFG;

    // Try to connect to the external database (forcing new connection)
    $enroldb = &ADONewConnection($CFG->enrol_dbtype);
    if ($enroldb->Connect($CFG->enrol_dbhost, $CFG->enrol_dbuser, $CFG->enrol_dbpass, $CFG->enrol_dbname, true)) {
        $enroldb->SetFetchMode(ADODB_FETCH_ASSOC); ///Set Assoc mode always after DB connection
        return $enroldb;
    } else {
        trigger_error("Error connecting to enrolment DB backend with: "
                      . "$CFG->enrol_dbhost,$CFG->enrol_dbuser,$CFG->enrol_dbpass,$CFG->enrol_dbname");
        return false;
    }
}

/// DB Disconnect
function enrol_disconnect($enroldb) {
    global $CFG;

    $enroldb->Close();
}

/**
 * This function returns the name and value of the role field to query the db
 * for, or null if there isn't one.
 *
 * @param object The ADOdb connection
 * @param object The role
 * @return array (boolean, string, db quoted string)
 */
function role_fields($enroldb, $role) {
    global $CFG;

    if ($have_role = !empty($role)
     && !empty($CFG->enrol_db_remoterolefield)
     && !empty($CFG->enrol_db_localrolefield)
     && !empty($role->{$CFG->enrol_db_localrolefield})) {
        $remote_role_name = $CFG->enrol_db_remoterolefield;
        $remote_role_value = $enroldb->quote($role->{$CFG->enrol_db_localrolefield});
    } else {
        $remote_role_name = $remote_role_value = null;
    }

    return array($have_role, $remote_role_name, $remote_role_value);
}

// get_course_details
//
// get_course_details Returns false if the course doesn't exist
// or if there is more than one course with this courseid in the
// enrolment database
//
// @param $enroldb
// @param $courseid
// @param $log_errors boolean (default 'true')
function get_course_details($enroldb, $courseid, $log_errors = true){
    global $CFG;

    $columns = $CFG->enrol_courseshortname;
    if (!empty($CFG->enrol_coursefullname)) {
        $columns = $columns . "," . $CFG->enrol_coursefullname;
    }
    if (!empty($CFG->enrol_coursesummary)) {
        $columns = $columns . "," . $CFG->enrol_coursesummary;
    }
    if (!empty($CFG->enrol_coursecategory)) {
        $columns = $columns . "," . $CFG->enrol_coursecategory;
    }
    if (!empty($CFG->enrol_courseenrollable)) {
        $columns = $columns . "," . $CFG->enrol_courseenrollable;
    }
    if (!empty($CFG->enrol_coursetemplate)) {
        $columns = $columns . "," . $CFG->enrol_coursetemplate;
    }
    if (!empty($CFG->enrol_coursestartdate)) {
        $columns = $columns . "," . $CFG->enrol_coursestartdate;
    }
    if (!empty($CFG->enrol_courseformat)) {
        $columns = $columns . "," . $CFG->enrol_courseformat;
    }
    if (!empty($CFG->enrol_coursetheme)) {
        $columns = $columns . "," . $CFG->enrol_coursetheme;
    }
    if (!empty($CFG->enrol_coursepassword)) {
        $columns = $columns . "," . $CFG->enrol_coursepassword;
    }
    if (!empty($CFG->enrol_courseguest)) {
        $columns = $columns . "," . $CFG->enrol_courseguest;
    }
    if (!empty($CFG->enrol_coursenumsections)) {
        $columns = $columns . "," . $CFG->enrol_coursenumsections;
    }
    if (!empty($CFG->enrol_courseidnumber)) {
        $columns = $columns . "," . $CFG->enrol_courseidnumber;
    }
    if (!empty($CFG->enrol_coursecost)) {
        $columns = $columns . "," . $CFG->enrol_coursecost;
    }
    if (!empty($CFG->enrol_coursenewsitems)) {
        $columns = $columns . "," . $CFG->enrol_coursenewsitems;
    }
    if (!empty($CFG->enrol_courseshowgrades)) {
        $columns = $columns . "," . $CFG->enrol_courseshowgrades;
    }
    if (!empty($CFG->enrol_coursegroupmode)) {
        $columns = $columns . "," . $CFG->enrol_coursegroupmode;
    }
    if (!empty($CFG->enrol_coursegroupmodefoce)) {
        $columns = $columns . "," . $CFG->enrol_coursegroupmodefoce;
    }
    if (!empty($CFG->enrol_coursevisible)) {
        $columns = $columns . "," . $CFG->enrol_coursevisible;
    }

    $courseid = addslashes($courseid);
    $query = "SELECT $columns FROM {$CFG->enrol_coursetable} " .
             "WHERE {$CFG->enrol_courseid} = '$courseid'";

    if ($rs = $enroldb->Execute($query)) {
        if ($rs->RecordCount() == 1) {
            $course = new StdClass;
            $course->shortname = $rs->fields[$CFG->enrol_courseshortname];
            if (!empty($CFG->enrol_coursefullname)) {
                $course->fullname = $rs->fields[$CFG->enrol_coursefullname];
            }
            if (!empty($CFG->enrol_coursesummary)) {
                $course->summary = $rs->fields[$CFG->enrol_coursesummary];
            }
            if (!empty($CFG->enrol_coursestartdate)) {
                $course->startdate = $rs->fields[$CFG->enrol_coursestartdate];
            }
            if (!empty($CFG->enrol_courseenrollable)) {
                $course->enrollable = $rs->fields[$CFG->enrol_courseenrollable];
            }
            if (!empty($CFG->enrol_courseformat)) {
                $course->format = $rs->fields[$CFG->enrol_courseformat];
            }
            if (!empty($CFG->enrol_coursetheme)) {
                $course->theme = $rs->fields[$CFG->enrol_coursetheme];
            }
            if (!empty($CFG->enrol_coursepassword)) {
                $course->password = $rs->fields[$CFG->enrol_coursepassword];
            }
            if (!empty($CFG->enrol_courseguest)) {
                $course->guest = $rs->fields[$CFG->enrol_courseguest];
            }
            if (!empty($CFG->enrol_coursenumsections)) {
                $course->numsections = $rs->fields[$CFG->enrol_coursenumsections];
            }
            if (!empty($CFG->enrol_courseidnumber)) {
                $course->idnumber = $rs->fields[$CFG->enrol_courseidnumber];
            }
            if (!empty($CFG->enrol_coursecost)) {
                $course->cost = $rs->fields[$CFG->enrol_coursecost];
            }
            if (!empty($CFG->enrol_coursenewsitems)) {
                $course->newsitems = $rs->fields[$CFG->enrol_coursenewsitems];
            }
            if (!empty($CFG->enrol_courseshowgrades)) {
                $course->showgrades = $rs->fields[$CFG->enrol_courseshowgrades];
            }
            if (!empty($CFG->enrol_coursegroupmode)) {
                $course->groupmode = $rs->fields[$CFG->enrol_coursegroupmode];
            }
            if (!empty($CFG->enrol_coursegroupmodefoce)) {
                $course->groupmodeforce = $rs->fields[$CFG->enrol_coursegroupmodefoce];
            }
            if (!empty($CFG->enrol_coursevisible)) {
                $course->visible = $rs->fields[$CFG->enrol_coursevisible];
            }
            if (!empty($CFG->enrol_coursecategory)) {
                $category = $rs->fields[$CFG->enrol_coursecategory];
                if(!empty($category) and $categoryid = $this->get_category($category)) {
                    $course->category = $categoryid;
                }
                // If we don't find a categoryid, don't set the course
                // category so it gets the default one.
            }
            if (!empty($CFG->enrol_coursetemplate)) {
                // Don't forget to unset $course->template later before
                // we do insert_record, as there is no 'template' field
                // in prefix_course and the insert will fail.
                $course->template = $rs->fields[$CFG->enrol_coursetemplate];
            }

            // Make this assignment last, in case the user chooses
            // one of the above fields as $CFG->enrol_localcoursefield
            $course->{$CFG->enrol_localcoursefield} = $courseid;
            return $course;

        } else if ($rs->RecordCount() > 1) {
            // Woops! There is more than one course with this ID.
            // We cannot tell which is the right one! Log this
            // and return with error.
            if ($log_errors) {
                error_log("User enrolled to course $courseid, but there "
                          . "is more than one course with that value in "
                          . "the enrolment database (in field "
                          . "{$CFG->enrol_courseid}) \n");
            }

        } else {
            // We didn't find the course in the external enrolment
            // database. Log it and return with error.
            if ($log_errors) {
                error_log("User enrolled to a nonexistant course $courseid "
                          . "(course not found in enrolment database) ");
            }
        }
    }
    return false;
}

// get_category
//
// get_category returns the id of a given course category. If
// $CFG->enrol_db_autocreate_category is set and the category doesn't
// exist, it creates it and returns the new id. Otherwise it returns
// false.
//
// if $CFG->enrol_category_separator is set, it can handle
// subcategories of any depth. You just need to specify the 'path' of
// the subcategory as the names of the categories separated by the
// value of the separator. For example, if we use '/' as the
// separator, we can specify 'category1/category2/category3' if we are
// interested in a category called 'category3' that is inside a
// category called 'category2' that is inside a category called
// 'category1' that is a top level category.
//
// @param $category the name of the category
// @return category id (int) or false.
// @uses $CFG
function get_category($category) {
    global $CFG;

    if(empty($CFG->enrol_category_separator)) {
        if (empty($CFG->enrol_autocreate_category)) {
            if ($id = get_field('course_categories', 'id', 'name', addslashes($category))) {
                return $id;
            }
            error_log("Category '$category' not found (and not autocreating categories). " .
                      "Using default category");
            return false;
        } else {
            $categories = array($category);
        }
    } else {
        if ((strpos($category, $CFG->enrol_category_separator) === 0)
            || (strrpos($category, $CFG->enrol_category_separator) === (strlen($category) - 1))) {
            error_log('Category name syntax invalid (cannot start or end with category ' .
                      'separator): '.$category);
            return false;
        }
        $categories = explode($CFG->enrol_category_separator, $category);
    }

    // Start checking/creating categories at the top level.
    $parentid = 0;
    $parentpath = '';
    foreach ($categories as $depth => $categoryname) {
        if ($category = get_record('course_categories', 'name', addslashes($categoryname),
                                   'parent', $parentid)) {
            $categoryid = $category->id;
            $parentid = $category->id;
            $parentpath = $category->path;
            continue;
        }
        if (empty($CFG->enrol_autocreate_category)) {
            error_log("Category '$categoryname' not found (and not autocreating categories). " .
                      "Using default category");
            return false;
        }
        $newcategory = new stdClass();
        $newcategory->name = addslashes($categoryname);
        $newcategory->description = addslashes($categoryname);
        $newcategory->sortorder = 999;
        $newcategory->parent = $parentid;
        $newcategory->depth = $depth + 1;
        if (!$newcategory->id = insert_record('course_categories', $newcategory)) {
            error_log("Could not create the new category: '$newcategory->name'");
            return false;
        } else {
            $newcategory->path = $parentpath . '/' . $newcategory->id;
            update_record ('course_categories', $newcategory);
            $newcategory->context = get_context_instance(CONTEXT_COURSECAT, $newcategory->id);
            mark_context_dirty($newcategory->context->path);
            $parentid = $newcategory->id;
            $parentpath = $newcategory->path;
            $categoryid = $newcategory->id;
        }
    }
    return $categoryid;
}

/**
 * Backups a course and then restores
 * that backup into a destination course.
 *
 * @param object $fromcourse The course to backup and restore
 * @param int $destcourseid The destination for the restore
 * @return boolean
 **/
function backup_and_restore_into_course($fromcourse, $destcourseid) {
    global $CFG, $USER;

    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/blocklib.php');
    require_once($CFG->libdir.'/wiki_to_markdown.php');
    require_once($CFG->libdir.'/xmlize.php');
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->dirroot.'/backup/lib.php');
    require_once($CFG->dirroot.'/backup/backuplib.php');
    require_once($CFG->dirroot.'/backup/restorelib.php');
    require_once($CFG->dirroot.'/backup/bb/restore_bb.php');

    // Same setup as in admin/cron.php
    $USER = get_admin();
    $USER->timezone = $CFG->timezone;
    $USER->lang = '';
    $USER->theme = '';

    // Look in backupfiles for the backup file or generate a new one
    if (array_key_exists($fromcourse->id, $this->backupfiles) and $this->backupfiles[$fromcourse->id] === false) {
        error_log("Failed to restore course with id = $fromcourse->id into course with id = $destcourseid because backup has failed once already");
        return false;

    } else if (!array_key_exists($fromcourse->id, $this->backupfiles)) {
        // Grab backup file (may actually trigger a backup)
        if (!$this->backupfiles[$fromcourse->id] = $this->get_backup_file($fromcourse)) {
            return false;
        }
    }

    // Should have backup file now - import it into destination course
    if (!@import_backup_file_silently($this->backupfiles[$fromcourse->id], $destcourseid, true, false)) {
        error_log("Failed to restore course with id = $fromcourse->id into course with id = $destcourseid because import failed");
        return false;
    }

    // Update the time since backup/restores can take a long time
    $this->cron_set_start();

    return true;
}

/**
 * Look for a backup file in
 * backupdata/template or create
 * a new backup
 *
 * @param object $course Find a backup file for this course
 * @return mixed
 **/
function get_backup_file($course) {
    global $CFG;

    $templatedir = "$CFG->dataroot/$course->id/backupdata/template";

    // If we are using template dir, check there for a backup file first
    if (!empty($CFG->enrol_restore_usetemplate) and is_dir($templatedir)) {
        $files = get_directory_list($templatedir, '', false);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) != 'zip') {
                continue;
            }
            if (strpos(strtolower($file), strtolower($course->shortname)) !== false) {
                // Found what we hope is a backup file
                return "$templatedir/$file";
            }
        }
    }

    $errorstring = '';
    $backupprefs = array();
    if ($backupfile = @backup_course_silently($course->id, $backupprefs, $errorstring)) {
        // If using templates dir, then save backup file to it
        if (!empty($CFG->enrol_restore_usetemplate) and make_upload_directory("$course->id/backupdata/template", false)) {
            $name = pathinfo($backupfile, PATHINFO_BASENAME);

            if (rename($backupfile, "$templatedir/$name")) {
                return "$templatedir/$name";
            }
        }
        return $backupfile;
    } else {
        error_log("Failed to backup course with id = $course->id.  Error string returned from backup: $errorstring");
        return false;
    }
}

/**
 * Remove all backup files generated
 * during execution except the ones
 * stored in backupdata/template.
 *
 * @return boolean
 **/
function delete_backup_files() {
    global $CFG;

    require_once($CFG->libdir.'/filelib.php');

    foreach ($this->backupfiles as $backupfile) {
        if (strpos($backupfile, 'backupdata/template') === false) {
            fulldelete($backupfile);
        }
    }
    $this->backupfiles = array();

    return true;
}

/**
 * Cron hook
 *
 * @return void
 **/
function cron() {
    global $CFG;

    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->dirroot.'/lib/blocklib.php');

    if ($this->cron_running()) {
        error_log("Cron is still running or has not expired yet.  Will try again next cron.");
        return;
    }
    $this->cron_set_start();

    // If we have settings to handle roles individually, through each type of
    // role and update it.  Otherwise, just got through once (with no role
    // specified).
    $roles = !empty($CFG->enrol_db_remoterolefield) && !empty($CFG->enrol_db_localrolefield)
        ? get_records('role')
        : array(null);

    foreach ($roles as $role) {
        $this->sync_enrolments($role);
    }

    $this->process_courses_without_enrolments();

    // sync metacourses
    if (function_exists('sync_metacourses')) {
        sync_metacourses();
    }

    $info = get_performance_info();
    error_log('Performance info from enrol/database cron: '.$info['txt']);

    $this->cron_set_end();
}

/**
 * Determine if the cron is still running
 *
 * @return boolean
 **/
function cron_running() {
    if ($started = get_config(NULL, 'enrol_db_cronstarted')) {
        $timetocheck = time() - HOURSECS;

        if ($started != 0 and $started > $timetocheck) {
            return true;
        }
    }

    return false;
}

/**
 * Set the time for the cronstarted
 *
 * If not forced, then will only actually
 * set the config every 100 calls - this is
 * nice for calling within loops.
 *
 * @return boolean
 **/
function cron_set_start($force = true) {
    static $count = 1;

    $return = true;

    if (!$force) {
        $count++;
    }
    if ($force or $count % 100 == 0) {
        $return = set_config('enrol_db_cronstarted', time());
    }
    return $return;
}

/**
 * End the cron time
 *
 * @return boolean
 **/
function cron_set_end() {
    return unset_config('enrol_db_cronstarted');
}

} // end of class

?>
