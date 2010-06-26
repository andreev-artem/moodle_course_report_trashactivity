<?php  // $Id: index.php,v 1.0 2008/12/06 argentum@cdp.tsure.ru Exp $

require_once('../../../config.php');
require_once($CFG->dirroot.'/lib/tablelib.php');

$id         = required_param('id', PARAM_INT); // course id.
$clear      = optional_param('clear', false, PARAM_INT);
$all        = optional_param('all', false, PARAM_BOOL);

if (!$course = get_record('course', 'id', $id)) {
    print_error('invalidcourse');
}

function check_trash_activity($sql, &$users) {
    $results = get_records_sql($sql);
 
    if ($results === false) {
        return;
    }
    
    foreach ($results as $user) {
        if (!in_array($user->user, $users)) {
            $users[] = $user->user;
        }
    }
}    

require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('coursereport/trashactivity:view', $context);

add_to_log($course->id, "course", "report trashactivity", "report/trashactivity/index.php?id=$course->id", $course->id);

$strtrashactivity = get_string('title', 'report_trashactivity');
$strreports       = get_string('reports');

$navlinks = array();
$navlinks[] = array('name' => $strreports, 'link' => "../../report.php?id=$course->id", 'type' => 'misc');
$navlinks[] = array('name' => $strtrashactivity, 'link' => null, 'type' => 'misc');
$navigation = build_navigation($navlinks);
print_header("$course->shortname: $strtrashactivity", $course->fullname, $navigation);

if ($clear !== false) {
    $context = get_context_instance(CONTEXT_COURSE, $id);
    require_capability('moodle/role:manage', $context);
    require_capability('mod/quiz:deleteattempts', $context);
    require_capability('mod/quiz:manage', $context);
    require_capability('mod/lesson:manage', $context);
    require_capability('mod/lesson:edit', $context);
    require_capability('mod/assignment:grade', $context);
    $userid = $clear;
    
    require_once($CFG->dirroot.'/mod/quiz/locallib.php');
    require_once($CFG->dirroot.'/mod/lesson/lib.php');
    require_once($CFG->dirroot.'/mod/assignment/lib.php');

    // delete all quiz activity
    $quizzessql = "SELECT DISTINCT q.* FROM {$CFG->prefix}quiz q INNER JOIN {$CFG->prefix}quiz_attempts a
                    ON a.quiz=q.id AND a.userid=$userid AND q.course = $id";
    if ($quizzes = get_records_sql($quizzessql)) {
        foreach ($quizzes as $quiz) {
            $attemptssql = "SELECT a.* FROM {$CFG->prefix}quiz_attempts a
            				WHERE a.quiz=$quiz->id AND a.userid=$userid";
            $attempts = get_records_sql($attemptssql);
            foreach ($attempts as $attempt) {
                quiz_delete_attempt( $attempt, $quiz );
            }
        }
    }

    // delete all lesson activity
    $lessons = get_fieldset_select('lesson', 'id', "course = $id");
    if (!empty($lessons)) {
        $lessons = implode(',', $lessons);
        
        /// Clean up the timer table
        delete_records_select('lesson_timer', "userid=$userid AND lessonid IN ($lessons)");
    
        /// Remove the grades from the grades and high_scores tables
        delete_records_select('lesson_grades', "userid=$userid AND lessonid IN ($lessons)");
        delete_records_select('lesson_high_scores', "userid=$userid AND lessonid IN ($lessons)");
    
        /// Remove attempts
        delete_records_select('lesson_attempts', "userid=$userid AND lessonid IN ($lessons)");
    
        /// Remove seen branches  
        delete_records_select('lesson_branch', "userid=$userid AND lessonid IN ($lessons)");
    }

    // delete all assignment submissions
    $assignmentlist = array();
    // delete submission files
    $assignmentssql = "SELECT DISTINCT a.id, a.course FROM {$CFG->prefix}assignment a INNER JOIN {$CFG->prefix}assignment_submissions s
                       ON s.assignment=a.id AND s.userid=$userid AND a.course = $id";
    if ($assignments = get_records_sql($assignmentssql)) {
        foreach ($assignments as $assignment) {
            fulldelete($CFG->dataroot.'/'.$assignment->course.'/moddata/assignment/'.$assignment->id.'/'.$userid);
            $assignmentlist[] = $assignment->id;
        }
    }

    // delete submission records
    if (!empty($assignmentlist)) {
        $assignmentlist = implode(',', $assignmentlist);
        delete_records_select('assignment_submissions', "userid=$userid AND assignment IN ($assignmentlist)");
    }
    
    // finally, delete all grade records to clean up database
    $sql = "SELECT g.id 
            FROM {$CFG->prefix}grade_grades g INNER JOIN {$CFG->prefix}grade_items i
            ON g.itemid = i.id AND i.courseid = $id AND g.userid=$userid";
    $grades = get_fieldset_sql($sql);
    if (!empty($grades)) {
        $grades = implode(',', $grades);
        delete_records_select('grade_grades', "id IN ($grades)");
    }
    
    echo '<div align=center>'.get_string('changessaved').'</div><br />';
}

