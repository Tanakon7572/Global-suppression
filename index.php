<?php
// บล็อกถ้าไม่ได้ผ่าน Cloudflare
if (!isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    http_response_code(403);
    exit('Forbidden');
}

/* =========================
   HANDLE AJAX REQUEST
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $email = trim($_POST['email'] ?? '');
    $companyId = trim($_POST['company_id'] ?? '');
    $caseType = trim($_POST['case'] ?? '');

    if (!$email || !$companyId || !$caseType) {
        http_response_code(400);
        echo "Missing parameters";
        exit;
    }

    $map = [
        'unsub'  => 'remove_unsub_data',
        'bounce' => 'remove_global_data',
        'spam'   => 'remove_spam_data'
    ];

    if (!isset($map[$caseType])) {
        http_response_code(400);
        echo "Invalid case type";
        exit;
    }

    $url = "https://api.taximail.com/v2/repaire_data.php"
        . "?cmd_data={$map[$caseType]}"
        . "&company_id=" . urlencode($companyId)
        . "&email=" . urlencode($email);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    http_response_code($httpCode);
    header("Content-Type: text/plain; charset=UTF-8");

    echo $response ?: "No response";
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Global Suppression Tool</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.log-box {
    height: 300px;
    overflow-y: auto;
    background: #000;
    color: #0f0;
    padding: 15px;
    font-family: monospace;
    border-radius: 8px;
    white-space: pre-wrap;
    font-size: 13px;
}
</style>
</head>
<body class="bg-light">

<div class="container py-4">
    <h3 class="mb-4">ปลด Global Suppression</h3>

    <div class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="number" id="companyId" class="form-control" placeholder="Company ID">
        </div>
        <div class="col-md-4">
            <input type="email" id="email" class="form-control" placeholder="Email">
        </div>
        <div class="col-md-3">
            <select id="caseType" class="form-select">
                <option value="unsub">Unsub</option>
                <option value="bounce">Bounce</option>
                <option value="spam">Spam</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-success w-100" onclick="send()">Execute</button>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-dark text-white">Response</div>
        <div id="log" class="log-box">> Ready...</div>
    </div>
</div>

<script>
async function send() {
    const companyId = document.getElementById('companyId').value.trim();
    const email = document.getElementById('email').value.trim();
    const caseType = document.getElementById('caseType').value;
    const log = document.getElementById('log');

    if (!companyId || !email) {
        alert("กรอกข้อมูลให้ครบ");
        return;
    }

    log.innerHTML += `\n> Sending ${email} (${caseType})...\n`;

    const formData = new FormData();
    formData.append("action", "process");
    formData.append("company_id", companyId);
    formData.append("email", email);
    formData.append("case", caseType);

    try {
        const res = await fetch(window.location.href, {
            method: "POST",
            body: formData
        });

        const text = await res.text();

        log.innerHTML += `[HTTP ${res.status}]\n`;
        log.innerHTML += text + "\n";

    } catch (err) {
        log.innerHTML += "[ERROR] " + err.message + "\n";
    }

    log.scrollTop = log.scrollHeight;
}
</script>

</body>
</html>
