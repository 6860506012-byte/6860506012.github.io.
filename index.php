<?php
/**
 * --- ส่วนที่ 1: การจัดการฐานข้อมูล (Backend) ---
 */
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$db   = getenv('DB_NAME') ?: 'tree_db';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Logic: จัดการข้อมูลผ่าน AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $val = intval($_POST['value']);
        $stmt = $conn->prepare("INSERT INTO tree_nodes (node_value) VALUES (?)");
        $stmt->bind_param("i", $val);
        $stmt->execute();
        exit;
    }
    
    if ($action === 'delete') {
        $val = intval($_POST['value']);
        // ลบเลขที่ระบุออกจากฐานข้อมูล (ลบตัวที่เก่าที่สุดถ้ามีเลขซ้ำ)
        $stmt = $conn->prepare("DELETE FROM tree_nodes WHERE node_value = ? LIMIT 1");
        $stmt->bind_param("i", $val);
        $stmt->execute();
        exit;
    }
    
    if ($action === 'clear') {
        $conn->query("TRUNCATE TABLE tree_nodes");
        exit;
    }
}

// ดึงข้อมูลมาเตรียมวาดต้นไม้
$result = $conn->query("SELECT node_value FROM tree_nodes ORDER BY id ASC");
$nodes_from_db = [];
while($row = $result->fetch_assoc()) {
    $nodes_from_db[] = intval($row['node_value']);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Binary Tree with Delete Function</title>
    <style>
        :root {
            --bg-color: #fcfcfc;
            --text-main: #2d3436;
            --accent-green: #6ab04c;
            --node-border: #dfe6e9;
            --accent-red: #ff7675;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: var(--bg-color);
            font-family: 'Tahoma', sans-serif;
            color: var(--text-main);
        }

        .container {
            width: 100%;
            max-width: 800px;
            background: #ffffff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            text-align: center;
        }

        h1 { font-weight: 300; font-size: 1.5rem; margin-bottom: 25px; color: var(--accent-red); }

        .input-group { margin-bottom: 30px; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }

        input {
            padding: 10px;
            border: 1px solid var(--node-border);
            border-radius: 8px;
            width: 70px;
            text-align: center;
            outline: none;
        }

        button {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: 0.2s;
        }

        .btn-add { background-color: var(--accent-red); color: white; }
        .btn-delete { background-color: #fab1a0; color: #d63031; }
        .btn-clear { background-color: #dfe6e9; color: #636e72; }
        button:hover { opacity: 0.8; }

        canvas { width: 100%; height: auto; margin-top: 20px; }

        .results-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: left;
        }

        .result-item { margin-bottom: 10px; font-size: 0.95rem; }
        .result-item b { color: var(--accent-green); margin-right: 10px; }
    </style>
</head>
<body>

<div class="container">
    <h1> 🎄Binary Tree x MariaDB🎄</h1>
    
    <div class="input-group">
        <input type="number" id="nodeInput" placeholder="เลข">
        <button class="btn-add" onclick="addNode()">ปลูก Node</button>
        <button class="btn-delete" onclick="deleteNode()">ลบเลขนี้</button>
        <button class="btn-clear" onclick="resetTree()">ล้างสวน</button>
    </div>

    <canvas id="treeCanvas" width="800" height="400"></canvas>

    <div class="results-section">
        <div class="result-item"><b>Preorder:</b> <span id="preText">-</span></div>
        <div class="result-item"><b>Inorder:</b> <span id="inText">-</span></div>
        <div class="result-item"><b>Postorder:</b> <span id="postText">-</span></div>
    </div>
</div>

<script>
    class Node {
        constructor(v) { this.val = v; this.left = null; this.right = null; }
    }

    let root = null;
    const canvas = document.getElementById('treeCanvas');
    const ctx = canvas.getContext('2d');

    // ดึงข้อมูลเริ่มต้นจากฐานข้อมูล
    const initialData = <?php echo json_encode($nodes_from_db); ?>;

    window.onload = () => {
        initialData.forEach(v => {
            root = insertIntoTree(root, v);
        });
        render();
    };

    // --- ฟังก์ชันจัดการฐานข้อมูล (AJAX) ---
    async function addNode() {
        const input = document.getElementById('nodeInput');
        const val = parseInt(input.value);
        if (isNaN(val)) return;

        let formData = new FormData();
        formData.append('action', 'add');
        formData.append('value', val);
        await fetch('index.php', { method: 'POST', body: formData });

        root = insertIntoTree(root, val);
        input.value = '';
        render();
    }

    async function deleteNode() {
        const input = document.getElementById('nodeInput');
        const val = parseInt(input.value);
        if (isNaN(val)) return;

        let formData = new FormData();
        formData.append('action', 'delete');
        formData.append('value', val);
        await fetch('index.php', { method: 'POST', body: formData });

        root = removeFromTree(root, val);
        input.value = '';
        render();
    }

    async function resetTree() {
        if (!confirm("ลบข้อมูลทั้งหมด?")) return;
        let formData = new FormData();
        formData.append('action', 'clear');
        await fetch('index.php', { method: 'POST', body: formData });
        root = null;
        render();
    }

    // --- Logic ของ Binary Search Tree ---
    function insertIntoTree(node, v) {
        if (!node) return new Node(v);
        if (v < node.val) node.left = insertIntoTree(node.left, v);
        else node.right = insertIntoTree(node.right, v);
        return node;
    }

    function removeFromTree(node, v) {
        if (!node) return null;
        if (v < node.val) {
            node.left = removeFromTree(node.left, v);
        } else if (v > node.val) {
            node.right = removeFromTree(node.right, v);
        } else {
            // กรณีเจอเลขที่ต้องการลบ
            if (!node.left) return node.right;
            if (!node.right) return node.left;
            
            // กรณีมีลูกสองข้าง: หาตัวที่น้อยที่สุดในฝั่งขวามาแทนที่
            let minNode = findMin(node.right);
            node.val = minNode.val;
            node.right = removeFromTree(node.right, minNode.val);
        }
        return node;
    }

    function findMin(node) {
        while (node.left) node = node.left;
        return node;
    }

    // --- การวาดและแสดงผล ---
    function render() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (root) draw(root, canvas.width / 2, 40, 160);
        updateTraversalText();
    }

    function draw(node, x, y, space) {
        ctx.strokeStyle = "#dfe6e9";
        if (node.left) {
            ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x - space, y + 70); ctx.stroke();
            draw(node.left, x - space, y + 70, space / 1.8);
        }
        if (node.right) {
            ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + space, y + 70); ctx.stroke();
            draw(node.right, x + space, y + 70, space / 1.8);
        }
        ctx.beginPath(); ctx.arc(x, y, 18, 0, Math.PI * 2);
        ctx.fillStyle = "white"; ctx.fill();
        ctx.strokeStyle = "#ff7675"; ctx.stroke();
        ctx.fillStyle = "#2d3436"; ctx.font = "12px Arial"; ctx.textAlign = "center";
        ctx.fillText(node.val, x, y + 5);
    }

    const getPre = (n, r=[]) => { if(n){ r.push(n.val); getPre(n.left, r); getPre(n.right, r); } return r; };
    const getIn = (n, r=[]) => { if(n){ getIn(n.left, r); r.push(n.val); getIn(n.right, r); } return r; };
    const getPost = (n, r=[]) => { if(n){ getPost(n.left, r); getPost(n.right, r); r.push(n.val); } return r; };

    function updateTraversalText() {
        document.getElementById('preText').innerText = getPre(root).join(' → ') || '-';
        document.getElementById('inText').innerText = getIn(root).join(' → ') || '-';
        document.getElementById('postText').innerText = getPost(root).join(' → ') || '-';
    }
</script>
</body>
</html>
