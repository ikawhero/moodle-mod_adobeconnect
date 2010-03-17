<?php // $Id: access.php,v 1.1.2.3 2010/03/17 17:19:28 adelamarre Exp $
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