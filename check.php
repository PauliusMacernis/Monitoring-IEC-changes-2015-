<?php

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(__DIR__);

// Email (recipients): $email_recipients_string
$email_recipients_array = array(
    'email1@example.com',
    'email2@example.com',
    'emailn@example.com'
); // REAL!!

$sms_recipients = array(
    '+37000000000', 
    '+37000000001'
);

$messente_settings = array(
    'debug' => true,
    'sms_number_from' => '+37000000000',
    'username' => 'yourusernameatmessente',
    'password' => 'yourpasswordatmessente'
);


// global system settings
date_default_timezone_set('Europe/Vilnius');
setlocale(LC_TIME, 'lt-LT');
$time_begin = microtime(true);
$datetime_begin = date('Y-m-d H:i:s');


require_once 'src/functions.php';
require_once 'src/Web.php';
require_once 'src/IecQuota2015.php';
require_once 'src/KompassRegister2015.php';
require_once 'src/messente.php'; // for sending sms


// Kompass Register 2015 - changes detection
$KompassRegister = new KompassRegister2015();
$kompass_register_remote_content = $KompassRegister->get('content_remote');
$kompass_register_remote_retry   = false;
if(empty($kompass_register_remote_content)) {    // if problems getting content
    unset($KompassRegister);                            // then unset all the content from remote
    sleep(7);                                           // then wait for 7 seconds
    $KompassRegister = new KompassRegister2015();       // then get the content again
    $kompass_register_remote_content = $KompassRegister->get('content_remote');
    $kompass_register_remote_retry   = 1;
}

if (!$KompassRegister->isLocalAnRemoteContentTheSame()) {
    // Kompass Register 2015 page has been changed, do something...
    $KompassRegister->set('changes_detected', true);

    // Save new as latest, move old to archive
    $KompassRegister->archiveLocalLatest();
    $KompassRegister->moveRemoteToLatest();
}
//END. Kompass Register 2015 - changes detection


// IEC quota 2015 - changes detection
$IecQuota = new IecQuota2015();
$countries_change = array();
if (!$IecQuota->isLocalAnRemoteContentTheSame()) {
    // Kompass Register 2015 page has been changed, do something...
    $IecQuota->set('changes_detected', true);

    // Get data of countries were quota changed.
    $countries_change = $IecQuota->getCountriesWithChangedQuota();

}

// The Working Holiday category, numbers
$lithuania_data_wh = $IecQuota->getCountryData('Lithuania', 'lt', 'wh');
// The Working Holiday category
$iecQuotaLtWhStringRemote = $IecQuota->getCountryData('Lithuania', 'lt', 'wh', true, 'content_remote');
$iecQuotaLtWhStringLocal = $IecQuota->getCountryData('Lithuania', 'lt', 'wh', true, 'content_local');
// The Young Professionals category
$iecQuotaLtYpStringRemote = $IecQuota->getCountryData('Lithuania', 'lt', 'yp', true, 'content_remote');
$iecQuotaLtYpStringLocal = $IecQuota->getCountryData('Lithuania', 'lt', 'yp', true, 'content_local');
// The International Co-op (Internship) category
$iecQuotaLtCoopStringRemote = $IecQuota->getCountryData('Lithuania', 'lt', 'coop', true, 'content_remote');
$iecQuotaLtCoopStringLocal = $IecQuota->getCountryData('Lithuania', 'lt', 'coop', true, 'content_local');


// Get info messages for The Working Holiday category for Lithuania
$lithuania_data_wh_messages[] = 'Lithuania (WH):';
$lithuania_data_wh_messages[] = 'Quota: ' . $lithuania_data_wh['quota'];
$lithuania_data_wh_messages[] = 'Spots available: ' . $lithuania_data_wh['places'];
$lithuania_data_wh_messages[] = 'Status: ' . $lithuania_data_wh['status'];


// Activate SMS sending if Lithuanian info has been changed
$sms_title_messages = array(); // SMS message (create)
$send_sms = false;
if ($iecQuotaLtWhStringRemote != $iecQuotaLtWhStringLocal) {
    $sms_title_messages[] = "IEC Working Holiday info changed for LT.";
    $send_sms = true;
}
if ($iecQuotaLtYpStringRemote != $iecQuotaLtYpStringLocal) {
    $sms_title_messages[] = "IEC Young Professionals info changed for LT.";
    $send_sms = true;
}
if ($iecQuotaLtCoopStringRemote != $iecQuotaLtCoopStringLocal) {
    $sms_title_messages[] = "IEC Internship info changed for LT.";
    $send_sms = true;
}
if ($KompassRegister->get('changes_detected')) {
    $sms_title_messages[] =
        "IEC Kompass reg. page changed for LT"
        . (empty($kompass_register_remote_content) ? " (to empty)" : "")
        . ".";
    $send_sms = true;
}

