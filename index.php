<?php
// proxy.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUrl = $_POST['url'] ?? '';

    if (!$targetUrl) {
        echo json_encode(['status' => 'error', 'message' => 'No URL provided']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // กรณี SSL มีปัญหา

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo json_encode([
        'http_code' => $httpCode,
        'response' => json_decode($response) ?: $response
    ]);
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
    // กลุ่ม Unsub
    'unsub': 'unsub',
    'unsubscribe': 'unsub',
    'Suppressed by recipient': 'unsub',

    // กลุ่ม Bounce
    'bounce': 'bounce',
    'hardbounce': 'bounce',
    'Suppressed by hard bounced': 'bounce',


    // กลุ่ม Spam
    'spam': 'spam',
    'Suppressed by complaint': 'spam',
};

const ENDPOINTS = {
    'unsub': 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_unsub_data',
    'bounce': 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_global_data',
    'spam': 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_spam_data'
};

function addRow() {
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `<input type="email" class="form-control row-email"><select class="form-select row-case" style="max-width: 110px;"><option value="unsub">Unsub</option><option value="bounce">Bounce</option><option value="spam">Spam</option></select><button class="btn btn-outline-danger" onclick="this.parentElement.remove()">✖</button>`;
    document.getElementById('manualArea').appendChild(div);
}

// ฟังก์ชันหลักที่แก้ไขใหม่
async function startProcess() {
    const log = document.getElementById('logBox');
    const compId = document.getElementById('defaultId').value;
    const fileInput = document.getElementById('csvFile');
    const btn = document.getElementById('btnExecute');
    
    if (!compId) return alert("กรุณาใส่ Company ID");

    let tasks = [];

    // 1. เก็บข้อมูลจากหน้าจอ
    document.querySelectorAll('#manualArea .input-group').forEach(group => {
        const email = group.querySelector('.row-email').value.trim();
        const caseType = group.querySelector('.row-case').value;
        if (email) tasks.push({ email, caseType });
    });

    // 2. อ่านไฟล์ (ถ้ามีการเลือกไฟล์)
    if (fileInput.files.length > 0) {
        log.innerHTML += "> กำลังโหลดไฟล์...\n";
        const file = fileInput.files[0];
        const text = await file.text(); // ใช้ Native Text Reader
        const rows = text.split('\n');
        const headers = rows[0].split(',').map(h => h.trim().toLowerCase());
        
        const emailIdx = headers.indexOf('email');
        const caseIdx = headers.indexOf('case');

        if (emailIdx === -1 || caseIdx === -1) {
            log.innerHTML += "<span style='color:red'>! Error: ไฟล์ CSV ต้องมีหัว email และ case</span>\n";
        } else {
            for (let i = 1; i < rows.length; i++) {
                const cols = rows[i].split(',');
                if (cols.length >= 2) {
                    const email = cols[emailIdx]?.trim();
                    const caseType = cols[caseIdx]?.trim().toLowerCase();
                    if (email && ENDPOINTS[caseType]) {
                        tasks.push({ email, caseType });
                    }
                }
            }
        }
    }

    if (tasks.length === 0) return alert("ไม่พบข้อมูลอีเมล");

    // 3. เริ่มยิง API
    btn.disabled = true;
    log.innerHTML += `> เริ่มประมวลผลทั้งหมด ${tasks.length} รายการ...\n`;

    // เปลี่ยนช่วงการวนลูปยิง API เป็นโค้ดนี้
for (const task of tasks) {
    const apiUrl = `${ENDPOINTS[task.caseType]}&company_id=${compId}&email=${task.email}`;
    log.innerHTML += `> ส่ง ${task.email} [${task.caseType}]... `;
    
    try {
        // สร้าง FormData เพื่อส่ง URL ไปให้ Proxy
        const formData = new FormData();
        formData.append('url', apiUrl);

        const response = await fetch('proxy.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.http_code === 200) {
            log.innerHTML += `<span style="color: #0f0;">[สำเร็จ]</span>\n`;
        } else {
            log.innerHTML += `<span style="color: #ffc107;">[Error: ${result.http_code}]</span>\n`;
        }
    } catch (e) {
        log.innerHTML += `<span style="color: #f00;">[ล้มเหลว: ${e.message}]</span>\n`;
    }
    log.scrollTop = log.scrollHeight;
}
</script>
</body>
</html>
