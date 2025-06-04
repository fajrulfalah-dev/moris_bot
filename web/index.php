<?php
  // error_reporting(E_ALL);
  // ini_set('display_errors', 1);
  // Memulai sesi untuk menyimpan data login pengguna
  session_start();

  // Mengimpor konfigurasi koneksi database
  require "../config/Database.php"; // Pastikan file koneksi database sudah benar

  // Inisialisasi pesan error kosong
  $error_message = "";

  // Mengecek apakah form dikirim menggunakan metode POST
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
      // Mengambil input dari form dan membersihkan spasi
      $input_username = trim($_POST['username']);
      $input_password = trim($_POST['password']);

      try {
          // Query untuk mencari user berdasarkan username
          $query = "SELECT * FROM users WHERE Username = :username";
          $stmt = $pdo->prepare($query);
          $stmt->bindParam(":username", $input_username, PDO::PARAM_STR);
          $stmt->execute();

          // Cek apakah user ditemukan
          if ($stmt->rowCount() == 1) {
              $user = $stmt->fetch(PDO::FETCH_ASSOC);

              // Verifikasi password dengan SHA-256 (karena pakai SHA2 di database)
              if (hash('sha256', $input_password) === $user['Password']) {

                  // Update status menjadi 'active' setelah login berhasil
                  $update_query = "UPDATE users SET status = 'active' WHERE ID = :user_id";
                  $update_stmt = $pdo->prepare($update_query);
                  $update_stmt->bindParam(":user_id", $user['ID'], PDO::PARAM_INT);
                  $update_stmt->execute();

                  // Set session untuk pengguna (sesi ini akan disimpan di session)
                  $_SESSION['user_id'] = $user['ID'];
                  $_SESSION['username'] = $user['Username']; 
                  $_SESSION['nama'] = $user['Nama'];
                  $_SESSION['role'] = $user['role'];
                  $_SESSION['divisi'] = $user['divisi'];

                  // Redirect ke dashboard
                  // var_dump($_SESSION);
                  // header("Location: Dashboard.php");
                  if ($_SESSION['divisi'] === 'assurance') {
                    header("Location: dashboard_ass.php");
                  } else {
                      // Default: provisioning atau lainnya
                      header("Location: dashboard.php");
                  }
                  exit();
              } else {
                  // Password tidak cocok
                  $error_message = "Password salah!";
              }
          } else {
              // Username tidak ditemukan dalam database
              $error_message = "Username tidak ditemukan!";
          }
      } catch (PDOException $e) {
          // Menangani error jika terjadi kesalahan saat koneksi ke database
          $error_message = "Terjadi kesalahan saat menghubungkan ke database: " . $e->getMessage();
      }
  }
  ?>


  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Login</title>
    <!-- Menggunakan tema Cosmo dari Bootswatch (versi Bootstrap 4.4.1) -->
    <link href="https://stackpath.bootstrapcdn.com/bootswatch/4.4.1/cosmo/bootstrap.min.css" rel="stylesheet">
    <!-- Styling tambahan untuk tampilan halaman login -->
    <style>
      body{
        background-color: #2c3e50; /* Warna latar belakang gelap */
      }
      .wrapper { 
        font-family:'Poppins', sans-serif;
        width: 500px; 
        padding: 30px; 
        margin: auto;
        margin-top: 50px;
        display: flex;
        flex-direction: column;
        text-align: center;
        background-color: #EEEEEE;
        border-radius: 0.5rem;
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
      .wrapper form .form-group span {color: red;}

      .form-group{
        margin-bottom: 20px;
        padding: 10px;
      }
      .btn{
      border-color: #2c3e50;
      }
      .btn-customs{
        color: #2c3e50;
      }
      .btn:hover{
      background: #2c3e50;
      border-color: #2c3e50;
      }


    </style>
  </head>
  <body>
    <main>
      <section class="container wrapper">
        <!-- Judul halaman login -->
        <h2 class="display-4 pt-3">Login</h2>
        <p class="text-center">Silahkan masukkan username dan password.</p>

        <!-- Menampilkan pesan error jika ada -->
        <?php if ($error_message): ?>
          <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Form login -->
        <form action="" method="POST"> 
          <div class="form-group">
            <!-- Input untuk username -->
            <input type="text" name="username" id="username" class="form-control" placeholder="Username" required >
          </div>

          <div class="form-group">
            <!-- Input untuk password -->
            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
          </div>

          <div class="form-group">
            <!-- Tombol submit login -->
            <input type="submit" class="btn btn-block btn-outline-primary btn-customs" value="Login">
          </div>
        </form>
      </section>
    </main>
  </body>
  </html>
