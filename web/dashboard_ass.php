<?php
// Memulai session untuk manajemen state pengguna
session_start();
// Memuat konfigurasi database  
require "../config/Database.php";


// Validasi otentikasi pengguna
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Unauthorized"]);
    header("Location: index.php");
    exit();
}

// Inisialisasi variabel untuk menyimpan data statistik
$order_count = 0;
$pickup_count = 0;
$close_count = 0;
$produktifitiData = [];

// Handling request AJAX untuk data dashboard
if (isset($_GET['ajax']) && $_GET['ajax'] == "true") {
    header("Content-Type: application/json");

     // Sanitasi parameter input GET
    $transaksi = htmlspecialchars(trim($_GET['transaksi'] ?? ''), ENT_QUOTES, 'UTF-8');
    $start_date = htmlspecialchars(trim($_GET['start_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $end_date = htmlspecialchars(trim($_GET['end_date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $order_by = htmlspecialchars(trim($_GET['order_by'] ?? ''), ENT_QUOTES, 'UTF-8');

    // Query Filter Order Count
    $sql = "SELECT Status, COUNT(DISTINCT order_id) AS jumlah FROM log_orders WHERE 1=1";

    // Dynamic query building berdasarkan parameter filter
    if (!empty($order_by)) {
        $sql .= " AND order_by = :order_by";
    }
    if (!empty($transaksi)) {
        $sql .= " AND transaksi = :transaksi";
    }
    if (!empty($start_date) && !empty($end_date)) {
        $sql .= " AND DATE(tanggal) BETWEEN :start_date AND :end_date";
    }

    $sql .= " GROUP BY Status";

    $stmt = $pdo->prepare($sql);

    if (!empty($order_by)) {
        $stmt->bindParam(":order_by", $order_by);
    }
    if (!empty($transaksi)) {
        $stmt->bindParam(':transaksi', $transaksi);
    }
    if (!empty($start_date) && !empty($end_date)) {
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
    }

    $stmt->execute();

    // Mengolah hasil query untuk statistik order
    $orders_count = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $orders_count[$row['Status']] = $row['jumlah'];
    }

    $userRole = $_SESSION['role'];
    $userId = $_SESSION['user_id'];

    // Query untuk tabel produktifiti dengan filter
    $queryProduktifiti = "SELECT 
                            lo.nama AS Nama, 
                            COUNT(DISTINCT CASE WHEN lo.transaksi = 'PDA' AND lo.status = 'Close' THEN lo.order_id END) AS PDA,
                            COUNT(DISTINCT CASE WHEN lo.transaksi = 'MO' AND lo.status = 'Close' THEN lo.order_id END) AS MO,
                            COUNT(DISTINCT CASE WHEN lo.transaksi = 'ORBIT' AND lo.status = 'Close' THEN lo.order_id END) AS ORBIT,
                            COUNT(DISTINCT CASE WHEN lo.transaksi = 'FFG' AND lo.status = 'Close' THEN lo.order_id END) AS FFG,
                            COUNT(DISTINCT CASE WHEN lo.transaksi = 'UNSPEK' AND lo.status = 'Close' THEN lo.order_id END) AS UNSPEK,
                            COUNT(DISTINCT CASE WHEN lo.transaksi = 'PSB' AND lo.status = 'Close' THEN lo.order_id END) AS PSB,
                            COUNT(DISTINCT CASE WHEN lo.transaksi = 'RO' AND lo.status = 'Close' THEN lo.order_id END) AS RO,
                            COUNT(DISTINCT CASE WHEN lo.transaksi = 'SO' AND lo.status = 'Close' THEN lo.order_id END) AS SO,
                            COUNT(DISTINCT CASE WHEN lo.transaksi = 'DO' AND lo.status = 'Close' THEN lo.order_id END) AS DO,
                            COUNT(DISTINCT CASE WHEN lo.status IN ('Close') THEN lo.order_id END) AS RecordCount
                        FROM 
                            log_orders lo
                        WHERE 
                            lo.role = 'Helpdesk'";

    // Tambahkan filter jika ada
    if ($userRole === 'helpdesk') {
        $queryProduktifiti .= " AND lo.id_user = :user_id";
    }
    if (!empty($order_by)) {
        $queryProduktifiti .= " AND lo.order_by = :order_by";
    }
    if (!empty($transaksi)) {
        $queryProduktifiti .= " AND lo.transaksi = :transaksi";
    }
    if (!empty($start_date) && !empty($end_date)) {
        $queryProduktifiti .= " AND DATE(lo.tanggal) BETWEEN :start_date AND :end_date";
    }

    // grup by 
    $queryProduktifiti .= " GROUP BY lo.id_user, lo.nama ORDER BY RecordCount DESC";

    $stmtProduktifiti = $pdo->prepare($queryProduktifiti);

    if ($userRole === 'helpdesk') {
        $stmtProduktifiti->bindParam(':user_id', $userId);
    }
    if (!empty($order_by)) {
        $stmtProduktifiti->bindParam(":order_by", $order_by);
    }
    if (!empty($transaksi)) {
        $stmtProduktifiti->bindParam(':transaksi', $transaksi);
    }
    if (!empty($start_date) && !empty($end_date)) {
        $stmtProduktifiti->bindParam(':start_date', $start_date);
        $stmtProduktifiti->bindParam(':end_date', $end_date);
    }

    $stmtProduktifiti->execute();
    $produktifitiData = $stmtProduktifiti->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk progressChart dengan filter
    $queryProgress = "SELECT tanggal, COUNT(*) as total FROM orders WHERE 1=1";

    if (!empty($order_by)) {
        $queryProgress .= " AND order_by = :order_by";
    }
    if (!empty($transaksi)) {
        $queryProgress .= " AND transaksi = :transaksi";
    }
    if (!empty($start_date) && !empty($end_date)) {
        $queryProgress .= " AND DATE(tanggal) BETWEEN :start_date AND :end_date";
    }

    // grup by
    $queryProgress .= " GROUP BY tanggal ORDER BY tanggal";
    $stmtProgress = $pdo->prepare($queryProgress);

    // Bind parameter untuk progressChart
    if (!empty($order_by)) {
        $stmtProgress->bindParam(":order_by", $order_by);
    }
    if (!empty($transaksi)) {
        $stmtProgress->bindParam(':transaksi', $transaksi);
    }
    if (!empty($start_date) && !empty($end_date)) {
        $stmtProgress->bindParam(':start_date', $start_date);
        $stmtProgress->bindParam(':end_date', $end_date);
    }

    $stmtProgress->execute();
    $dataProgress = $stmtProgress->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk categoryChart dengan filter
    $queryCategory = "SELECT Kategori, COUNT(*) as total FROM orders WHERE 1=1";

    if (!empty($order_by)) {
        $queryCategory .= " AND order_by = :order_by";
    }
    if (!empty($transaksi)) {
        $queryCategory .= " AND transaksi = :transaksi";
    }
    if (!empty($start_date) && !empty($end_date)) {
        $queryCategory .= " AND DATE(tanggal) BETWEEN :start_date AND :end_date";
    }

    // grup by kategori
    $queryCategory .= " GROUP BY Kategori";
    $stmtCategory = $pdo->prepare($queryCategory);

    // Bind parameter untuk categoryChart
    if (!empty($order_by)) {
        $stmtCategory->bindParam(":order_by", $order_by);
    }
    if (!empty($transaksi)) {
        $stmtCategory->bindParam(':transaksi', $transaksi);
    }
    if (!empty($start_date) && !empty($end_date)) {
        $stmtCategory->bindParam(':start_date', $start_date);
        $stmtCategory->bindParam(':end_date', $end_date);
    }

    $stmtCategory->execute();
    $dataCategory = $stmtCategory->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk progressTypeChart dengan filter
    $queryProgressType = "SELECT progress_order, COUNT(*) as total FROM orders WHERE 1=1";
    
    if (!empty($order_by)) {
        $queryProgressType .= " AND order_by = :order_by";
    }
    if (!empty($transaksi)) {
        $queryProgressType.= " AND transaksi = :transaksi";
    }
    if (!empty($start_date) && !empty($end_date)) {
        $queryProgressType .= " AND DATE(tanggal) BETWEEN :start_date AND :end_date";
    }

    // grup by progess_order
    $queryProgressType .= " GROUP BY progress_order";
    $stmtProgressType = $pdo->prepare($queryProgressType);

    // Bind parameter untuk progressTypeChart
    if (!empty($order_by)) {
        $stmtProgressType ->bindParam(":order_by", $order_by);
    }
    if (!empty($transaksi)) {
        $stmtProgressType ->bindParam(':transaksi', $transaksi);
    }
    if (!empty($start_date) && !empty($end_date)) {
        $stmtProgressType ->bindParam(':start_date', $start_date);
        $stmtProgressType ->bindParam(':end_date', $end_date);
    }

    $stmtProgressType->execute();
    $dataProgressType = $stmtProgressType->fetchAll(PDO::FETCH_ASSOC);

    // menampilkan sisa order
    $querySisaOrder = "SELECT COUNT(*) as sisa_order FROM orders WHERE status = 'Order'";

    if (!empty($order_by)) {
        $querySisaOrder .= " AND order_by = :order_by";
    }
    if (!empty($transaksi)) {
        $querySisaOrder .= " AND transaksi = :transaksi";
    }
    if (!empty($start_date) && !empty($end_date)) {
        $querySisaOrder .= " AND DATE(tanggal) BETWEEN :start_date AND :end_date";
    }

    $stmtSisaOrder = $pdo->prepare($querySisaOrder);

    if (!empty($order_by)) {
        $stmtSisaOrder->bindParam(":order_by", $order_by);
    }
    if (!empty($transaksi)) {
        $stmtSisaOrder->bindParam(':transaksi', $transaksi);
    }
    if (!empty($start_date) && !empty($end_date)) {
        $stmtSisaOrder->bindParam(':start_date', $start_date);
        $stmtSisaOrder->bindParam(':end_date', $end_date);
    }

    $stmtSisaOrder->execute();
    $sisaOrder = $stmtSisaOrder->fetch(PDO::FETCH_ASSOC);

    $querySisaPickup = "SELECT COUNT(*) as sisa_pickup FROM orders WHERE status = 'Pickup'";

    if (!empty($order_by)) {
        $querySisaPickup .= " AND order_by = :order_by";
    }
    if (!empty($transaksi)) {
        $querySisaPickup .= " AND transaksi = :transaksi";
    }
    if (!empty($start_date) && !empty($end_date)) {
        $querySisaPickup .= " AND DATE(tanggal) BETWEEN :start_date AND :end_date";
    }

    $stmtSisaPickup = $pdo->prepare($querySisaPickup);

    if (!empty($order_by)) {
        $stmtSisaPickup->bindParam(":order_by", $order_by);
    }
    if (!empty($transaksi)) {
        $stmtSisaPickup->bindParam(':transaksi', $transaksi);
    }
    if (!empty($kategori)) {
        $stmtSisaPickup->bindParam(':kategori', $kategori);
    }
    if (!empty($start_date) && !empty($end_date)) {
        $stmtSisaPickup->bindParam(':start_date', $start_date);
        $stmtSisaPickup->bindParam(':end_date', $end_date);
    }

    $stmtSisaPickup->execute();
    $sisaPickup = $stmtSisaPickup->fetch(PDO::FETCH_ASSOC);

    // Gabungkan semua data dalam satu output JSON
    echo json_encode([
        "orders_count" => $orders_count,
        "sisa_order" => $sisaOrder['sisa_order'],
        "sisa_pickup" => $sisaPickup['sisa_pickup'],
        "produktifitiData" => $produktifitiData,
        "progressChart" => $dataProgress,
        "categoryChart" => $dataCategory,
        "progressTypeChart" => $dataProgressType
    ]);

    exit();
}

// Jika tidak ada request AJAX, lanjutkan dengan menampilkan halaman biasa
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <meta http-equiv="refresh" content="60"> -->
    <link rel="stylesheet" href="./style/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
    <title>Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<!-- Sidebar navigasi -->
<div class="sidebar" id="sidebar">
    <h1>MORIS BOT</h1>
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="dropdown">
        <button class="dropdown-btn">Dashboard</button>
        <div class="dropdown-container">
            <a href="dashboard.php">Provisioning</a>
            <a href="dashboard_ass.php">Assurance</a>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($_SESSION['role'] === 'helpdesk'): ?>
    <a href="dashboard.php">Dashboard</a>
    <?php endif; ?>
    <?php if ($_SESSION['role'] === 'helpdesk'): ?>
    <a href="dashboard_ass.php">Dashboard</a>
    <?php endif; ?>
    
    <!-- Menu Provisioning -->
    <div class="dropdown">
        <button class="dropdown-btn">Provisioning</button>
        <div class="dropdown-container">
            <a href="order.php">Order</a>
            <a href="pickup.php">PickUp</a>
            <a href="close.php">Close</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="log.php">Log</a>
            <?php endif; ?>
        </div>
    </div>
    <!-- Menu Assurance -->
    <div class="dropdown">
        <button class="dropdown-btn">Assurance</button>
        <div class="dropdown-container">
            <a href="order_ass.php">Order</a>
            <a href="pickup_ass.php">PickUp</a>
            <a href="close_ass.php">Close</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="log_ass.php">Log</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- konten utama halaman dashboard -->
<div class="content" id="content">
    <div class="navbar">
        <button id="toggleSidebar">☰</button>
        <a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
        <div class="profile-dropdown">
            <button id="profileButton"><?php echo htmlspecialchars($_SESSION['nama']); ?></button>
            <div class="profile-content" id="profileContent">
                <!-- hanya admin yang tampil -->
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="add_user.php">Tambah User</a>
                <a href="admin.php">Tools</a>
                <?php endif; ?>
                <a href="reset_password.php">Reset Password</a>
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn" style="width: 100%; border: none; background: none; text-align: left;">Logout</button>
                </form>
            </div>
        </div>
    </div>
    <h1 class="headtitle">Dashboard Assurance</h1>
    <!-- Filter -->
    <div class="filter">
        <form action="" id="filterForm" method="GET">
            <!-- plasa or teknisi -->
            <select aria-label="order_by" name="order_by" id="order_by">
                <option value="">All</option>
                <option value="Plasa" <?= (isset($order_by) && $order_by === 'Plasa') ? 'selected' : '' ?>>PLASA</option>
                <option value="Teknisi" <?= (isset($order_by) && $order_by === 'Teknisi') ? 'selected' : '' ?>>TEKNISI</option>
            </select>
            <!-- berdasarkan transaksi -->
            <select aria-label="transaksi" name="transaksi" id="transaksi">
                <option value="">All Transaksi</option>
                <option value="PDA">PDA</option>
                <option value="MO">MO</option>
                <option value="ORBIT">ORBIT</option>
                <option value="FFG">FFG</option>
                <option value="UNSPEK">UNSPEK</option>
                <option value="PSB">PSB</option>
                <option value="RO">RO</option>
                <option value="SO">SO</option>
                <option value="DO">DO</option>
            </select>

            <select aria-label="transaksi" name="transaksi" id="transaksi">
                    <option value="">All Permintaan</option>
                    <option value="SENDMYI" <?= ($transaksi === 'SENDMYI') ? 'selected' : '' ?>>SEND MYI</option>
                    <option value="CEKPASSWORDWIFI" <?= ($transaksi === 'CEKPASSWORDWIFI') ? 'selected' : '' ?>>CEK PASSWORD WIFI</option>
                    <option value="CEKREDAMAN" <?= ($transaksi === 'CEKREDAMAN') ? 'selected' : '' ?>>CEK REDAMAN</option>
                    <option value="INTERNETERROR" <?= ($transaksi === 'INTERNETERROR') ? 'selected' : '' ?>>INTERNET ERROR</option>
                    <option value="GANTIONT" <?= ($transaksi === 'GANTIONT') ? 'selected' : '' ?>>GANTI ONT</option>
                    <option value="GANTISTB" <?= ($transaksi === 'GANTISTB') ? 'selected' : '' ?>>GANTI STB</option>
                    <option value="OMSET" <?= ($transaksi === 'OMSET') ? 'selected' : '' ?>>OMSET</option>
                    <option value="VOIPERROR" <?= ($transaksi === 'VOIPERROR') ? 'selected' : '' ?>>VOIP ERROR</option>
                    <option value="USERERROR" <?= ($transaksi === 'USERERROR') ? 'selected' : '' ?>>USER ERROR</option>
                </select>

            <!-- <div class="filter_date"> -->
            <label for="start_date">Date:</label>
            <input type="date" name="start_date" id="start_date" value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>">
            <label for="end_date">to:</label>
            <input type="date" name="end_date" id="end_date" value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>">
            <!-- </div> -->

            <button type="submit">Filter</button>
        </form>
    </div>
    <!-- Kartu Statistik -->
    <div class="stats">
        <div class="sisa_order">
            <h3>Sisa Order</h3>
            <p class="record-count" id="sisa_order_count">0</p>
        </div>
        <div class="sisa_pickup">
            <h3>Sisa Pickup</h3>
            <p class="record-count" id="sisa_pickup_count">0</p>
        </div>
        <div class="card_close">
            <h3>Total Close</h3>
            <p class="record-count" id="close_count">0</p>
        </div>
        <div class="card_order">
            <h3>Total Order</h3>
            <p class="record-count" id="order_count">0</p>
        </div>
    </div>
    <!-- Tabel dan Grafik -->
    <div class="dashboard-content">
        <!-- validasi hanya tampil di admin dan Helpdesk -->
        <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'helpdesk'): ?>
            <div class="table-container">
                <table id="productivityTable" class="display">
                <!-- <table id="dataTable" class="display" style="width:100%"> -->
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>PDA</th>
                            <th>MO</th>
                            <th>ORBIT</th>
                            <th>FFG</th>
                            <th>UNSPEK</th>
                            <th>PSB</th>
                            <th>RO</th>
                            <th>SO</th>
                            <th>DO</th>
                            <th>Total</th>
                            <th>Log</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <tr><td colspan="8">Loading...</td></tr>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    <!-- Grafik Progress by Tanggal hanya tampil di admin -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="table-container">
            <canvas id="progressChart"></canvas>
    </div>
    <div class="chart-container">
            <div class="chart-box">
                <canvas id="categoryChart"></canvas>
            </div>
            <div class="chart-box">
                <canvas id="progressTypeChart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- js eksternal -->
<script src="./js/sidebar.js"></script>  
<script src="./js/card.js"></script>
<script src="./js/profile.js"></script>



</body>
</html>
