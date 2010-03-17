<?php  // $Id: view.php,v 1.1.2.4 2010/03/17 17:19:27 adelamarre Exp $

/**
 * This page prints a particular instance of adobeconnect
 *
 * @author  Your Name <adelamarre@remote-learner.net>
 * @version $Id: view.php,v 1.1.2.4 2010/03/17 17:19:27 adelamarre Exp $
 * @package mod/adobeconnect
 */

/// (Replace adobeconnect with the name of your module and remove this line)

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/meeting_detail_form.php');
require_once(dirname(__FILE__).'/connect_class.php');
require_once(dirname(__FILE__).'/connect_class_dom.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$a  = optional_param('a', 0, PARAM_INT);  // adobeconnect instance ID
$groupid = optional_param('group', 0, PARAM_INT);

if ($id) {
    if (! $cm = get_coursemodule_from_id('adobeconnect', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = get_record('course', 'id', $cm->course)) {
        error('Course is misconfigured');
    }

    if (! $adobeconnect = get_record('adobeconnect', 'id', $cm->instance)) {
        error('Course module is incorrect');
    }

} else if ($a) {
    if (! $adobeconnect = get_record('adobeconnect', 'id', $a)) {
        error('Course module is incorrect');
    }
    if (! $course = get_record('course', 'id', $adobeconnect->course)) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('adobeconnect', $adobeconnect->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

global $CFG, $USER;

add_to_log($course->id, "adobeconnect", "view", "view.php?id=$cm->id", "$adobeconnect->id");

/// Print the page header
$stradobeconnects = get_string('modulenameplural', 'adobeconnect');
$stradobeconnect  = get_string('modulename', 'adobeconnect');

$navlinks = array();
$navlinks[] = array('name' => $stradobeconnects, 'link' => "index.php?id=$course->id", 'type' => 'activity');
$navlinks[] = array('name' => format_string($adobeconnect->name), 'link' => '', 'type' => 'activityinstance');

$navigation = build_navigation($navlinks);

print_header_simple(format_string($adobeconnect->name), '', $navigation, '', '', true,
              update_module_button($cm->id, $course->id, $stradobeconnect), navmenu($course, $cm));

// Check for empy group id, if empty check if this user belongs to any
// group in the course and set the first group found as the default.
// This is required for the groups selection drop down box and for the
// initial display of the meeting details.

if (0 != $cm->groupmode){
    if (empty($groupid)) {
        $groups = groups_get_user_groups($course->id, $USER->id);

        if (array_key_exists(0, $groups)) {
            $groupid = current($groups[0]);
        }

        if (empty($groupid)) {
            $groupid = 0;
            notify(get_string('usergrouprequired', 'adobeconnect'));
            print_footer($course);
            die();
        }
    }
} else {
    $groupid = 0;
}

/// Print the main part of the page
$usrgroups = groups_get_user_groups($cm->course, $USER->id);
$usrgroups = $usrgroups[0]; // Just want groups and not groupings

$sql = "SELECT meetingscoid FROM {$CFG->prefix}adobeconnect_meeting_groups amg WHERE ".
       "amg.instanceid = {$cm->instance}";


$meetscoids = get_records_sql($sql);
$recording = array();

if (!empty($meetscoids)) {
    $recscoids = array();

    $aconnect = aconnect_login();

    // Get the forced recordings folder sco-id
    // Get recordings that are based off of the meeting
    $fldid = aconnect_get_folder($aconnect, 'forced-archives');
    foreach($meetscoids as $scoid) {

        $data = aconnect_get_recordings($aconnect, $fldid, $scoid->meetingscoid);

        if (!empty($data)) {
          // Store recordings in an array to be moved to the Adobe shared folder later on
          $recscoids = array_merge($recscoids, array_keys($data));

        }

    }

    // Move the meetings to the shared content folder
    if (!empty($recscoids)) {
        $recscoids = array_flip($recscoids);

        if (aconnect_move_to_shared($aconnect, $recscoids)) {
            // do nothing
        }
    }

    //Get the shared content folder sco-id
    // Create a list of recordings moved to the shared content folder
    $fldid = aconnect_get_folder($aconnect, 'content');
    foreach($meetscoids as $scoid) {

        $data = aconnect_get_recordings($aconnect, $fldid, $scoid->meetingscoid);

        if (!empty($data)) {
            $recording[] = $data;
        }
//        print_object(aconnect_get_recordings($aconnect, $fldid, $scoid->meetingscoid));
    }


    // Check if the user exists and if not create the new user
    if (!($usrprincipal = aconnect_user_exists($aconnect, $USER))) {
        if (!($usrprincipal = aconnect_create_user($aconnect, $USER))) {
            // DEBUG
            debugging("error creating user", DEBUG_DEVELOPER);

//            print_object("error creating user");
//            print_object($aconnect->_xmlresponse);
            $validuser = false;
        }
    }

    // Check the user's capability and assign them view permissions to the recordings folder
    // if it's a public meeting give them permissions regardless
    if ($cm->groupmode) {
        $context = get_context_instance(CONTEXT_MODULE, $id);

        if (has_capability('mod/adobeconnect:meetingpresenter', $context, $USER->id) or
            has_capability('mod/adobeconnect:meetingparticipant', $context, $USER->id)) {
            if (aconnect_assign_user_perm($aconnect, $usrprincipal, $fldid, ADOBE_VIEW_ROLE)) {
                //DEBUG
                // echo 'true';
            } else {
                //DEBUG
                debugging("error assign user recording folder permissions", DEBUG_DEVELOPER);
//                print_object('error assign user recording folder permissions');
//                print_object($aconnect->_xmlrequest);
//                print_object($aconnect->_xmlresponse);
            }
        }
    } else {
        aconnect_assign_user_perm($aconnect, $usrprincipal, $fldid, ADOBE_VIEW_ROLE);
    }

    aconnect_logout($aconnect);
}

// Log in the current user
$login = $USER->username;
$password  = $USER->username;

$aconnect = new connect_class_dom($CFG->adobeconnect_host, $CFG->adobeconnect_port);
$aconnect->request_http_header_login(1, $login);
$adobesession = $aconnect->get_cookie();

if (($formdata = data_submitted($CFG->wwwroot . '/mod/adobeconnect/view.php')) && confirm_sesskey()) {

    // Edit participants
    if (isset($formdata->participants)) {
//        $context = get_context_instance(CONTEXT_MODULE, $id);
        // Using course context because we want the assign page to use that context
        // Otherwise the user would have to re-assign users for every activity instance
        $context = get_context_instance(CONTEXT_COURSE, $course->id);

        $roleid = get_field('role', 'id', 'shortname', 'adobeconnectpresenter');

        if (!empty($roleid)) {
            redirect("assign.php?id=$id&amp;contextid={$context->id}&amp;roleid=$roleid&amp;groupid={$formdata->group}", '', 0);
        } else {
            notice("error: error finding adobeconnectpresenter role");
        }
    }

    // Edit participants
    if (isset($formdata->viewcontent)) {
        redirect("viewcontent.php?id=$id&amp;groupid=$groupid", '', 0);
    }
}

if ($cm->groupmode) {
    groups_print_course_menu($course, "view.php?id=$id");
}

$aconnect = aconnect_login();

// Get the Meeting details
$scoid = get_field('adobeconnect_meeting_groups', 'meetingscoid', 'instanceid', $adobeconnect->id, 'groupid', $groupid);
$meetfldscoid = aconnect_get_folder($aconnect, 'meetings');
$filter = array('filter-sco-id' => $scoid);

if (($meeting = aconnect_meeting_exists($aconnect, $meetfldscoid, $filter))) {
    $meeting = current($meeting);
} else {
    notice('No meeting exists on the server', '', $course);
    aconnect_logout($aconnect);
    die();
}


aconnect_logout($aconnect);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

$sesskey = !empty($USER->sesskey) ? $USER->sesskey : '';

echo '<br /><br />';
echo '<form name="meetingdetail" action="' . $CFG->wwwroot . '/mod/adobeconnect/view.php" method="post">' . "\n";

// print meeting detail field set
echo '<div id="aconfldset1" class="aconfldset">'."\n";
echo '<fieldset>'."\n";

echo '<legend>'.get_string('meetinginfo', 'adobeconnect').'</legend>'."\n";

echo '<div class="aconmeetinforow">'."\n";

echo '<div class="aconlabeltitle" id="aconmeetnametitle">'."\n";
echo '<label for="lblmeetingnametitle">'.get_string('meetingname', 'adobeconnect').':</label>'."\n";
echo '</div>'."\n";

echo '<div class="aconlabeltext" id="aconmeetnametxt">'."\n";
echo '<label for="lblmeetingname">'.format_string($meeting->name).'</label><br />'."\n";
echo '</div>'."\n";

echo '</div>'."\n";

if (has_capability('mod/adobeconnect:meetingpresenter', $context) or
    has_capability('mod/adobeconnect:meetinghost', $context)) {

    $url = 'http://' . $CFG->adobeconnect_meethost . ':'
                     . $CFG->adobeconnect_port . $meeting->url;
    echo '<div class="aconmeetinforow">'."\n";

    echo '<div class="aconlabeltitle" id="aconmeeturltitle">'."\n";
    echo '<label for="lblmeetingurltitle">'.get_string('meeturl', 'adobeconnect').':</label>'."\n";
    echo '</div>'."\n";

    echo '<div class="aconlabeltext" id="aconmeeturltext">'."\n";
    echo '<label for="lblmeetingurl">'.$url.'</label><br />'."\n";
    echo '</div>'."\n";

    echo '</div>'."\n";
}

echo '<div class="aconmeetinforow">'."\n";

echo '<div class="aconlabeltitle" id="aconmeetstarttitle">'."\n";
echo '<label for="lblmeetingstarttitle">'.get_string('meetingstart', 'adobeconnect').':</label>'."\n";
echo '</div>'."\n";

$time = date("D F, Y", $adobeconnect->starttime);
echo '<div class="aconlabeltext" id="aconmeetstarttxt">'."\n";
echo '<label for="lblmeetingstart">'.$time.'</label><br />'."\n";
echo '</div>'."\n";

echo '</div>'."\n";

echo '<div class="aconmeetinforow">'."\n";

echo '<div class="aconlabeltitle" id="aconmeetendtitle">'."\n";
echo '<label for="lblmeetingendtitle">'.get_string('meetingend', 'adobeconnect').':</label>'."\n";
echo '</div>'."\n";

$time = date("D F, Y", $adobeconnect->endtime);
echo '<div class="aconlabeltext" id="aconmeetendtxt">'."\n";
echo '<label for="lblmeetingend">'.$time.'</label><br />'."\n";
echo '</div>'."\n";

echo '</div>'."\n";

echo '<div class="aconmeetinforow">'."\n";

echo '<div class="aconlabeltitle" id="aconmeetsummarytitle">'."\n";
echo '<label for="lblmeetingsummarytitle">'.get_string('meetingintro', 'adobeconnect').':</label>'."\n";
echo '</div>'."\n";

echo '<div class="aconlabeltext" id="aconmeetsummarytxt">'."\n";
echo '<label for="lblmeetingsummary">'.$adobeconnect->intro.'</label><br />'."\n";
echo '</div>'."\n";

echo '</div>'."\n";

echo '</fieldset>'."\n";
echo '</div>'."\n";

echo '<br />';

echo '<div class="aconbtnrow">'."\n";

echo '<div class="aconbtnjoin">'."\n";
echo button_to_popup_window('/mod/adobeconnect/join.php?id='.$id.'&amp;sesskey='.$sesskey.'&amp;groupid='.$groupid,
                            'btnname', get_string('joinmeeting', 'adobeconnect'), 900, 900, null, null, true);
echo '</div>'."\n";

if (has_capability('mod/adobeconnect:meetingpresenter', $context, $USER->id) or
    has_capability('mod/adobeconnect:meetinghost', $context, $USER->id)){
    echo '<div class="aconbtnroles">'."\n";
    echo '<input type="submit" name="participants" value="'.get_string('selectparticipants', 'adobeconnect').'">';
    echo '</div>'."\n";
}

echo '</div>'."\n";

echo '<input type="hidden" name="id" value="'.$id.'">'."\n";
echo '<input type="hidden" name="group" value="'.$groupid.'">'."\n";
echo '<input type="hidden" name="sesskey" value="'.$sesskey.'">'."\n";

echo '</form>'."\n";

echo '<br />';

$showrecordings = false;
// Check if meeting is private, if so check the user's capability.  If public show recorded meetings
if (!$adobeconnect->meetingpublic) {
    if (has_capability('mod/adobeconnect:meetingpresenter', $context, $USER->id) or
        has_capability('mod/adobeconnect:meetingparticipant', $context, $USER->id)) {
            $showrecordings = true;
    }
} else {
    $showrecordings = true;
}

$recordings = $recording;
if ($showrecordings and !empty($recordings)) {

    echo '<div id="aconfldset2" class="aconfldset">'."\n";
    echo '<fieldset>'."\n";
    echo '<legend>'.get_string('recordinghdr', 'adobeconnect').'</legend>'."\n";

    echo '<div class="aconrecording">'."\n";
    foreach ($recordings as $key => $recordinggrp) {
        if (!empty($recordinggrp)) {
            foreach($recordinggrp as $recording) {
                echo '<div class="aconrecordingrow">'."\n";
                echo '<a href="http://'.$CFG->adobeconnect_meethost.':'.
                     $CFG->adobeconnect_port.$recording->url.'?session='.$adobesession.
                     '" target="_blank">'.$recording->name.'</a><br />';
                echo '</div>'."\n";
            }
        }
    }
    echo '</div>'."\n";

    echo '</fieldset>'."\n";
    echo '</div>'."\n";
}

/// Finish the page
print_footer($course);

?>