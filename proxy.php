<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = $_POST['url'] ?? '';

    if (!$url) {
        echo json_encode(['error' => 'No URL']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // <--- ดึง Header ออกมาด้วย
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // แยก Header และ Body
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    curl_close($ch);

    echo json_encode([
        'http_code' => $http_code,
        'headers' => $header,
        'body' => $body // ผลลัพธ์ string(19) "..." จะอยู่ในนี้
    ]);
    exit;
}
?>
