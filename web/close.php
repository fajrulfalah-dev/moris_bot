<?php 
session_start();
require "../config/Database.php";

// Memastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Query untuk mengambil data order
$transaksi = htmlspecialchars(trim($_GET['transaksi'] ?? ''), ENT_QUOTES, 'UTF-8');
$kategori = htmlspecialchars(trim($_GET['kategori'] ?? ''), ENT_QUOTES, 'UTF-8');
$start_date = htmlspecialchars(trim($_GET['start_date'] ?? ''), ENT_QUOTES, 'UTF-8');
$end_date = htmlspecialchars(trim($_GET['end_date'] ?? ''), ENT_QUOTES, 'UTF-8');
$order_by = htmlspecialchars(trim($_GET['order_by'] ?? ''), ENT_QUOTES, 'UTF-8');

// Query untuk mengambil data order
$query = "
    SELECT 
        o.Order_ID AS order_id,
        o.Kategori AS kategori,
        o.Transaksi AS transaksi,
        o.Keterangan AS Keterangan,
        o.No_Tiket AS no_tiket,
        o.id_telegram AS id_telegram,
        o.username_telegram AS username_telegram,
        u.Nama AS nama,
        o.tanggal AS tanggal,
        o.Status AS status,
        o.order_by AS order_by
    FROM 
        orders o
    LEFT JOIN 
        users u ON o.id_telegram = u.id_telegram
    WHERE 
        o.Status = 'Close'
";

// Tambahkan filter jika ada input order_by
if ($order_by) {
    $query .= " AND o.order_by = :order_by";
}

// Tambahkan filter jika ada input transaksi
if ($transaksi) {
    $query .= " AND o.Transaksi = :transaksi";
}
// Tambahkan filter jika ada input kategori
if ($kategori) {
    $query .= " AND o.Kategori = :kategori";
}
// Tambahkan filter jika ada input date
if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND o.tanggal BETWEEN :start_date AND :end_date";
} elseif (!empty($start_date)) {
    $query .= " AND o.tanggal >= :start_date";
} elseif (!empty($end_date)) {
    $query .= " AND o.tanggal <= :end_date";
}

// Tambahkan ORDER BY di akhir
$query .= " ORDER BY o.tanggal DESC";

// Eksekusi query
$stmt = $pdo->prepare($query);

// Bind parameter jika ada
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
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
    <!-- Script eksternal jquery datatable dan exceljs-->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<!-- Sidebar navigasi -->
<div class="sidebar" id="sidebar">
    <h1>MORIS BOT</h1>
    <!-- menu admin -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="dropdown">
        <button class="dropdown-btn">Dashboard</button>
        <div class="dropdown-container">
            <a href="dashboard.php">Provisioning</a>
            <a href="dashboard_ass.php">Assurance</a>
        </div>
    </div>
    <!-- Menu Provisioning -->
    <div class="dropdown">
    <button class="dropdown-btn">Provisioning</button>
        <div class="dropdown-container">
            <a href="order.php">Order</a>
            <a href="pickup.php">PickUp</a>
            <a href="close.php">Close</a>
            <a href="log.php">Log</a>
        </div>
    </div>
    
    <!-- Menu Assurance -->
    <div class="dropdown">
        <button class="dropdown-btn">Assurance</button>
        <div class="dropdown-container">
            <a href="order_ass.php">Order</a>
            <a href="pickup_ass.php">PickUp</a>
            <a href="close_ass.php">Close</a>
            <a href="log_ass.php">Log</a>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- menu helpdesk -->
    <?php if ($_SESSION['role'] === 'helpdesk' && $_SESSION['divisi'] === 'assurance'): ?>
        <div>
            <a href="dashboard_ass.php">Dashboard</a>
            <a href="order_ass.php">Order</a>
            <a href="pickup_ass.php">PickUp</a>
            <a href="close_ass.php">Close</a>
        </div>
    <?php endif; ?>
