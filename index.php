<?php
/**
 * 1. ส่วนประมวลผล API (Server-side)
 */
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'execute_api') {
    header('Content-Type: application/json');
    $targetUrl = $_POST['url'] ?? '';

    if (!$targetUrl) {
        echo json_encode(['error' => 'URL is missing']);
        exit;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);          // ดึง Header มาแสดงผล
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $header_text = substr($response, 0, $header_size);
    $body_text = substr($response, $header_size);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);

    echo json_encode([
        'http_code' => $http_code,
        'headers'   => $header_text,
        'body'      => $body_text // แสดงผล string(19) "list_..." และ int(1)
    ]);
    exit;
}

/**
 * 2. ส่วนความปลอดภัย (Cloudflare)
 */
if (!isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    http_response_code(403);
    exit('Forbidden');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Taximail Repair Tool - Final URL Fix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-container { height: 450px; overflow-y: auto; background: #000; color: #0f0; padding: 15px; font-family: monospace; border-radius: 8px; white-space: pre-wrap; font-size: 12px; border: 1px solid #333; }
        .section-box { background: white; padding: 20px; border-radius: 10px; border: 1px solid #ddd; margin-bottom: 20px; }
        .header-text { color: #888; }
        .body-text { color: #0f0; font-weight: bold; }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
        <h3 class="mb-0 text-primary">ปลด Global Suppression</h3>
        <div class="d-flex align-items-center bg-white p-2 rounded border shadow-sm">
            <span class="me-2 fw-bold">Company ID:</span>
            <input type="number" id="defaultId" class="form-control form-control-sm" style="width: 100px;" value="16501">
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="section-box h-100">
                <h5 class="fw-bold mb-3">1. เพิ่มรายการ (Manual)</h5>
                <div id="manualArea">
                    <div class="input-group mb-2 shadow-sm">
                        <input type="email" class="form-control row-email" placeholder="email@example.com">
                        <select class="form-select row-case" style="max-width: 110px;">
                            <option value="unsub">Unsub</option>
                            <option value="bounce">Bounce</option>
                            <option value="spam">Spam</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-primary mt-1" onclick="addRow()">+ เพิ่มแถว</button>
            </div>
        </div>

        <div class="col-md-6">
            <div class="section-box h-100">
                <h5 class="fw-bold mb-3">2. แนบไฟล์ (CSV)</h5>
                <input type="file" id="csvFile" class="form-control mb-3" accept=".csv">
                <div class="alert alert-info py-2 small mb-0">หัวตาราง: <b>email, case</b></div>
            </div>
        </div>
    </div>

    <div class="text-center my-4">
        <button id="btnExecute" onclick="startProcess()" class="btn btn-success btn-lg px-5 shadow fw-bold rounded-pill">เริ่มดำเนินการ (Execute)</button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between">
            <span class="small fw-bold">Response Headers & Raw Body Logs</span>
            <button class="btn btn-sm btn-outline-light py-0" onclick="document.getElementById('logBox').innerHTML = ''">Clear</button>
        </div>
        <div id="logBox" class="log-container">> รอดำเนินการ...</div>
    </div>
</div>

<script>
// ชุด URL ตามที่คุณกำหนด
const ENDPOINTS = {
    'unsub': 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_unsub_data',
    'bounce': 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_global_data',
    'spam': 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_spam_data'
};

const CASE_MAPPER = {
    'unsub': 'unsub', 'unsubscribe': 'unsub', 'suppressed by recipient': 'unsub',
    'bounce': 'bounce', 'hardbounce': 'bounce', 'suppressed by hard bounced': 'bounce',
    'spam': 'spam', 'suppressed by complaint': 'spam'
};

function addRow() {
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `<input type="email" class="form-control row-email"><select class="form-select row-case" style="max-width: 110px;"><option value="unsub">Unsub</option><option value="bounce">Bounce</option><option value="spam">Spam</option></select><button class="btn btn-outline-danger" onclick="this.parentElement.remove()">✖</button>`;
    document.getElementById('manualArea').appendChild(div);
}

async function startProcess() {
    const log = document.getElementById('logBox');
    const compId = document.getElementById('defaultId').value;
    const fileInput = document.getElementById('csvFile');
    const btn = document.getElementById('btnExecute');
    
    if (!compId) return alert("กรุณาใส่ Company ID");

    let tasks = [];
    document.querySelectorAll('#manualArea .input-group').forEach(group => {
        const email = group.querySelector('.row-email').value.trim();
        const caseType = group.querySelector('.row-case').value;
        if (email) tasks.push({ email, caseType });
    });

    if (fileInput.files.length > 0) {
        const text = await fileInput.files[0].text();
        const rows = text.split('\n');
        const headers = rows[0].split(',').map(h => h.trim().toLowerCase());
        const eIdx = headers.indexOf('email'), cIdx = headers.indexOf('case');
        if (eIdx !== -1 && cIdx !== -1) {
            for (let i = 1; i < rows.length; i++) {
                const cols = rows[i].split(',');
                if (cols.length >= 2) {
                    const mapped = CASE_MAPPER[cols[cIdx]?.trim().toLowerCase()];
                    if (cols[eIdx] && mapped) tasks.push({ email: cols[eIdx].trim(), caseType: mapped });
                }
            }
        }
    }

    if (tasks.length === 0) return alert("ไม่พบข้อมูล");

    btn.disabled = true;
    log.innerHTML = `> เริ่มส่งคำขอด้วยชุด URL ที่กำหนด...\n`;

    for (const task of tasks) {
        // การประกอบ URL ให้ตรงตามรูปภาพ Query String
        const targetUrl = `${ENDPOINTS[task.caseType]}&email=${encodeURIComponent(task.email)}&company_id=${compId}`;
        log.innerHTML += `> REQUEST: ${task.email}\n`;
        
        try {
            const formData = new FormData();
            formData.append('url', targetUrl);

            // ส่งหาตัวเองที่ index.php พร้อมตัวแปร ajax_action เพื่อให้ PHP ยิง API
            const response = await fetch('index.php?ajax_action=execute_api', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            log.innerHTML += `<span class="header-text">[HEADERS]\n${result.headers}</span>`;
            log.innerHTML += `<span class="body-text">[BODY]\n${result.body}</span>\n`;
            log.innerHTML += `--------------------------------------------------\n`;
        } catch (e) {
            log.innerHTML += `<span style="color: #f00;">[ERROR]: ${e.message}</span>\n`;
        }
        log.scrollTop = log.scrollHeight;
    }

    btn.disabled = false;
    log.innerHTML += `--- เสร็จสิ้น ---`;
}
</script>
</body>
</html>
