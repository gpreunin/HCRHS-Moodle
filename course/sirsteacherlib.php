<?php

// gwp 2010 May 7
// MDL-22343 code 
function get_sirs_teacher_courses($userid, $roleID){

    global $CFG,$USER;

    $my_course_list = "";

 //   $sql =  "SELECT DISTINCT mra.roleid
 //            FROM {$CFG->prefix}role_assignments mra, {$CFG->prefix}role r
 //            WHERE mra.userid = $userid AND mra.enrol='database'
 //            AND mra.roleid = $roleID ";

$sql=    "SELECT c.id
        FROM {$CFG->prefix}course c
        WHERE c.id 
          IN (SELECT ctx.instanceid
           FROM {$CFG->prefix}context ctx
           WHERE ctx.id 
              IN (SELECT mra.contextid
              FROM {$CFG->prefix}role_assignments mra
              WHERE mra.userid = $userid AND mra.enrol='database'
              AND mra.roleid = $roleID
              )
           )";

     if ($rs = get_recordset_sql($sql)) {

      while ($next_record = rs_fetch_next_record($rs)){
          $my_course_list = $my_course_list . $next_record->id . ',';
      }
      // remove the last comma from the sring
      $my_course_list = substr($my_course_list, 0, strlen($my_course_list)-1);

     }

     return $my_course_list;

}

function get_enrolled_courses_search($searchterms, $sort='fullname ASC', $page=0,
                                    $recordsperpage=50,&$totalcount,
                                    $sirs_teacher_course_list) {

    global $CFG;


    //to allow case-insensitive search for postgesql
    if ($CFG->dbfamily == 'postgres') {
        $LIKE = 'ILIKE';
        $NOTLIKE = 'NOT ILIKE';   // case-insensitive
        $REGEXP = '~*';
        $NOTREGEXP = '!~*';
    } else {
        $LIKE = 'LIKE';
        $NOTLIKE = 'NOT LIKE';
        $REGEXP = 'REGEXP';
        $NOTREGEXP = 'NOT REGEXP';
    }

    $fullnamesearch = '';
    $summarysearch = '';
    $idnumbersearch = '';
    $shortnamesearch = '';

    foreach ($searchterms as $searchterm) {

        $NOT = ''; /// Initially we aren't going to perform NOT LIKE searches, only MSSQL and Oracle
                   /// will use it to simulate the "-" operator with LIKE clause

    /// Under Oracle and MSSQL, trim the + and - operators and perform
    /// simpler LIKE (or NOT LIKE) queries
        if ($CFG->dbfamily == 'oracle' || $CFG->dbfamily == 'mssql') {
            if (substr($searchterm, 0, 1) == '-') {
                $NOT = ' NOT ';
            }
            $searchterm = trim($searchterm, '+-');
        }

        if ($fullnamesearch) {
            $fullnamesearch .= ' AND ';
        }
        if ($summarysearch) {
            $summarysearch .= ' AND ';
        }
        if ($idnumbersearch) {
            $idnumbersearch .= ' AND ';
        }
        if ($shortnamesearch) {
            $shortnamesearch .= ' AND ';
        }

        if (substr($searchterm,0,1) == '+') {
            $searchterm      = substr($searchterm,1);
            $summarysearch  .= " c.summary $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $fullnamesearch .= " c.fullname $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $idnumbersearch  .= " c.idnumber $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $shortnamesearch  .= " c.shortname $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
        } else if (substr($searchterm,0,1) == "-") {
            $searchterm      = substr($searchterm,1);
            $summarysearch  .= " c.summary $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $fullnamesearch .= " c.fullname $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $idnumbersearch .= " c.idnumber $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $shortnamesearch .= " c.shortname $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
        } else {
            $summarysearch .= ' summary '. $NOT . $LIKE .' \'%'. $searchterm .'%\' ';
            $fullnamesearch .= ' fullname '. $NOT . $LIKE .' \'%'. $searchterm .'%\' ';
            $idnumbersearch .= ' idnumber '. $NOT . $LIKE .' \'%'. $searchterm .'%\' ';
            $shortnamesearch .= ' shortname '. $NOT . $LIKE .' \'%'. $searchterm .'%\' ';
        }

    }

    $sql = "SELECT c.*,
                   ctx.id AS ctxid, ctx.path AS ctxpath,
                   ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel
            FROM {$CFG->prefix}course c
            JOIN {$CFG->prefix}context ctx
             ON (c.id = ctx.instanceid AND ctx.contextlevel=".CONTEXT_COURSE.")
            WHERE (( $fullnamesearch ) OR ( $summarysearch ) OR ( $idnumbersearch ) OR ( $shortnamesearch ))
                  AND category > 0
                  AND c.id IN ($sirs_teacher_course_list)
            ORDER BY " . $sort;

    $courses = array();

    if ($rs = get_recordset_sql($sql)) {


        // Tiki pagination
        $limitfrom = $page * $recordsperpage;
        $limitto   = $limitfrom + $recordsperpage;
        $c = 0; // counts how many visible courses we've seen

        while ($course = rs_fetch_next_record($rs)) {
            $course = make_context_subobj($course);
            if ($course->visible || has_capability('moodle/course:viewhiddencourses', $course->context)) {
                // Don't exit this loop till the end
                // we need to count all the visible courses
                // to update $totalcount
                if ($c >= $limitfrom && $c < $limitto) {
                    $courses[] = $course;
                }
                $c++;
            }
        }
    }

    // our caller expects 2 bits of data - our return
    // array, and an updated $totalcount
    $totalcount = $c;
    return $courses;
}

function get_courses_notin_sirsteacher_metacourse($metacourseid,$my_course_list,$count=false) {
    global $CFG;

    if ($count) {
        $sql  = "SELECT COUNT(c.id)";
    } else {
        $sql = "SELECT c.id,c.shortname,c.fullname";
    }

    $alreadycourses = get_courses_in_metacourse($metacourseid);


    $sql .= " FROM {$CFG->prefix}course c
              WHERE "
              .((!empty($my_course_list)) ?
              "c.id IN (".$my_course_list.") AND "
              : "")
              .((!empty($alreadycourses)) ?
              "c.id NOT IN (".implode(',',array_keys($alreadycourses)).") AND "
              : "")." c.id !=$metacourseid
              AND c.id != ".SITEID."
              AND c.metacourse != 1 ".((empty($count)) ? " ORDER BY c.shortname" : "");

    return get_records_sql($sql);
}

function count_courses_notin_sirsteacher_metacourse($metacourseid, $my_course_list) {
    global $CFG;
    
    $alreadycourses = get_courses_in_metacourse($metacourseid);

    $sql = "SELECT COUNT(c.id) AS notin
            FROM {$CFG->prefix}course c
              WHERE "
              .((!empty($my_course_list)) ?
              "c.id IN (".$my_course_list.") AND "
              : "")
              .((!empty($alreadycourses)) ?
              "c.id NOT IN (".implode(',',array_keys($alreadycourses)).") AND "
              : "")." c.id !=$metacourseid
              AND c.id != ".SITEID."
              AND c.metacourse != 1 ";

    if (!$count = get_record_sql($sql)) {
        return 0;
    }

    return $count->notin;
}
?>
