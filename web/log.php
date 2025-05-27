<?php 
// Memulai session untuk manajemen state pengguna
session_start();
// Memuat konfigurasi database
require "../config/Database.php";

// Memastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Query untuk mengambil data order
$transaksi = htmlspecialchars(trim($_GET['transaksi'] ?? ''), ENT_QUOTES, 'UTF-8');
$start_date = htmlspecialchars(trim($_GET['start_date'] ?? ''), ENT_QUOTES, 'UTF-8');
$end_date = htmlspecialchars(trim($_GET['end_date'] ?? ''), ENT_QUOTES, 'UTF-8');
$order_by = htmlspecialchars(trim($_GET['order_by'] ?? ''), ENT_QUOTES, 'UTF-8');
$nama = htmlspecialchars(trim($_GET['nama'] ?? ''), ENT_QUOTES, 'UTF-8');

// Query untuk mengambil data order
$query = "
    SELECT 
        lo.id_user AS id_user,
        lo.order_id AS order_id,
        lo.transaksi AS transaksi,
        lo.Kategori AS kategori,
        lo.No_Tiket AS no_tiket,
        lo.status AS status,
        lo.progress_order AS progress_order,
        lo.keterangan AS keterangan,
        lo.tanggal AS tanggal,
        lo.nama AS nama,
        lo.role AS role,
        lo.order_by AS order_by
    FROM 
        log_orders lo
    WHERE 1=1
    ";


// Tambahkan filter jika ada input nama
if ($nama) {
    $query .= " AND lo.nama = :nama";
}

// Tambahkan filter jika ada input order_by
if ($order_by) {
    $query .= " AND lo.order_by = :order_by";
}

if ($transaksi) {
    $query .= " AND lo.Transaksi = :transaksi";
}
if ($kategori) {
    $query .= " AND lo.Kategori = :kategori";
}
if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND lo.tanggal BETWEEN :start_date AND :end_date";
} elseif (!empty($start_date)) {
    $query .= " AND lo.tanggal >= :start_date";
} elseif (!empty($end_date)) {
    $query .= " AND lo.tanggal <= :end_date";
}

// Tambahkan ORDER BY di akhir
$query .= " ORDER BY lo.tanggal DESC";

// Eksekusi query
$stmt = $pdo->prepare($query);

// Bind parameter jika ada
if ($nama) {
    $stmt->bindParam(":nama", $nama, PDO::PARAM_STR);
}
if ($order_by) {
    $stmt->bindParam(":order_by", $order_by, PDO::PARAM_STR);
}
if ($transaksi) {
    $stmt->bindParam(":transaksi", $transaksi, PDO::PARAM_STR);
}
if ($kategori) {
    $stmt->bindParam(":kategori", $kategori, PDO::PARAM_STR);
}
if (!empty($start_date) && !empty($end_date)) {
    $stmt->bindParam(":start_date", $start_date, PDO::PARAM_STR);
    $stmt->bindParam(":end_date", $end_date, PDO::PARAM_STR);
} elseif (!empty($start_date)) {
    $stmt->bindParam(":start_date", $start_date, PDO::PARAM_STR);
} elseif (!empty($end_date)) {
    $stmt->bindParam(":end_date", $end_date, PDO::PARAM_STR);
}

