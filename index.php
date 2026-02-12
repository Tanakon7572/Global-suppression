<?php
// บล็อกถ้าไม่ได้ผ่าน Cloudflare (เปิดใช้งานเมื่อขึ้น Production)
$userIp = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taximail Repair Tool - Full Proxy Mode</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .log-container { height: 400px; overflow-y: auto; background: #1a1a1a; color: #00ff00; padding: 20px; font-family: 'Consolas', monospace; border-radius: 8px; font-size: 13px; line-height: 1.6; border: 1px solid #333; }
        .section-box { background: white; padding: 25px; border-radius: 12px; border: 1px solid #e0e0e0; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .status-success { color: #00ff00; fw-bold; }
        .status-error { color: #ff4444; }
        .status-warn { color: #ffbb33; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">ปลด Global Suppression</h2>
            <p class="text-muted mb-0">จัดการ Unsub, Bounce, และ Spam ผ่าน Proxy (Fix IP)</p>
        </div>
        <div class="text-end">
            <div class="input-group input-group-sm mb-1 shadow-sm">
                <span class="input-group-text bg-primary text-white border-primary">Company ID</span>
                <input type="number" id="defaultId" class="form-control" style="width: 100px;" value="157">
            </div>
            <small class="badge bg-secondary">Client IP: <?php echo $userIp; ?></small>
        </div>
    </div>

    

    <div class="row">
        <div class="col-lg-6">
            <div class="section-box h-100">
                <h5 class="fw-bold mb-3 border-bottom pb-2">1. กรอกข้อมูลรายการ</h5>
                <div id="manualArea">
                    <div class="input-group mb-2 shadow-sm">
                        <input type="email" class="form-control row-email" placeholder="ระบุอีเมล">
                        <select class="form-select row-case" style="max-width: 120px;">
                            <option value="unsub">Unsub</option>
                            <option value="bounce">Bounce</option>
                            <option value="spam">Spam</option>
                        </select>
                        <button class="btn btn-outline-danger" onclick="this.parentElement.remove()">✖</button>
                    </div>
                </div>
                <button class="btn btn-sm btn-link text-decoration-none mt-2" onclick="addRow()">+ เพิ่มรายการใหม่</button>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="section-box h-100 text-center d-flex flex-column justify-content-center">
                <h5 class="fw-bold mb-3 border-bottom pb-2 text-start">2. อัปโหลดไฟล์ CSV</h5>
                <div class="py-3">
                    <input type="file" id="csvFile" class="form-control mb-3" accept=".csv">
                    <div class="alert alert-light border small text-start mb-0">
                        <strong>คำแนะนำ:</strong> คอลัมน์ต้องชื่อ <code>email</code> และ <code>case</code><br>
                        <span class="text-muted">* รองรับคำอ่าน: Suppressed by recipient, complain, hard bounced</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center my-4">
        <button id="btnExecute" onclick="startProcess()" class="btn btn-primary btn-lg px-5 shadow fw-bold rounded-pill">🚀 เริ่มดำเนินการส่งข้อมูล</button>
    </div>

    <div class="card border-0 shadow-lg overflow-hidden">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <span class="small fw-bold">REAL-TIME LOGS (SERVER PROXY)</span>
            <button class="btn btn-sm btn-outline-secondary py-0" onclick="document.getElementById('logBox').innerHTML = ''">ล้างหน้าจอ</button>
        </div>
        <div id="logBox" class="log-container">> ระบบพร้อมทำงาน...</div>
    </div>
</div>

<script>
// ตั้งค่าคำอ่าน (Keyword Mapping)
const CASE_MAPPER = {
    'unsub': 'unsub',
    'unsubscribe': 'unsub',
    'suppressed by recipient': 'unsub',
    'ยกเลิก': 'unsub',

    'bounce': 'bounce',
    'hardbounce': 'bounce',
    'suppressed by hard bounced': 'bounce',
    'เสีย': 'bounce',

    'spam': 'spam',
    'suppressed by complaint': 'spam',
    'complain': 'spam',
    'สแปม': 'spam'
};

const ENDPOINTS = {
    'unsub': 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_unsub_data',
    'bounce': 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_global_data',
    'spam': 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_spam_data'
};

function addRow() {
    const div = document.createElement('div');
    div.className = 'input-group mb-2 shadow-sm';
    div.innerHTML = `<input type="email" class="form-control row-email"><select class="form-select row-case" style="max-width: 120px;"><option value="unsub">Unsub</option><option value="bounce">Bounce</option><option value="spam">Spam</option></select><button class="btn btn-outline-danger" onclick="this.parentElement.remove()">✖</button>`;
    document.getElementById('manualArea').appendChild(div);
}

async function startProcess() {
    const log = document.getElementById('logBox');
    const compId = document.getElementById('defaultId').value;
    const fileInput = document.getElementById('csvFile');
    const btn = document.getElementById('btnExecute');
    
    if (!compId) return alert("กรุณาระบุ Company ID");

    let tasks = [];

    // 1. ดึงข้อมูลจาก Manual
    document.querySelectorAll('#manualArea .input-group').forEach(group => {
        const email = group.querySelector('.row-email').value.trim();
        const caseType = group.querySelector('.row-case').value;
        if (email) tasks.push({ email, caseType });
    });

    // 2. ดึงข้อมูลจากไฟล์ CSV
    if (fileInput.files.length > 0) {
        log.innerHTML += `[${new Date().toLocaleTimeString()}] กำลังประมวลผลไฟล์...\n`;
        const text = await fileInput.files[0].text();
        const rows = text.split('\n');
        
        if (rows.length > 0) {
            const headers = rows[0].split(',').map(h => h.trim().toLowerCase());
            const emailIdx = headers.indexOf('email');
            const caseIdx = headers.indexOf('case');

            if (emailIdx === -1 || caseIdx === -1) {
                log.innerHTML += `<span class="status-error">! ไม่พบหัวตาราง email หรือ case ในไฟล์</span>\n`;
            } else {
                for (let i = 1; i < rows.length; i++) {
                    const cols = rows[i].split(',');
                    if (cols.length >= 2) {
                        const email = cols[emailIdx]?.trim();
                        const rawCase = cols[caseIdx]?.trim().toLowerCase();
                        const mappedCase = CASE_MAPPER[rawCase];
                        
                        if (email && mappedCase) {
                            tasks.push({ email, caseType: mappedCase });
                        } else if (email) {
                            log.innerHTML += `<span class="status-warn">! ข้าม ${email}: ไม่รู้จักเคส "${rawCase}"</span>\n`;
                        }
                    }
                }
            }
        }
    }

    if (tasks.length === 0) return alert("ไม่พบข้อมูลที่จะส่ง");

    btn.disabled = true;
    log.innerHTML += `[${new Date().toLocaleTimeString()}] เริ่มส่ง API ผ่าน Proxy (${tasks.length} รายการ)\n`;

    // 3. ยิง API ทีละรายการผ่าน Proxy
    // ... (โค้ดส่วนบนคงเดิม) ...

    for (const task of tasks) {
        const finalApiUrl = `${ENDPOINTS[task.caseType]}&company_id=${compId}&email=${task.email}`;
        log.innerHTML += `> Processing: ${task.email} [${task.caseType}]... `;
        
        try {
            const formData = new FormData();
            formData.append('url', finalApiUrl);

            // แก้ไข: ใช้เส้นทางไฟล์ที่แน่นอน และดักจับ Error ที่ละเอียดขึ้น
            const response = await fetch('./proxy.php', { // ใส่ ./ เพื่อยืนยันว่าอยู่ที่ folder เดียวกัน
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.http_code === 200) {
                log.innerHTML += `<span class="status-success">[SUCCESS 200]</span>\n`;
            } else {
                // แสดงข้อความ Error จาก PHP ถ้ามี
                const errMsg = result.message || 'API Error';
                log.innerHTML += `<span class="status-error">[FAILED ${result.http_code}: ${errMsg}]</span>\n`;
            }
        } catch (e) {
            log.innerHTML += `<span class="status-error">[ERROR: ${e.message}]</span>\n`;
            console.error("Fetch Error:", e); // ดูรายละเอียดใน F12 Console
        }
        log.scrollTop = log.scrollHeight;
    }

// ... (โค้ดส่วนล่างคงเดิม) ...

    btn.disabled = false;
    log.innerHTML += `[${new Date().toLocaleTimeString()}] --- จบการทำงาน ---\n`;
}
</script>

</body>
</html>
