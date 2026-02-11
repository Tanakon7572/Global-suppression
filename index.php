<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $email = $_POST['email'] ?? '';
    $companyId = $_POST['company_id'] ?? '';
    $caseType = $_POST['case'] ?? '';

    $endpoints = [
        'unsub' => 'remove_unsub_data',
        'bounce' => 'remove_global_data',
        'spam' => 'remove_spam_data'
    ];

    if (!isset($endpoints[$caseType])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid case']);
        exit;
    }

    $url = "https://api.taximail.com/v2/repaire_data.php?cmd_data={$endpoints[$caseType]}&company_id={$companyId}&email={$email}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    header('Content-Type: application/json');
    echo json_encode([
        'status' => $httpCode === 200 ? 'success' : 'fail',
        'http_code' => $httpCode,
        'api_response' => $response
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Taximail Tool - Ultra Stable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-container { height: 300px; overflow-y: auto; background: #000; color: #0f0; padding: 15px; font-family: monospace; border-radius: 8px; white-space: pre-wrap; font-size: 13px; }
        .section-box { background: white; padding: 20px; border-radius: 10px; border: 1px solid #ddd; margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">ปลด Global suppression</h3>
        <div class="d-flex align-items-center bg-white p-2 rounded border">
            <span class="me-2 fw-bold">Company ID:</span>
            <input type="number" id="defaultId" class="form-control form-control-sm" style="width: 100px;" value="">
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="section-box">
                <h5 class="fw-bold border-bottom pb-2">1. กรอกอีเมล</h5>
                <div id="manualArea">
                    <div class="input-group mb-2">
                        <input type="email" class="form-control row-email" placeholder="">
                        <select class="form-select row-case" style="max-width: 110px;">
                            <option value="unsub">Unsub</option>
                            <option value="bounce">Bounce</option>
                            <option value="spam">Spam</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-sm btn-link" onclick="addRow()">+ เพิ่มแถว</button>
            </div>
        </div>

        <div class="col-md-6">
            <div class="section-box">
                <h5 class="fw-bold border-bottom pb-2">2. แนบไฟล์ (CSV เท่านั้น)</h5>
                <input type="file" id="csvFile" class="form-control mb-2" accept=".csv">
                <div class="alert alert-warning py-1 small mb-0">
                    หัวตารางต้องเป็น: <b>email,case</b>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center my-3">
        <button id="btnExecute" onclick="startProcess()" class="btn btn-success btn-lg px-5 shadow fw-bold">เริ่มส่งข้อมูล (Execute)</button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between">
            <span>Status Logs</span>
            <button class="btn btn-sm btn-outline-light py-0" onclick="document.getElementById('logBox').innerHTML = ''">Clear</button>
        </div>
        <div id="logBox" class="log-container">> พร้อมดำเนินการ...</div>
    </div>
</div>
<script>
const CASE_MAPPER = {
    'unsub': 'unsub',
    'unsubscribe': 'unsub',
    'suppressed by recipient': 'unsub',

    'bounce': 'bounce',
    'hardbounce': 'bounce',
    'suppressed by hard bounced': 'bounce',

    'spam': 'spam',
    'suppressed by complaint': 'spam',
};

function addRow() {
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="email" class="form-control row-email">
        <select class="form-select row-case" style="max-width:110px;">
            <option value="unsub">Unsub</option>
            <option value="bounce">Bounce</option>
            <option value="spam">Spam</option>
        </select>
        <button class="btn btn-outline-danger" onclick="this.parentElement.remove()">✖</button>
    `;
    document.getElementById('manualArea').appendChild(div);
}

async function startProcess() {

    const log = document.getElementById('logBox');
    const compId = document.getElementById('defaultId').value;
    const fileInput = document.getElementById('csvFile');
    const btn = document.getElementById('btnExecute');

    if (!compId) return alert("กรุณาใส่ Company ID");

    let tasks = [];

    // เก็บ manual input
    document.querySelectorAll('#manualArea .input-group').forEach(group => {
        const email = group.querySelector('.row-email').value.trim();
        const caseType = group.querySelector('.row-case').value;
        if (email) tasks.push({ email, caseType });
    });

    // อ่าน CSV
    if (fileInput.files.length > 0) {
        log.innerHTML += "> กำลังโหลดไฟล์...\n";

        const text = await fileInput.files[0].text();
        const rows = text.split('\n');
        const headers = rows[0].split(',').map(h => h.trim().toLowerCase());

        const emailIdx = headers.indexOf('email');
        const caseIdx  = headers.indexOf('case');

        if (emailIdx === -1 || caseIdx === -1) {
            log.innerHTML += "<span style='color:red'>! CSV ต้องมีหัว email และ case</span>\n";
        } else {
            for (let i = 1; i < rows.length; i++) {
                const cols = rows[i].split(',');
                if (cols.length >= 2) {
                    const email = cols[emailIdx]?.trim();
                    let caseRaw = cols[caseIdx]?.trim().toLowerCase();
                    const caseType = CASE_MAPPER[caseRaw];

                    if (email && caseType) {
                        tasks.push({ email, caseType });
                    }
                }
            }
        }
    }

    if (tasks.length === 0) return alert("ไม่พบข้อมูลอีเมล");

    btn.disabled = true;
    log.innerHTML += `> เริ่มประมวลผล ${tasks.length} รายการ...\n`;

    let success = 0;
    let fail = 0;

    for (const task of tasks) {

        log.innerHTML += `> ส่ง ${task.email} [${task.caseType}]... `;

        try {

            const formData = new FormData();
            formData.append('action', 'process');
            formData.append('email', task.email);
            formData.append('company_id', compId);
            formData.append('case', task.caseType);

            const res = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (data.status === 'success') {
                log.innerHTML += `<span style="color:#0f0">[OK ${data.http_code}]</span>\n`;
                success++;
            } else {
                log.innerHTML += `<span style="color:#f00">[FAIL ${data.http_code ?? ''}]</span>\n`;
                fail++;
            }

        } catch (err) {
            log.innerHTML += `<span style="color:#f00">[ERROR]</span>\n`;
            fail++;
        }

        log.scrollTop = log.scrollHeight;
    }

    btn.disabled = false;

    log.innerHTML += `\n--- เสร็จสิ้น ---\n`;
    log.innerHTML += `สำเร็จ: ${success}\n`;
    log.innerHTML += `ล้มเหลว: ${fail}\n`;
}
</script>
</body>
</html>
