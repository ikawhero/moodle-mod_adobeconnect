<?php // $Id: access.php,v 1.4 2010/05/11 03:45:03 adelamarre Exp $
$capabilities = array(
    'mod/adobeconnect:meetingpresenter' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
        )
    ),

    'mod/adobeconnect:meetingparticipant' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
        )
    ),

    'mod/adobeconnect:meetinghost' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => array(
        )
    ),

);
?>