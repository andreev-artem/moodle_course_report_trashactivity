<?php

    if (!defined('MOODLE_INTERNAL')) {
        die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
    }

    if (has_capability('coursereport/trashactivity:view', $context)) {
        echo '<p>';
        echo "<a href=\"{$CFG->wwwroot}/course/report/trashactivity/index.php?id={$course->id}\">";
        echo get_string('title', 'report_trashactivity')."</a>\n";
        echo '</p>';
    }
?>
