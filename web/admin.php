<?php
// Memulai session untuk mengelola state pengguna
session_start();
require "../config/Database.php"; // Memuat konfigurasi database

// Cek apakah pengguna sudah login dan memiliki role admin
if (!isset($_SESSION['role'])) {
    $_SESSION['message'] = "Anda harus login terlebih dahulu!";
    header("Location: index.php");
    exit();
}

//cek apakah yang login adalah admin
if ($_SESSION['role'] !== 'admin') {
    //jka tidak akan diarahkan balik ke dashboard
    $_SESSION['message'] = "Anda tidak memiliki akses untuk mengubah kategori!";
    header("Location: dashboard.php");
    exit();
}

//notifikasi
if(isset($_SESSION['message'])) {
  echo '<script>alert('.json_encode($_SESSION['message']).');</script>';
  unset($_SESSION['message']);
}

// Ambil regex kategori dari database
$stmt = $pdo->query("SELECT regex_pattern FROM kategori LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$kategori_regex = $row['regex_pattern'] ?? '';

// Ubah "|" menjadi ", " untuk placeholder
$kategori_placeholder = str_replace('|', ', ', $kategori_regex);

// Handling form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['category_regex'])) {
        $category_regex = trim($_POST['category_regex']); // Ambil input admin

        // Cek apakah input sama dengan yang tersimpan
        if ($category_regex === $kategori_regex) {
            $_SESSION['message'] = "Kategori tidak berubah!";
            header("Location: admin.php");
            exit();
        }

        // Validasi format (hanya boleh huruf, angka, dan | sebagai pemisah)
        if (!preg_match('/^[A-Za-z0-9|]+$/', $category_regex)) {
            $_SESSION['message'] = "Format kategori tidak valid! Gunakan hanya huruf, angka, dan pemisah '|'.";
            header("Location: admin.php");
            exit();
        }

        // Jika valid, update database
        try {
            $stmt = $pdo->prepare("UPDATE kategori SET regex_pattern = ?");
            $stmt->execute([$category_regex]);

            $_SESSION['message'] = "Kategori berhasil diperbarui!";
            header("Location: admin.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['message'] = "Gagal memperbarui kategori: " . $e->getMessage();
            header("Location: admin.php");
            exit();
        }
    } elseif (isset($_POST['group_name'], $_POST['group_id'])) {
        // Handle tambah group
        $group_name = trim($_POST['group_name']);
        $group_id = trim($_POST['group_id']);
        
        // Validasi input
        if (empty($group_name) || empty($group_id)) {
            $_SESSION['message'] = "Nama Group dan ID Group harus diisi!";
            header("Location: admin.php");
            exit();
        }
        
        if (!is_numeric($group_id)) {
            $_SESSION['message'] = "ID Group harus berupa angka!";
            header("Location: admin.php");
            exit();
        }

        try {
            // Cek apakah group sudah ada
            $stmt = $pdo->prepare("SELECT * FROM groups WHERE group_id = ?");
            $stmt->execute([$group_id]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['message'] = "Group ID sudah terdaftar!";
                header("Location: admin.php");
                exit();
            }
            
            // Tambahkan group baru
            $stmt = $pdo->prepare("INSERT INTO groups (group_id, group_name) VALUES (?, ?)");
            $stmt->execute([$group_id, $group_name]);
            
            $_SESSION['message'] = "Group berhasil ditambahkan!";
            header("Location: admin.php");
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['message'] = "Gagal menambahkan group: " . $e->getMessage();
            header("Location: admin.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "Input tidak boleh kosong!";
        header("Location: admin.php");
        exit();
    }
}

// Ambil data groups untuk datatable
try {
    $stmt = $pdo->query("SELECT * FROM groups");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $groups = [];
    $_SESSION['message'] = "Gagal mengambil data groups: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Tools</title>
  <link href="https://stackpath.bootstrapcdn.com/bootswatch/4.4.1/cosmo/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"> -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
  <!-- Style kustom untuk halaman admin -->
  <style>
    body {
      background-color: #2c3e50;
    }
    .wrapper {
      font-family: 'Poppins', sans-serif;
      width: 900px; /* Lebar wrapper disesuaikan */
      padding: 30px;
      margin: 50px auto 50px auto;
      display: flex;
      flex-direction: column; /* Mengatur tata letak menjadi atas-bawah */
      background-color: #EEEEEE;
      border-radius: 0.5rem;
      position: relative;
    }
    .close-btn {
      position: absolute;
      top: 5px;
      right: 10px;
      font-size: 30px;
      color: #333;
      cursor: pointer;
      background: none;
      border: none;
    }
    .wrapper h2 {
      padding: 10px;
      margin-bottom: 10px;
      font-weight: bold;
      text-align: center;
    }
    .wrapper p {
      padding: 8px;
      margin-bottom: 2px;
    }
    .wrapper form .form-group span {
      color: red;
    }
    .form-group {
      margin-bottom: 20px;
      padding: 10px;
    }
    .btn {
      border-color: #2c3e50;
    }
    .btn-customs {
      color: #2c3e50;
    }
    .btn:hover {
      background: #2c3e50;
      border-color: #2c3e50;
    }
    .admin-section {
      width: 100%; /* Lebar penuh untuk setiap section */
      margin-bottom: 30px; /* Jarak antara dua section */
      background-color: #fff;
      padding: 20px;
      border-radius: 0.5rem;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }
    .admin-section h3 {
      margin-bottom: 20px;
      text-align: center;
    }
    /* tabel */
    .table-responsive {
      background: #fff;
      margin: 10px;
      padding: 15px;
      z-index: 1;
      max-width: calc(100% - 10px);
      overflow-x: auto;
      border-radius: 8px; 
      box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1); 
    }

    table {
      width: 100%;
      table-layout: fixed;
      border-collapse: collapse;
      white-space: nowrap; 
    }

    table th {
      border: 1px solid #ddd;
      background: #2c3e50; 
      color: white;
      font-size: 18px; 
      font-weight: bold;
      padding: 10px; 
      text-align: center;
    }

    table td {
      border: 1px solid #ddd;
      padding: 10px;
      font-size: 14px; 
      text-align: left;
      background: #f9f9f9; 
      max-width: 100%
    }

    table td {
      border: 1px solid #ddd;
      padding: 10px;
      font-size: 14px; 
      text-align: left;
      background: #f9f9f9; 
      max-width: 100%;
    }
    .flex-group {
      display: flex;
      flex-wrap: nowrap; /* Mencegah turun ke bawah */
      gap: 20px; /* Jarak antar input */
      align-items: center; /* Menyelaraskan elemen sejajar */
    }

    .flex-group .form-group {
      flex: 1; /* Membuat input memiliki ukuran yang sama */
    }

    .flex-group input {
      width: 100%; /* Agar input mengisi form-group */
    }

    .group-name {
    display: block; 
    white-space: normal; /* Izinkan teks turun ke bawah */
    word-wrap: break-word; /* Pecah kata jika terlalu panjang */
    overflow-wrap: break-word; /* Pastikan teks bisa wrap */
    }
    
  </style>
</head>
<body>
    <?php if(isset($_SESSION['message'])): ?>
      <div class="alert alert-<?= (strpos($_SESSION['message'], 'berhasil') !== false) ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10000;">
          <?= $_SESSION['message'] ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
          </button>
      </div>
    <?php unset($_SESSION['message']); endif; ?>
    <main>
    <!-- Container utama --> 
    <section class="container wrapper">
      <!-- Tombol kembali -->
      <button class="close-btn" onclick="window.history.back();">&times;</button>
      <h2 class="display-4 pt-3">Admin Tools</h2>
      
      <!-- Section Manajemen Kategori -->
      <div class="admin-section">
        <h3>Manajemen Kategori</h3>
        <form action="" method="POST">
          <div class="form-group">
            <input type="text" name="category_regex" id="category_regex" class="form-control" placeholder="<?php echo htmlspecialchars($kategori_placeholder); ?>" required>
            <label for="category_regex">
              Format Edit Kategori 
              <a href="#" id="regexInfo" class="info-icon">ℹ️</a>
            </label>
          </div>

          <!-- Modal atau Tooltip untuk Menampilkan Info Regex -->
          <div id="regexModal" style="display: none; position: absolute; background: white; border: 1px solid #ccc; padding: 10px;">
            <strong>Format:</strong>
            <p>INDIHOME|INDIBIZ|Wifiid|Astinet|Metro|VPNIP|WMS|OLO</p>
          </div>

          <div class="form-group">
            <input type="submit" class="btn btn-block btn-outline-primary btn-customs" value="Edit Kategori">
          </div>
        </form>
      </div>

      <!-- Section Manajemen Group -->
      <div class="admin-section">
        <h3>Manajemen Group</h3>
        <div class="table-responsive">
        <h4 style="margin-bottom: 20px;">Daftar Group</h4>
            <table id="dataTable" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Group</th>
                    <th>Id Group</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            </table>
        </div>
        <h4 style="margin: 20px;">Tambah Group</h4>
              <!-- Di dalam form tambah group -->
        <form action="" method="POST">
            <div class="flex-group">
                <div class="form-group">
                    <input type="text" name="group_name" class="form-control" placeholder="Nama Group" required>
                </div>
                <div class="form-group">
                    <input type="number" name="group_id" class="form-control" placeholder="ID Group" required>
                </div>
                <div class="form-group">
                    <input type="submit" class="btn btn-outline-primary btn-customs" value="Tambah Group">
                </div>
            </div>
        </form>
      </div>
    </section>
  </main>
  <!-- Script untuk menangani tampilan informasi regex -->
  <script>
  document.getElementById("regexInfo").addEventListener("click", function(event) {
    event.preventDefault();
    let modal = document.getElementById("regexModal");
    modal.style.display = modal.style.display === "none" ? "block" : "none";
    modal.style.position = "absolute";
    modal.style.top = event.pageY + "px";
    modal.style.right = event.pageX  + "px";
  });

  document.addEventListener("click", function(event) {
    if (!event.target.matches("#regexInfo")) {
      document.getElementById("regexModal").style.display = "none";
    }
  });
  </script>
  <!-- Script untuk inisialisasi DataTable dan fungsi toggle status -->
  <script>
    $(document).ready(function() {
      // Inisialisasi DataTable untuk Group
      const groupTable = $('#dataTable').DataTable({
          ajax: {
              url: 'fetch_groups.php', // Endpoint PHP untuk mengambil data
              dataSrc: ''
          },
          scrollX: true,
          ordering: false,
          columns: [
              { 
                  data: null, 
                  render: function(data, type, row, meta) {
                      return meta.row + 1;
                  },
                  width: "50px"
              },
              { 
                  data: 'group_name',
                  render: function(data) {
                      return `<span class="group-name">${data}</span>`;
                  },
                  width: "200px"
              },
              { 
                  data: 'group_id',
                  width: "100px" 
              },
              { 
                  data: 'is_active', 
                  render: function(data, type, row) {
                      return `
                          <button class="btn btn-sm ${data ? 'btn-success' : 'btn-danger'}" 
                                  onclick="toggleGroupStatus(${row.group_id}, ${data})">
                              ${data ? 'Aktif' : 'Nonaktif'}
                          </button>
                      `;
                  },
                  width: "100px"
              },
              { // Kolom baru untuk hapus
                  data: null,
                  render: function(data, type, row) {
                      return `
                          <button class="btn btn-sm btn-danger" 
                                  onclick="deleteGroup(${row.group_id})">
                              Hapus
                          </button>
                      `;
                  },
                  width: "100px"
              }
          ],
          columnDefs: [{ targets: '_all', className: 'text-center' }]
      });

      // Fungsi toggle status
      window.toggleGroupStatus = function(groupId, currentStatus) {
          if (confirm(`Apakah Anda yakin ingin ${currentStatus ? 'menonaktifkan' : 'mengaktifkan'} group ini?`)) {
              $.ajax({
                  url: 'toggle_group.php',
                  method: 'POST',
                  data: { group_id: groupId },
                  dataType: 'json', // Pastikan respons dibaca sebagai JSON
                  success: function(response) {
                      if (response.success) {
                          alert(response.message);
                          groupTable.ajax.reload(null, false); // Refresh DataTable tanpa reset pagination
                      } else {
                          alert("Error: " + response.message);
                      }
                  },
                  error: function(xhr) {
                      alert("Terjadi kesalahan: " + xhr.responseText);
                  }
              });
          }
      }
      window.deleteGroup = function(groupId) {
        if (confirm('Apakah Anda yakin ingin menghapus grup ini?')) {
            $.ajax({
                url: 'delete_group.php',
                method: 'POST',
                data: { group_id: groupId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message);
                        groupTable.ajax.reload(null, false);
                    } else {
                        alert("Error: " + response.message);
                    }
                },
                error: function(xhr) {
                    alert("Terjadi kesalahan: " + xhr.responseText);
                }
            });
        }
    }
  });
  </script>
  <!-- <script src="./js/datatable.js"></script> -->
</body>
</html>