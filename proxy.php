<?php
// proxy.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUrl = $_POST['url'] ?? '';

    if (!$targetUrl || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or No URL provided']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo json_encode(['status' => 'error', 'message' => $error]);
    } else {
        echo json_encode([
            'http_code' => $httpCode,
            'response' => json_decode($response) ?: $response
        ]);
    }
    exit;
}
?>