// Switch files: remote - to latest, local - to archive
if ($IecQuota->get('changes_detected')) {
    // Save new as latest, move old to archive
    $IecQuota->archiveLocalLatest();
    $IecQuota->moveRemoteToLatest();
}

// Kompass registration page retry messages
$kompass_register_remote_retry_messages = array();
if($kompass_register_remote_retry) {
    $kompass_register_remote_retry_messages[] =
        "\r\n*** The content of Kompass registration has been received by "
        . ((int)$kompass_register_remote_retry + 1) // retries + 1 of first time try
        . " try. ***";
}

// SMS message (send)
$sms_messages_separator = "\n";
$sms_error_messages = array();
$sms_message = '';
if ($send_sms) {
    $sms_message = implode(
        $sms_messages_separator,
        array_merge($sms_title_messages, array(''), $lithuania_data_wh_messages, array(''), array($datetime_begin))
    );
    foreach ($sms_recipients as $recipient) {
        //var_dump(
        //    'SMS has been sent to: ' . $recipient .
        //    "<br />" . $sms_message
        //);
        $result = sendSms(
                $recipient, 
                $sms_message, 
                $messente_settings['debug'], 
                $messente_settings['sms_number_from'], 
                $messente_settings['username'], 
                $messente_settings['password']
        );
        if ($result['error_message']) {
            $sms_error_messages[] = $result['error_message'];
        }
    }
}


// Activate Email sending if any info has been changed
$send_email = false;
if ($KompassRegister->get('changes_detected') || $IecQuota->get('changes_detected')) {
    $send_email = true;
}

// Email message (create)
$email_message = '';
$email_messages = array();

if ($send_email) {
    
    $email_recipients_string = implode(', ', $email_recipients_array);

    // Email (subject): $email_subject_string
    $email_subject_string = '';
    if ($IecQuota->get('changes_detected') && $KompassRegister->get('changes_detected')) {
        $email_subject_string = 'IEC and Kompass changes detected';
    } elseif ($IecQuota->get('changes_detected')) {
        $email_subject_string = 'IEC changes detected';
    } elseif ($KompassRegister->get('changes_detected')) {
        $email_subject_string = 'IEC Kompass changes detected';
    } else {
        $email_subject_string = 'IEC changes';
    }

    // Email (message): Make string on countries changed: $quotas_changed_to_array
    $quotas_changed_to_array = array();
    if (!empty($countries_change)) {
        $quotas_changed_to_array[] = 'Quotas changed to:';
    }
    foreach ($countries_change as $country_case) {
        $quotas_changed_to_array[] =
            $country_case->location . ' (' . $country_case->category . '): ' . $country_case->info['quota'];
    }

    // Add important links at the end.
    $important_links = array();
    $important_links[] = 'IEC Kompass register:';
    $important_links[] = 'https://kompass-2015-iec-eic.international.gc.ca/registration-inscription?regionCode=LT';

    $important_links[] = '';
    $important_links[] = 'IEC Kompass login:';
    $important_links[] = 'https://kompass-2015-iec-eic.international.gc.ca/sign_in-connexion?regionCode=LT';

    $important_links[] = '';
    $important_links[] = 'IEC Quota info:';
    $important_links[] = 'http://www.cic.gc.ca/english/work/iec/index.asp?country=lt&cat=wh';

    $important_links[] = '';
    $important_links[] = 'IEC Apply:';
    $important_links[] = 'http://www.cic.gc.ca/english/work/iec/apply.asp';

    // The message
    $email_message = implode("\r\n",
        array_merge(
            $sms_title_messages,
            array(''),
            $lithuania_data_wh_messages,
            array(''),
            $quotas_changed_to_array,
            array(''),
            $important_links,
            array(''),
            $kompass_register_remote_retry_messages,
            array(''),
            array($datetime_begin)
        )
    );
    $email_message = wordwrap($email_message, 70, "\r\n"); // In case any of our lines are larger than 70 characters.

    // Send mail
    //var_dump(
    //    'Mail has been sent to: ' . $email_recipients_string .
    //    "<br />Subject: " . $email_subject_string .
    //    "<br />" . $email_message
    //);
    mail($email_recipients_string, $email_subject_string, $email_message);
}

// Log it.
$filename = 'log/activity_log.txt';
$file_data = $datetime_begin . " | +" . (int)(microtime(true) - $time_begin) . 's.'
    . "\r\n------------------------------------------------------------"
    . "\r\n" . ((!$send_sms && !$send_email) ? 'No changes.' : ($email_message ?: $sms_message))
    . "\r\n\r\n\r\n";
//if(is_file($filename)) {
//    $file_data .= file_get_contents($filename);
//}
file_put_contents($filename, $file_data, FILE_APPEND);

echo str_replace("\r\n", "<br />", $file_data);