<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 2015-02-25
 * Time: 15:54
 */


function sendSms(
        $to_number, 
        $txt, 
        $messente_debug,
        $messente_sms_number_from,
        $messente_username,
        $messente_password
) {
    // Initialize Messente API
    // No E-mail is sent when debug mode is on. Disable debug mode for live release.
    $preferences = array(
        'username' => $messente_username,
        'password' => $messente_password,
        'debug' => $messente_debug,
        'error_email' => getenv("SERVER_ADMIN_EMAIL") // E-mail that gets mail when something goes wrong
    );
    $Messente = new Messente($preferences);
    // Array of messages to send
    $message = array(
        'from' => $messente_sms_number_from,
        'to' => $to_number,
        'content' => $txt,
    );

    return $Messente->send_sms($message);

}