$users = array();
$sql = "SELECT DISTINCT tbl.userid as user FROM {$CFG->prefix}quiz_attempts tbl 
        INNER JOIN {$CFG->prefix}quiz q ON q.id = tbl.quiz AND q.course = $id WHERE tbl.userid NOT IN
        (SELECT ra.userid FROM {$CFG->prefix}role_assignments ra WHERE ra.contextid={$context->id})";
check_trash_activity($sql, $users);

$sql = "SELECT DISTINCT tbl.userid as user FROM {$CFG->prefix}quiz_grades tbl 
        INNER JOIN {$CFG->prefix}quiz q ON q.id = tbl.quiz AND q.course = $id WHERE tbl.userid NOT IN
        (SELECT ra.userid FROM {$CFG->prefix}role_assignments ra WHERE ra.contextid={$context->id})";
check_trash_activity($sql, $users);

$sql = "SELECT DISTINCT tbl.userid as user FROM {$CFG->prefix}assignment_submissions tbl 
        INNER JOIN {$CFG->prefix}assignment a ON a.id = tbl.assignment AND a.course = $id WHERE tbl.userid NOT IN
        (SELECT ra.userid FROM {$CFG->prefix}role_assignments ra WHERE ra.contextid={$context->id})";
check_trash_activity($sql, $users);

$sql = "SELECT DISTINCT tbl.userid as user FROM {$CFG->prefix}lesson_attempts tbl 
        INNER JOIN {$CFG->prefix}lesson l ON l.id = tbl.lessonid AND l.course = $id WHERE tbl.userid NOT IN
        (SELECT ra.userid FROM {$CFG->prefix}role_assignments ra WHERE ra.contextid={$context->id})";
check_trash_activity($sql, $users);

$sql = "SELECT DISTINCT tbl.userid as user FROM {$CFG->prefix}lesson_grades tbl 
        INNER JOIN {$CFG->prefix}lesson l ON l.id = tbl.lessonid AND l.course = $id WHERE tbl.userid NOT IN
        (SELECT ra.userid FROM {$CFG->prefix}role_assignments ra WHERE ra.contextid={$context->id})";
check_trash_activity($sql, $users);

$sql = "SELECT DISTINCT tbl.userid as user FROM {$CFG->prefix}lesson_branch tbl 
        INNER JOIN {$CFG->prefix}lesson l ON l.id = tbl.lessonid AND l.course = $id WHERE tbl.userid NOT IN
        (SELECT ra.userid FROM {$CFG->prefix}role_assignments ra WHERE ra.contextid={$context->id})";
check_trash_activity($sql, $users);

$sql = "SELECT DISTINCT tbl.userid as user FROM {$CFG->prefix}lesson_timer tbl 
        INNER JOIN {$CFG->prefix}lesson l ON l.id = tbl.lessonid AND l.course = $id WHERE tbl.userid NOT IN
        (SELECT ra.userid FROM {$CFG->prefix}role_assignments ra WHERE ra.contextid={$context->id})";
check_trash_activity($sql, $users);

if ($users) {
    sort($users);
    $sql = "SELECT u.id as id, u.firstname as fname, u.lastname as lname, u.deleted as deleted
            FROM {$CFG->prefix}user u WHERE u.id IN (".implode(',',$users).")";
    $userdata = get_records_sql($sql);

    if ($all) {
        $SESSION->bulk_users = array();
        foreach ($users as $userid) {
            if (array_key_exists($userid,$userdata)) {
                $SESSION->bulk_users[] = $userid;
            }
        }
        echo '<div align=center>'.get_string('listloaded', 'report_trashactivity').'</div><br />';
    }

    $tablecolumns = array('name','link');
    $tableheaders = array(get_string('firstname').'/'.get_string('lastname'),'');
    $table = new flexible_table('course-report-trashactivity-'.$id);
    
    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    
    $table->set_attribute('cellspacing', '0');
    $table->set_attribute('id', 'trashactivity-course');
    $table->set_attribute('class', 'boxaligncenter generaltable');
    $table->define_baseurl('index.php');
    $table->setup();
    foreach ($users as $userid) {
        if (!array_key_exists($userid,$userdata)) {
            $userinfo = '['.get_string('permdeleted', 'report_trashactivity').']';
        } else {
            $user = $userdata[$userid];
            $userinfo = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'">'.$user->fname.' '.$user->lname.'</a>';
            if ($user->deleted) {
                $userinfo .= ' ('.get_string('deleted', 'report_trashactivity').')';
            }
        }
        $table->add_data(array($userinfo, '<a href="index.php?id='.$id.'&clear='.$userid.'">'.get_string('clear', 'report_trashactivity').'</a>'));
    }
    
    echo '<div align=center>'.get_string('header', 'report_trashactivity').'</div><br />';
    $table->print_html();
    echo '<br /><form action="index.php">
    	<div align=center>
    	<input type=submit value="'.get_string('selectall', 'report_trashactivity').'">
    	<input type=hidden name=id value='.$id.'>
    	<input type=hidden name=all value=1>
    	</div></form>';
} else {
    echo '<div align=center>'.get_string('nodata', 'report_trashactivity').'</div>';
}

print_footer();
?>