// eksekusi hasilnya    
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <meta http-equiv="refresh" content="60"> -->
    <link rel="stylesheet" href="./style/style.css">
    <title>Close</title>
    <!-- scripts eksternal -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
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

    <!-- konten -->
    <div class="content" id="content">
        <div class="navbar">
            <button id="toggleSidebar">☰</button>
            <a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
            <div class="profile-dropdown">
                <button id="profileButton"><?php echo htmlspecialchars($_SESSION['nama']); ?></button>
                <div class="profile-content" id="profileContent">
                    <!-- hanya tampil di admin -->
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
        <h1 class="headtitle">Log Provisioning</h1>
        <div class="filter">
            <form action="" method="GET">
                <!-- filter by order -->
                <select aria-label="order_by" name="order_by" id="order_by">
                    <option value="">All</option>
                    <option value="Plasa" <?= ($order_by === 'Plasa') ? 'selected' : '' ?>>PLASA</option>
                    <option value="Teknisi" <?= ($order_by === 'Teknisi') ? 'selected' : '' ?>>TEKNISI</option>
                </select>

                <!-- filter by transaksi -->
                <select aria-label="transaksi" name="transaksi" id="transaksi">
                    <option value="">All Transaksi</option>
                    <option value="PDA" <?= ($transaksi === 'PDA') ? 'selected' : '' ?>>PDA</option>
                    <option value="MO" <?= ($transaksi === 'MO') ? 'selected' : '' ?>>MO</option>
                    <option value="ORBIT" <?= ($transaksi === 'ORBIT') ? 'selected' : '' ?>>ORBIT</option>
                    <option value="FFG" <?= ($transaksi === 'FFG') ? 'selected' : '' ?>>FFG</option>
                    <option value="UNSPEk" <?= ($transaksi === 'UNSPEk') ? 'selected' : '' ?>>UNSPEK</option>
                    <option value="PSB" <?= ($transaksi === 'PSB') ? 'selected' : '' ?>>PSB</option>
                    <option value="RO" <?= ($transaksi === 'RO') ? 'selected' : '' ?>>RO</option>
                    <option value="SO" <?= ($transaksi === 'SO') ? 'selected' : '' ?>>SO</option>
                    <option value="DO" <?= ($transaksi === 'DO') ? 'selected' : '' ?>>DO</option>
                </select>

                <!-- filter by kategori -->
                <select aria-label="kategori" name="kategori" id="kategori">
                    <option value="">All Kategori</option>
                    <option value="Indibiz">Indibiz</option>
                    <option value="Indihome">Indihome</option>
                    <option value="Wifiid">Wifi.id</option>
                    <option value="Astinet">Astinet</option>
                    <option value="Metro">Metro</option>
                    <option value="VPNIP">VPNIP</option>
                    <option value="OLO">OLO</option>
                </select>

                <!-- filter by date -->
                <!-- <div class="filter_date"> -->
                    <label for="start_date">Date:</label>
                    <input type="date" name="start_date" id="start_date" value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>">
                    <label for="end_date">to:</label>
                    <input type="date" name="end_date" id="end_date" value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>">
                <!-- </div> -->

                <button type="submit">Filter</button>
            </form>
        </div>
        <div class="table-responsive">
            <table id="dataTable" class="display" style="width:100%">
            <thead>
                <!-- tabel kolom -->
                <tr>
                    <th>No</th>
                    <th>User ID</th>
                    <th>Order ID</th>
                    <th>Kategori</th>
                    <th>Transaksi</th>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>No Tiket</th>
                    <th>Progress Order</th>
                    <th>Status</th>
                    <th>Nama</th>
                    <th>Role</th>
                    <th>Order BY</th>
                </tr>
            </thead>
            <tbody>
            <!-- looping mengambil data dari order -->
            <?php if (!empty($orders)): ?>
                <?php $no = 1; ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= $no ?></td>
                        <td><?= htmlspecialchars($order['id_user']) ?></td>
                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                        <td><?= htmlspecialchars($order['kategori']) ?></td>
                        <td><?= htmlspecialchars($order['transaksi']) ?></td>
                        <td><?= htmlspecialchars($order['tanggal']) ?></td>
                        <td><?= htmlspecialchars($order['keterangan']) ?></td>
                        <td><?= htmlspecialchars($order['no_tiket']) ?></td>
                        <td><?= htmlspecialchars($order['progress_order']) ?></td>
                        <td><?= htmlspecialchars($order['status']) ?></td>
                        <td><?= htmlspecialchars($order['nama']) ?></td>
                        <td><?= htmlspecialchars($order['role']) ?></td>
                        <td><?= htmlspecialchars($order['order_by']) ?></td>
                    <?php $no++; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9">Tidak ada data order.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
        <!-- btn doenload -->
        <button id="downloadButton" class="download-btn">Download Excel</button>
    </div>

<!-- eksternal script js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./js/sidebar.js"></script> <!-- untuk sidebar -->
<script src="./js/profile.js"></script> <!-- untuk profile -->
<script src="./js/datatable.js"></script> <!-- untuk datatable -->
<script src="./js/cancel.js"></script> <!-- untuk cancel -->
<!-- <script src="./js/download.js"></script> -->

</body>
</html>