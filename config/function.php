<?php
function formatSizeUnits($bytes)
{
    /*ref.[https://stackoverflow.com/questions/5501427/php-filesize-mb-kb-conversion]*/

    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}





/* Create Qrcode 6 Degit*/
function generateRandomString($length = 6)
{
    $characters = '0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

function isCodeUnique($code, $db)
{
    $query = "SELECT COUNT(*) as COUNT FROM QRCODE WHERE CODE = ?";
    $result = $db->Query($query, [$code]);
    return ($result['COUNT'] == 0);
}

function generateUniqueCode($db)
{
    // Get current max QRCODEID
    $queryMaxId = "SELECT COALESCE(MAX(QRCODEID), 0) as MAXID FROM QRCODE";
    $maxIdResult = $db->Query($queryMaxId, false, false, true);
    $maxIdQr = $maxIdResult['MAXID'] + 1;

    // Generate unique code
    do {
        $code = generateRandomString(6);
    } while (!isCodeUnique($code, $db));
    return [
        'CODE' => $code,
        'MAXID' => $maxIdQr
    ];
}
function deleteRoomCookie()
{
    // Set the cookie expiration date to a time in the past
    setcookie("roomId", "", time() - 3600, "/"); // Cookie will be deleted
}


?>