</div>


    <div class="content" id="content">
        <!-- Navbar dengan toggle sidebar dan menu profil -->
        <div class="navbar">
            <button id="toggleSidebar">☰</button>
            <a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
            <div class="profile-dropdown">
                <button id="profileButton"><?php echo htmlspecialchars($_SESSION['nama']); ?></button>
                <div class="profile-content" id="profileContent">
                    <!-- hanya terlihat jika yag login admin -->
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
        <h1 class="headtitle">Close Provisioning</h1>
        <!-- Filter data -->
        <div class="filter">
            <form action="" method="GET">
                <select aria-label="order_by" name="order_by" id="order_by">
                    <option value="">All</option>
                    <option value="Plasa" <?= ($order_by === 'Plasa') ? 'selected' : '' ?>>PLASA</option>
                    <option value="Teknisi" <?= ($order_by === 'Teknisi') ? 'selected' : '' ?>>TEKNISI</option>
                </select>

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

                <!-- <div class="filter_date"> -->
                    <label for="start_date">Date:</label>
                    <input type="date" name="start_date" id="start_date" value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>">
                    <label for="end_date">to:</label>
                    <input type="date" name="end_date" id="end_date" value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>">
                <!-- </div> -->

                <button type="submit">Filter</button>
            </form>
        </div>
        <!-- Tabel data order -->
        <div class="table-responsive">
            <table id="dataTable" class="display" style="width:100%">
            <thead>
                <!-- Kolom tabel -->
                <tr>
                    <th>No</th>
                    <th>Order ID</th>
                    <th>Kategori</th>
                    <th>Transaksi</th>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>No Tiket</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Log</th>
                </tr>
            </thead>
            <tbody>
            <!-- Loop data order dari database -->
            <?php if (!empty($orders)): ?>
                <?php $no = 1; ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= $no ?></td>
                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                        <td><?= htmlspecialchars($order['kategori']) ?></td>
                        <td><?= htmlspecialchars($order['transaksi']) ?></td>
                        <td><?= htmlspecialchars($order['tanggal']) ?></td>
                        <td class="text-container">
                            <!-- Fitur show more untuk keterangan panjang -->
                            <?php
                                $text = nl2br(htmlspecialchars($order['Keterangan']));
                                $shortText = substr($text, 0, 80); // Ambil 80 karakter pertama
                            ?>
                            <div class="short-text"><?= $shortText ?>...</div>
                            <div class="hidden-text" style="display: none;"><?= $text ?></div>
                            <button class="show-more">Show More</button>
                        </td>
                        <td><?= htmlspecialchars($order['no_tiket']) ?></td>
                        <td>
                            <!-- memanggil href link untuk ke telegram -->
                            <a href="https://t.me/<?= htmlspecialchars($order['username_telegram']) ?>" target="_blank">
                                <?= htmlspecialchars($order['nama']) ?>
                            </a>
                        </td>
                        <td>
                        <?= htmlspecialchars($order['status']) ?>
                        </td>
                        <td><a href="#" onclick="showLog('<?php echo htmlspecialchars($order['no_tiket'], ENT_QUOTES, 'UTF-8'); ?>')">Lihat Log</a></td>
                    </tr>
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
        <!-- Tombol download Excel -->
        <button id="downloadButton" class="download-btn">Download Excel</button>
    </div>

     <!-- log aktifitas -->
     <div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logModalLabel">Log Aktivitas</h5>
                </div>
                <div class="modal-body" id="logContent">
                    <!-- Data log akan dimuat di sini -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

<!-- Kumpulan script eksternal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="./js/sidebar.js"></script> <!-- Script toggle sidebar -->
<script src="./js/profile.js"></script> <!-- Script menu profil -->
<script src="./js/datatable.js"></script> <!-- Inisialisasi DataTables -->
<script src="./js/showmore.js"></script> <!-- Fungsi show more text -->
<script src="./js/cancel.js"></script> <!-- Fungsi cancel -->
<!-- <script src="./js/download.js"></script> -->
<script src="./js/log.js"></script> <!-- Fungsi tampilkan log -->

</body>
</html>