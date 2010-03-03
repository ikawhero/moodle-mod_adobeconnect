<?php // $Id: access.php,v 1.1 2010/03/03 16:39:52 arborrow Exp $
$mod_adobeconnect_capabilities = array(
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