<?php
// ส่วนที่ 1: การจัดการ Security และ API Logic (หลังบ้าน)
$logOutput = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_api'])) {
    $compId = $_POST['company_id'];
    $tasks = json_decode($_POST['tasks_data'], true);
    
    $endpoints = [
        'unsub' => 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_unsub_data',
        'bounce' => 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_global_data',
        'spam' => 'https://api.taximail.com/v2/repaire_data.php?cmd_data=remove_spam_data'
    ];

    foreach ($tasks as $task) {
        $url = $endpoints[$task['caseType']] . "&email=" . urlencode($task['email']) . "&company_id=" . $compId;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // ดึง Header มาด้วย
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        curl_close($ch);

        $logOutput .= "> Processing: " . $task['email'] . "\n";
        $logOutput .= "> URL: " . $url . "\n";
        $logOutput .= "[HEADERS]\n" . $header . "\n";
        $logOutput .= "[BODY]\n" . $body . "\n";
        $logOutput .= "--------------------------------------------------\n";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Taximail Tool - Single File</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-container { height: 400px; overflow-y: auto; background: #000; color: #0f0; padding: 15px; font-family: monospace; border-radius: 8px; white-space: pre-wrap; font-size: 12px; }
        .section-box { background: white; padding: 20px; border-radius: 10px; border: 1px solid #ddd; margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light">

<div class="container py-4">
    <h3 class="mb-4 text-primary">ปลด Global suppression (Single File Mode)</h3>

    <form id="mainForm" method="POST">
        <input type="hidden" name="execute_api" value="1">
        <input type="hidden" name="tasks_data" id="tasks_data">

        <div class="section-box shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <label class="fw-bold">Company ID:</label>
                    <input type="number" name="company_id" id="compIdInput" class="form-control" value="157" required>
                </div>
                <div class="col-md-8 text-end pt-4">
                    <input type="file" id="csvFile" class="d-none" accept=".csv" onchange="handleFile(this)">
                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('csvFile').click()">+ แนบไฟล์ CSV</button>
                    <button type="button" class="btn btn-outline-primary" onclick="addRow()">+ เพิ่มแถวกรอกมือ</button>
                </div>
            </div>
        </div>

        <div class="section-box shadow-sm">
            <h6 class="fw-bold border-bottom pb-2">รายการที่จะดำเนินการ</h6>
            <div id="itemContainer">
                <div class="input-group mb-2">
                    <input type="email" class="form-control row-email" placeholder="email@example.com">
                    <select class="form-select row-case" style="max-width: 150px;">
                        <option value="unsub">Unsub</option>
                        <option value="bounce">Bounce</option>
                        <option value="spam">Spam</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <button type="button" onclick="prepareAndSubmit()" class="btn btn-success btn-lg px-5 shadow fw-bold">เริ่มส่งข้อมูล (Process)</button>
        </div>
    </form>

    <div class="card border-0 shadow">
        <div class="card-header bg-dark text-white">Status Logs (Response Header & Body)</div>
        <div id="logBox" class="log-container"><?php echo htmlspecialchars($logOutput); ?></div>
    </div>
</div>

<script>
const CASE_MAPPER = {
    'unsub': 'unsub', 'unsubscribe': 'unsub', 'suppressed by recipient': 'unsub',
    'bounce': 'bounce', 'hardbounce': 'bounce', 'suppressed by hard bounced': 'bounce',
    'spam': 'spam', 'suppressed by complaint': 'spam'
};

function addRow(email = '', caseType = 'unsub') {
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `<input type="email" class="form-control row-email" value="${email}"><select class="form-select row-case" style="max-width: 150px;"><option value="unsub" ${caseType=='unsub'?'selected':''}>Unsub</option><option value="bounce" ${caseType=='bounce'?'selected':''}>Bounce</option><option value="spam" ${caseType=='spam'?'selected':''}>Spam</option></select><button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">✖</button>`;
    document.getElementById('itemContainer').appendChild(div);
}

async function handleFile(input) {
    if (!input.files.length) return;
    const text = await input.files[0].text();
    const rows = text.split('\n');
    const headers = rows[0].split(',').map(h => h.trim().toLowerCase());
    const eIdx = headers.indexOf('email'), cIdx = headers.indexOf('case');

    if (eIdx === -1 || cIdx === -1) return alert("Header ต้องเป็น email,case");

    for (let i = 1; i < rows.length; i++) {
        const cols = rows[i].split(',');
        if (cols.length >= 2) {
            const mCase = CASE_MAPPER[cols[cIdx]?.trim().toLowerCase()];
            if (cols[eIdx] && mCase) addRow(cols[eIdx].trim(), mCase);
        }
    }
}

function prepareAndSubmit() {
    const tasks = [];
    document.querySelectorAll('#itemContainer .input-group').forEach(group => {
        const email = group.querySelector('.row-email').value.trim();
        const caseType = group.querySelector('.row-case').value;
        if (email) tasks.push({ email, caseType });
    });

    if (tasks.length === 0) return alert("ไม่มีข้อมูล");
    
    document.getElementById('tasks_data').value = JSON.stringify(tasks);
    document.getElementById('logBox').innerHTML = "> กำลังส่งข้อมูลไปยัง Server... กรุณารอสักครู่\n";
    document.getElementById('mainForm').submit();
}
</script>
</body>
</html>
