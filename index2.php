<?php
// บล็อกถ้าไม่ได้ผ่าน Cloudflare
if (!isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    http_response_code(403);
    exit('Forbidden');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Taximail Tool - Direct Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-container { height: 350px; overflow-y: auto; background: #000; color: #0f0; padding: 15px; font-family: monospace; border-radius: 8px; white-space: pre-wrap; font-size: 13px; border: 1px solid #333; }
        .section-box { background: white; padding: 20px; border-radius: 10px; border: 1px solid #ddd; margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">ปลด Global suppression (Direct Mode)</h3>
        <div class="d-flex align-items-center bg-white p-2 rounded border">
            <span class="me-2 fw-bold">Company ID:</span>
            <input type="number" id="defaultId" class="form-control form-control-sm" style="width: 100px;" value="16501">
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="section-box">
                <h5 class="fw-bold border-bottom pb-2">1. กรอกอีเมล</h5>
                <div id="manualArea">
                    <div class="input-group mb-2">
                        <input type="email" class="form-control row-email" placeholder="example@mail.com">
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
                <div class="alert alert-warning py-1 small mb-0 text-start">
                    หัวตารางต้องเป็น: <b>email,case</b>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center my-3">
        <button id="btnExecute" onclick="startProcess()" class="btn btn-success btn-lg px-5 shadow fw-bold">เริ่มส่งข้อมูล (Direct API Call)</button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span>Status Logs (ตรวจสอบ Response ได้ที่ Network Tab F12)</span>
            <button class="btn btn-sm btn-outline-light py-0" onclick="document.getElementById('logBox').innerHTML = ''">Clear</button>
        </div>
        <div id="logBox" class="log-container">> พร้อมดำเนินการ...</div>
    </div>
</div>

<script>
const CASE_MAPPER = {
    'unsub': 'unsub', 'unsubscribe': 'unsub', 'suppressed by recipient': 'unsub',
    'bounce': 'bounce', 'hardbounce': 'bounce', 'suppressed by hard bounced': 'bounce',
    'spam': 'spam', 'suppressed by complaint': 'spam'
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

async function startProcess() {
    const log = document.getElementById('logBox');
    const compId = document.getElementById('defaultId').value;
    const fileInput = document.getElementById('csvFile');
    const btn = document.getElementById('btnExecute');
    
    if (!compId) return alert("กรุณาใส่ Company ID");

    let tasks = [];

    // ดึงข้อมูลจาก Manual
    document.querySelectorAll('#manualArea .input-group').forEach(group => {
        const email = group.querySelector('.row-email').value.trim();
        const caseType = group.querySelector('.row-case').value;
        if (email) tasks.push({ email, caseType });
    });

    // ดึงข้อมูลจากไฟล์
    if (fileInput.files.length > 0) {
        log.innerHTML += "> กำลังโหลดไฟล์...\n";
        const file = fileInput.files[0];
        const text = await file.text();
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
    log.innerHTML = `> เริ่มส่งคำขอโดยตรงไปที่ Taximail API...\n`;

    for (const task of tasks) {
        // ประกอบ URL ตรงตามที่คุณต้องการ (Direct Call)
        const directUrl = `${ENDPOINTS[task.caseType]}&email=${encodeURIComponent(task.email)}&company_id=${compId}`;
        
        log.innerHTML += `> Request URL: ${directUrl}\n`;
        
        try {
            // ยิงตรงไปที่ Taximail (จะปรากฏใน Network Tab ทันที)
            // ใช้ mode: 'no-cors' เพื่อให้เบราว์เซอร์ยอมส่งคำขอไปต่างโดเมน
            await fetch(directUrl, { 
                method: 'GET',
                mode: 'no-cors',
                headers: {
                    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8'
                }
            });
            
            log.innerHTML += `<span style="color: #0f0;">> ส่งคำขอสำเร็จ: ${task.email}</span>\n`;
            log.innerHTML += `--------------------------------------------------\n`;
        } catch (e) {
            log.innerHTML += `<span style="color: #f00;">> เกิดข้อผิดพลาด: ${e.message}</span>\n`;
        }
        log.scrollTop = log.scrollHeight;
    }

    btn.disabled = false;
    log.innerHTML += `--- เสร็จสิ้นภารกิจเมื่อ ${new Date().toLocaleTimeString()} ---\n`;
}
</script>
</body>
</html>
