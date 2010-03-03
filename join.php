<?php // $Id: join.php,v 1.1.2.2 2010/03/03 18:04:42 arborrow Exp $
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/connect_class.php');

$id       = required_param('id', PARAM_INT); // course_module ID, or
$groupid  = required_param('groupid', PARAM_INT);
$sesskey  = required_param('sesskey', PARAM_ALPHANUM);

if (! $cm = get_coursemodule_from_id('adobeconnect', $id)) {
    error('Course Module ID was incorrect');
}

if (! $course = get_record('course', 'id', $cm->course)) {
    error('Course is misconfigured');
}

if (! $adobeconnect = get_record('adobeconnect', 'id', $cm->instance)) {
    error('Course module is incorrect');
}

require_login($course, true, $cm);

global $CFG, $USER;

add_to_log($course->id, "adobeconnect", "view", "join.php?id=$cm->id&groupid=$groupid&sesskey=$sesskey", "$adobeconnect->id");

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

$usrcanjoin = false;

$usrgroups = groups_get_user_groups($cm->course, $USER->id);
$usrgroups = $usrgroups[0]; // Just want groups and not groupings

// If separate groups is enabled, check if the user is a part of the selected group
if (0 != $cm->groupmode/*$adobeconnect->meetingpublic*/) {
    if (false !== array_search($groupid, $usrgroups)) {
        $usrcanjoin = true;
    }
}

$context = get_context_instance(CONTEXT_COURSE, $cm->course);

// Make sure the user has a role in the course
$crsroles = get_roles_used_in_context($context);

if (empty($crsroles)) {
    $crsroles = array();
}

foreach ($crsroles as $roleid => $crsrole) {
    if (user_has_role_assignment($USER->id, $roleid, $context->id)) {
        $usrcanjoin = true;
    }
}

// user has to be in a group
if ($usrcanjoin and confirm_sesskey($sesskey)) {

    $usrprincipal = 0;
    $validuser = true;
    $groupobj = groups_get_group($groupid);

    // Get the meeting sco-id
    $meetingscoid = get_field('adobeconnect_meeting_groups', 'meetingscoid',
                              'instanceid', $cm->instance, 'groupid', $groupid);

    $aconnect = aconnect_login();

    // Check if the meeting still exists on the Adobe server
    $meetfldscoid = aconnect_get_meeting_folder($aconnect);
    $filter = array('filter-sco-id' => $meetingscoid);
    $meeting = aconnect_meeting_exists($aconnect, $meetfldscoid, $filter);

    if (!empty($meeting)) {
        $meeting = current($meeting);
    }

    if (!($usrprincipal = aconnect_user_exists($aconnect, $USER))) {
        if (!($usrprincipal = aconnect_create_user($aconnect, $USER))) {
            // DEBUG
            print_object("error creating user");
            print_object($aconnect->_xmlresponse);
            $validuser = false;
        }
    }

    $context = get_context_instance(CONTEXT_MODULE, $id);

    // Check the user's capabilities and assign them the Adobe Role
    if (!empty($meetingscoid) and !empty($usrprincipal) and !empty($meeting)) {
        if (has_capability('mod/adobeconnect:meetingpresenter', $context, $USER->id)) {
            if (aconnect_check_user_perm($aconnect, $usrprincipal, $meetingscoid, ADOBE_PRESENTER, true)) {
                //DEBUG
                // echo 'true';
            } else {
                //DEBUG
                print_object('error assign user adobe presenter role');
                print_object($aconnect->_xmlrequest);
                print_object($aconnect->_xmlresponse);
                $validuser = false;
            }
        } elseif (has_capability('mod/adobeconnect:meetingparticipant', $context, $USER->id)) {
            if (aconnect_check_user_perm($aconnect, $usrprincipal, $meetingscoid, ADOBE_PARTICIPANT, true)) {
                //DEBUG
                // echo 'true';
            } else {
                //DEBUG
                print_object('error assign user adobe particpant role');
                print_object($aconnect->_xmlrequest);
                print_object($aconnect->_xmlresponse);
                $validuser = false;
            }
        } elseif (has_capability('mod/adobeconnect:meetinghost', $context, $USER->id)) {
            if (aconnect_check_user_perm($aconnect, $usrprincipal, $meetingscoid, ADOBE_HOST, true)) {
                //DEBUG
                // echo 'true';
            } else {
                //DEBUG
                print_object('error assign user adobe host role');
                print_object($aconnect->_xmlrequest);
                print_object($aconnect->_xmlresponse);
                $validuser = false;
            }
        } else {
            // Check if meeting is public and allow them to join
            if ($adobeconnect->meetingpublic) {
                // if for a public meeting the user does not not have either of presenter or participant capabilities then give
                // the user the participant role for the meeting
                aconnect_check_user_perm($aconnect, $usrprincipal, $meetingscoid, ADOBE_PARTICIPANT, true);
                $validuser = true;
            } else {
                $validuser = false;
            }
        }
    } else {
        $validuser = false;
        notice('Unable to retrieve meeting details');
    }

    aconnect_logout($aconnect);

    // User is either valid or invalid, if valid redirect user to the meeting url
    if (empty($validuser)) {
        notice('You are not a participant for this meeting');
    } else {

        $login = $USER->username;
        $password  = $USER->username;

        $aconnect = new connect_class($CFG->adobeconnect_host, $CFG->adobeconnect_port);
        $aconnect->request_http_header_login(1, $login);

        redirect('http://' . $CFG->adobeconnect_meethost . ':'
                 . $CFG->adobeconnect_port . $meeting->url
                 . '?session=' . $aconnect->get_cookie());
    }
} else {
    notice('Only members of this meeting are allowed to join this meeting');
}
?>