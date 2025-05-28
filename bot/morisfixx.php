<?php
session_start();
require "../config/Database.php";
require "../config/bot_config.php";

// File untuk menyimpan offset terakhir
$offsetFile = 'last_offset.txt';

// Membaca offset terakhir dari file (jika ada)
$offset = file_exists($offsetFile) ? (int)file_get_contents($offsetFile) : 0;

while (true) {
    // Ambil update dari Telegram
    $updates = json_decode(file_get_contents(API_URL . "getUpdates?offset=$offset"), true);

    if ($updates["ok"] && count($updates["result"]) > 0) {
        foreach ($updates["result"] as $update) {
            $offset = $update["update_id"] + 1; // Update offset ke ID update terakhir + 1

            // Menyimpan offset terakhir ke file
            file_put_contents($offsetFile, $offset);

            $message = $update["message"] ?? null;

            if ($message) {
                $chat_id = $message["chat"]["id"];
                $chat_type = $message["chat"]["type"] ?? '';
                $text = $message["text"] ?? "";
                $user_id = $message["from"]["id"];
                $username = $message["from"]["username"] ?? "Unknown";
                $message_id = $message["message_id"]; // Extract message_id

                handleUserMessage($user_id, $chat_id, $text, $username, $chat_type, $message_id);
            }
        }
    }

    sendNotifications();

    sleep(2); // Delay untuk mencegah bot terlalu sering mengecek update
}

function handleUserMessage($user_id, $chat_id, $text, $username, $chat_type, $message_id) {
    global $pdo;

    //jika pengguna chat bot secara pribadi
    if ($chat_type === 'private') {
        //jika pengguna mengirimkan pesan /start
        if ($text == "/start") {
            sendMessage($chat_id, "Selamat datang! Ketik /daftar untuk registrasi");
        //jika pengguna mengirimkan pesan /daftar
        } elseif ($text == "/daftar") {
            // Cek apakah user sudah terdaftar
            $stmt = $pdo->prepare("SELECT * FROM users WHERE ID_Telegram = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            //jika id_telegram pengguna sudah terdaftar
            if ($user) {
                sendMessage($chat_id, "Anda sudah terdaftar sebagai {$user['Role']}.");
            //jika belum. maka..
            } else {
                // Mulai proses registrasi. id telegram pengguna disimpan ke tabel user_states
                $pdo->prepare("INSERT INTO user_states (user_id, state) VALUES (?, 'awaiting_name') 
                            ON DUPLICATE KEY UPDATE state = 'awaiting_name'")
                    ->execute([$user_id]);
                //mengirim pesan ke pengguna
                sendMessage($chat_id, "Masukkan nama Anda:");
            }
        } else {
            // Ambil state dari database
            $stmt = $pdo->prepare("SELECT * FROM user_states WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $state = $stmt->fetch();

            if ($state) {
                //jika state pengguna adalah 'awaiting_name' maka..
                if ($state['state'] == 'awaiting_name') {
                    // Simpan nama dan update state
                    $pdo->prepare("UPDATE user_states SET name = ?, state = 'awaiting_role' WHERE user_id = ?")
                        ->execute([$text, $user_id]);
                    //bot akan mengirimkan pesan ini..
                    sendMessage($chat_id, "Masukkan role (Teknisi/Plasa):");
                //jika state pengguna adalah 'awaiting_role' maka..
                } elseif ($state['state'] == 'awaiting_role') {
                    // role disimpan dalam lowercase
                    $role = strtolower($text);
                    // pengguna akan disuruh mengisi role
                    if ($role === 'teknisi' || $role === 'plasa') {
                        // Simpan ke tabel users
                        $pdo->beginTransaction();
                        $pdo->prepare("INSERT INTO users (Nama, Role, ID_Telegram) VALUES (?, ?, ?)")
                            ->execute([$state['name'], ucfirst($role), $user_id]);
                        // Hapus state
                        $pdo->prepare("DELETE FROM user_states WHERE user_id = ?")->execute([$user_id]);
                        $pdo->commit();
                        sendMessage($chat_id, "Registrasi berhasil! Nama: {$state['name']}, Role: " . ucfirst($role));
                    //jika role yang dimasukkan tidak valid maka akan mengirim pesan ini..
                    } else {
                        sendMessage($chat_id, "Role tidak valid. Masukkan 'Teknisi' atau 'Plasa'.");
                    }
                }
            }
        }
    }elseif ($chat_type === 'group' || $chat_type === 'supergroup') {
        // Hanya cek database jika pesan adalah /moban atau /help
        if (strpos($text, "/moban") === 0 || $text == "/help" || $text == "/cek" || strpos($text, "/bantu") === 0  || $text == "/assurance") {
            //memerikas apakah grup sudah terdaftar di database
            $stmt = $pdo->prepare("SELECT * FROM groups WHERE group_id = ? AND is_active = 1");
            $stmt->execute([$chat_id]);
            $group = $stmt->fetch();

            // Jika grup tidak terdaftar, kirim pesan dan hentikan eksekusi
            if (!$group) {
                sendMessage($chat_id, "Grup belum terdaftar sedang dinonaktifkan di bot.\n\nHubungi admin untuk mendaftarkan grup ini.");
                return;
            }

            // jika grup terdaftar. Proses perintah yang valid /moban, /help, /cek
            if (strpos($text, "/moban") === 0) {
                handleOrder($text, $chat_id, $message_id, $user_id, $username);
            } elseif ($text == "/help") {
                sendHelpMessage($chat_id, $message_id);
            } elseif (strpos($text, "/cek") === 0) {
                handleCekOrder($text, $chat_id, $message_id);
            } elseif (strpos($text, "/bantu") === 0) {
                handleOrderAssurance($text, $chat_id, $message_id, $user_id, $username);
            } elseif (strpos($text, "/assurance") === 0) {
                sendHelpMessageAssurance($chat_id, $message_id) ;
            }    
        } 
    }
}

//fungsi untuk menangani /cek order
function handleCekOrder($text, $chat_id, $message_id) {
    global $pdo;

    // Cek apakah grup terdaftar dan aktif di database
    $stmtGroup = $pdo->prepare("SELECT * FROM groups WHERE group_id = ? AND is_active = 1");
    $stmtGroup->execute([$chat_id]);
    $group = $stmtGroup->fetch();

    if (!$group) {
        replyMessage($chat_id, "Grup belum terdaftar atau dinonaktifkan di bot.\n\nHubungi admin untuk mendaftarkan grup ini.", $message_id);
        return;
    }

    // Ekstrak Order_ID dari pesan
    $parts = explode(' ', $text);
    if (count($parts) < 2) {
        //jika salah format kirim pesan tersebut
        replyMessage($chat_id, "Format salah. Gunakan: /cek [Order_ID]\nContoh: /cek WO123456789", $message_id);
        return;
    }
    
    //ambil order id dari text
    $orderId = trim($parts[1]);

    // Query database berdarsarkan order_id yang diambil
    $stmt = $pdo->prepare("
            SELECT 
                    o.*,
                    u.Nama AS pembuat,
                    lo.nama AS penangani,
                    lo.status AS log_status
                FROM orders o
                LEFT JOIN users u ON o.id_telegram = u.ID_Telegram
                LEFT JOIN log_orders lo ON lo.no = (
                    SELECT MAX(no)
                    FROM log_orders
                    WHERE no_tiket = o.No_Tiket AND status IN ('Pickup', 'Close')
                )
                WHERE o.Order_ID = ?
            ");
    
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    //jika order tidak ditemukan dalam database
    if (!$order) {
        replyMessage($chat_id, "Order ID $orderId tidak ditemukan dalam sistem.", $message_id);
        return;
    }

    // Format respons ketika order ditemukan
    $response = "Detail Order\n";
    $response .= "────────────────────\n";
    $response .= "• Order ID: {$order['Order_ID']}\n";
    $response .= "• No Tiket: {$order['No_Tiket']}\n";
    $response .= "• Kategori: {$order['Kategori']}\n";
    $response .= "• Transaksi: {$order['Transaksi']}\n";
    $response .= "• Status: {$order['progress_order']}\n";
    $response .= "• Dibuat oleh: {$order['pembuat']} (@{$order['username_telegram']})\n";
    // Tambahkan penangani(Provi) hanya jika status Pickup/Close
    if(in_array($order['log_status'], ['Pickup', 'Close'])) {
        $response .= "• Ditangani oleh: {$order['penangani']}\n";
    }
    $response .= "• Tanggal:  {$order['tanggal']}  \n";
    $response .= "• Keterangan: {$order['Keterangan']}\n";
    $response .= "────────────────────\n";

    //mengirim pesan berupa reply
    replyMessage($chat_id, $response, $message_id);
}


//fungsi untuk menangani perintah /help
function sendHelpMessage($chat_id, $message_id) {
    //informasi yang akan ditampilkan
    $helpMessage = "Panduan Penggunaan Perintah `/moban`\n\n"
                . "Gunakan format berikut:\n"
                . "/moban #Kategori #Transaksi #Order_ID #Keterangan\n"
                . "\nKategori:"
                . "\n- INDIHOME"
                . "\n- INDIBIZ"
                . "\n- Wifiid"
                . "\n- Astinet"
                . "\n- Metro"
                . "\n- VPNIP"
                . "\n- OLO"
                . "\n\nJenis Transaksi:"
                . "\n- PDA"
                . "\n- MO"
                . "\n- ORBIT"
                . "\n- FFG"
                . "\n- UNSPEK"
                . "\n- PSB"
                . "\n- DO"
                . "\n- RO"
                . "\n- SO"
                . "\n\n Untuk informasi lebih lanjut, hubungi admin.
                
                \n\nPerintah untuk cek status order:\n/cek [Order_ID]\nContoh: /cek MOk42504171139006946cd3d0";

    //mengirim jawaban berupa reply
    replyMessage($chat_id, $helpMessage, $message_id);
}

//fungsi mengirim pesan 
function sendMessage($chat_id, $message) {
    // Membentuk URL untuk endpoint Telegram Bot API sendMessage
    $url = API_URL . "sendMessage?chat_id=$chat_id&text=" . urlencode($message);

    // Mengirim permintaan HTTP GET ke API Telegram
    file_get_contents($url);
}

//fungsi untuk menangani perintah /moban
function handleOrder($text, $chat_id, $message_id, $user_id, $username) {
    global $pdo;

    // Ambil daftar kategori dari database
    $stmt = $pdo->query("SELECT regex_pattern FROM kategori LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $kategori_regex = $row['regex_pattern']; // Contoh: "INDIHOME|INDIBIZ|Wifiid|Astinet|Metro|VPNIP|WMS|OLO"

    // Perbaikan regex agar sesuai format dengan kategori yang diambil dari database
    $pattern = "/^\/moban #($kategori_regex) #([A-Z0-9]+) #([A-Za-z0-9]+) #([\s\S]+)/i";
    preg_match($pattern, $text, $matches);

    // Perbaikan regex agar sesuai format(format lama langsung input disini)
    // preg_match("/^\/moban #(INDIHOME|INDIBIZ|Wifiid|Astinet|Metro|VPNIP|WMS|OLO) #([A-Z0-9]+) #([A-Z0-9]+) #([\s\S]+)/i", $text, $matches);

    //jika salah format maka akan mengirim pesan ini...
    if (count($matches) !== 5) {
        $message = "Format Order Tidak Valid!\n\n";
        $message .= "Pastikan formatnya sesuai dengan contoh berikut:\n";
        $message .= "/moban #Kategori #Transaksi #WONUM #Keterangan\n\n";
        $message .= "Contoh:\n";
        $message .= "/moban #INDIHOME #MO #WO123456789 #Permintaan layanan\n\n";
        $message .= "Untuk informasi lebih lanjut, ketik perintah `/help`.";
        
        //pesan dikirim dalam bentuk reply
        replyMessage($chat_id, $message, $message_id,);
        return;
    }

    //mengekstak data dari hasil regex
    $kategori = strtoupper($matches[1]); // Kategori (INDIHOME, INDIBIZ, DATIN)
    $transaksi = strtoupper($matches[2]); // Transaksi
    $wonum = $matches[3]; // WONUM
    $keterangan = trim($matches[4]); // Keterangan

    // Ambil role user berdasarkan user_id
    $stmt = $pdo->prepare("SELECT Nama, role FROM users WHERE id_telegram = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        replyMessage($chat_id, "User tidak ditemukan dalam database. Daftarkan anda terlebih dahulu", $message_id);
        return;
    }

    // Jika user bukan Teknisi/Plasa, tolak akses...
    if (!in_array($user['role'], ['teknisi', 'plasa'])) {
        replyMessage($chat_id, "Maaf, fitur ini hanya bisa digunakan oleh Teknisi atau Plasa. Hubungi admin untuk mendapatkan akses.", $message_id);
        return;
    }

    $nama = $user['Nama']; // Ambil nama user dari database
    $order_by = strtolower($user['role']); // Role user sebagai order_by

    // Generate nomor tiket
    $no_tiket = generateTicket();

    // Cek apakah Order_ID atau No_Tiket sudah ada. mengindari duplikasi..
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE Order_ID = ? OR No_Tiket = ?");
    $stmtCheck->execute([$wonum, $no_tiket]);
    $exists = $stmtCheck->fetchColumn();
    
    if ($exists > 0) {
        //jika duplikat 
        $stmtGet = $pdo->prepare("SELECT Order_ID, No_Tiket FROM orders WHERE Order_ID = ? OR No_Tiket = ?");
        $stmtGet->execute([$wonum, $no_tiket]);
        $existingOrder = $stmtGet->fetch(PDO::FETCH_ASSOC);
        
        //kirim reply ini...
        replyMessage($chat_id, "Order sudah ada di sistem!\n\n Order_ID: {$existingOrder['Order_ID']}\n No_Tiket: {$existingOrder['No_Tiket']}", $message_id);
        return;
    }
    
    try {
        //mulai transaksi ke database..
        $pdo->beginTransaction();

        // Simpan ke tabel orders (tambahkan group_id)
        $stmt1 = $pdo->prepare("INSERT INTO orders (Order_ID, Transaksi, Kategori, Keterangan, No_Tiket, Status, id_telegram, username_telegram, order_by, group_id) 
        VALUES (?, ?, ?, ?, ?, 'Order', ?, ?, ?, ?)");
        $stmt1->execute([$wonum, $transaksi, $kategori, $keterangan, $no_tiket, $user_id, $username, $order_by, $chat_id]); // <-- $chat_id adalah group_id

        // Simpan ke tabel order_messages
        $stmt2 = $pdo->prepare("INSERT INTO order_messages (no_tiket, message_id, group_id) VALUES (?, ?, ?)");
        $stmt2->execute([$no_tiket, $message_id, $chat_id]); // <-- Simpan group_id

        $pdo->commit();

        // Balas pesan di grup asal
        replyMessage($chat_id, "Hallo $nama. Permintaan Anda $wonum $transaksi $kategori dengan no tiket $no_tiket sedang kami check, silahkan tunggu.", $message_id);
    } catch (Exception $e) {
        //rollback jika transaksi gagal
        $pdo->rollBack();
        replyMessage($chat_id, "Terjadi kesalahan saat menyimpan order. Coba lagi nanti.\n\nError: " . $e->getMessage(), $message_id);
    }
}


//mengirim pesan berupa reply ke user
function replyMessage($chat_id, $message, $reply_to_message_id) {
    // Membentuk URL untuk mengirim balasan pesan ke Telegram Bot API
    $url = API_URL . "sendMessage?chat_id=$chat_id&text=" . urlencode($message) . "&reply_to_message_id=$reply_to_message_id";
    // Mengirim permintaan HTTP GET ke API Telegram
    file_get_contents($url);
}

//fungsi mengirim notifikasi jika terjadi perubahan data pada database
function sendNotifications() {
    global $pdo;

    // Ambil notifikasi yang statusnya Pickup atau Close dan belum terkirim (is_sent = 0)
    $stmt = $pdo->query("
        SELECT lo.*, o.group_id 
        FROM log_orders lo
        JOIN orders o ON lo.No_Tiket = o.No_Tiket
        WHERE (lo.status = 'Pickup' OR lo.status = 'Close') 
        AND lo.is_sent = 0
    ");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($notifications as $notification) {
        $chat_id = $notification['group_id']; // Ambil group_id dari order
        $no_tiket = $notification['No_Tiket']; 
        $order_id = $notification['order_id'];
        $transaksi = $notification['transaksi'];
        $status = $notification['status'];
        $progress_order = $notification['progress_order'];
        $keterangan = !empty($notification['keterangan']) ? $notification['keterangan'] : "-"; // Jika NULL atau kosong, ubah jadi "-"
        $nama = $notification['nama']; // Nama yang menangani order
        $order_by = $notification['order_by']; // Teknisi atau Plasa

        // Cek apakah chat_id valid sebelum lanjut
        if (empty($chat_id) || $chat_id == 0) {
            error_log("Lewati notifikasi karena tidak ada message_id untuk No Tiket: $no_tiket");

            //  Tetap update is_sent agar tidak diulang di pemanggilan berikutnya
            $stmtUpdate = $pdo->prepare("UPDATE log_orders SET is_sent = 1 WHERE no = ?");
            $stmtUpdate->execute([$notification['no']]);

            continue; // Lewati iterasi ini, tidak kirim pesan
        }

        // Format pesan berdasarkan status
        if ($status === 'Pickup') {
            if ($progress_order === 'On Eskalasi') {
                $message = " Order Pending\n\n No Tiket: $no_tiket\n Order ID: $order_id\n Transaksi: $transaksi\n Progress: $progress_order\n Ditangani oleh: $nama\n Keterangan: $keterangan\n Source: $order_by";
            } elseif ($progress_order === 'In Progress') {
                $message = " Order Proses\n\n No Tiket: $no_tiket\n Order ID: $order_id\n Transaksi: $transaksi\n Progress: $progress_order\n Ditangani oleh: $nama\n Keterangan: $keterangan\n Source: $order_by";
            }
        } elseif ($status === 'Close') {
            if ($progress_order === 'Cancel') {
                $message = " Order Cancelled\n\n No Tiket: $no_tiket\n Order ID: $order_id\n Transaksi: $transaksi\n Progress Terakhir: $progress_order\n Ditangani oleh: $nama\n Keterangan: $keterangan\n Source: $order_by";
            } elseif (in_array($progress_order, ['Sudah PS', 'CAINPUL'])) {
                $message = " Order Selesai\n\n No Tiket: $no_tiket\n Order ID: $order_id\n Transaksi: $transaksi\n Progress Terakhir: $progress_order\n Ditangani oleh: $nama\n Keterangan: $keterangan\n Source: $order_by";
            }
        }
        

        // Ambil message_id dari order_messages yang sesuai dengan grup
        $stmtMessage = $pdo->prepare("
        SELECT message_id 
        FROM order_messages 
        WHERE no_tiket = ? AND group_id = ?
        ORDER BY id ASC 
        LIMIT 1
        ");
        $stmtMessage->execute([$no_tiket, $chat_id]);
        $orderMessage = $stmtMessage->fetch(PDO::FETCH_ASSOC);


        // Kirim pesan dengan atau tanpa reply
        if ($orderMessage) {
            replyMessage($chat_id, $message, $orderMessage['message_id']);
        } else {
            // Jika tidak ada message_id, lewati pengiriman
            error_log("Lewati notifikasi karena tidak ada message_id untuk No Tiket: $no_tiket");
        }

        // Update status is_sent menjadi 1 agar tidak dikirim ulang
        $stmtUpdate = $pdo->prepare("UPDATE log_orders SET is_sent = 1 WHERE no = ?");
        $stmtUpdate->execute([$notification['no']]);
    }
}

//fungsi menangani perintah /bantu assurance
function handleOrderAssurance($text, $chat_id, $message_id, $user_id, $username) {
    global $pdo;

    // [1] REGEX UNTUK PARSE INPUT
    $pattern = "/^\/bantu #([a-zA-Z0-9_-]+) #([A-Z0-9]+) #(.+)/i";
    preg_match($pattern, $text, $matches);

    // [2] VALIDASI FORMAT
    if (count($matches) !== 4) {
        $message = "Format Order Tidak Valid!\n\n";
        $message .= "Pastikan formatnya sesuai dengan contoh berikut:\n";
        $message .= "/bantu #INCIDEN #PERMINTAAN #Keterangan\n\n";
        $message .= "Contoh:\n";
        $message .= "/bantu #INC12121 #INTERNETERROR #mohon bantuan internet error\n\n";
        $message .= "Mohon menggunakan huruf kapital untuk permintaan dan jangan ada spasi \n";
        $message .= "Untuk informasi lebih lanjut, ketik perintah `/assurance`";
    
        replyMessage($chat_id, $message, $message_id);
        return;
    }

    // [3] NORMALISASI INPUT
    $incident = strtoupper(trim($matches[1]));
    $permintaan = strtoupper(trim($matches[2]));
    $keterangan = trim($matches[3]);

    // [4] VALIDASI ENUM PERMINTAAN
    $allowedRequests = ['SENDMYI','CEKPASSWORDWIFI','CEKREDAMAN','INTERNETERROR',
                        'GANTIONT','GANTISTB','OMSET','VOIPERROR','USERERROR'];
    if (!in_array($permintaan, $allowedRequests)) {
        replyMessage($chat_id, "PERMINTAAN TIDAK VALID!\n\nGunakan opsi yang tersedia.", $message_id);
        return;
    }

    // [5] VALIDASI USER
    $stmt = $pdo->prepare("SELECT Nama, role FROM users WHERE ID_Telegram = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        replyMessage($chat_id, "User tidak terdaftar. Gunakan /daftar di chat private bot.", $message_id);
        return;
    }
    
    if (!in_array(strtolower($user['role']), ['teknisi', 'plasa'])) {
        replyMessage($chat_id, "Akses ditolak! Hanya untuk Teknisi/Plasa.", $message_id);
        return;
    }

    // [6] GENERATE TIKET & DATA
    $noTiket = generateTicket();
    $nama = $user['Nama'];
    $roleUser = ucfirst($user['role']);

    // [7] CEK DUPLIKASI
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ass_orders WHERE Order_ID = ? OR No_Tiket = ?");
    $stmtCheck->execute([$incident, $noTiket]);
    
    if ($stmtCheck->fetchColumn() > 0) {
        $stmtGet = $pdo->prepare("SELECT Order_ID, No_Tiket FROM ass_orders WHERE Order_ID = ? OR No_Tiket = ?");
        $stmtGet->execute([$incident, $noTiket]);
        $existing = $stmtGet->fetch();
        
        replyMessage($chat_id, "Order sudah ada!\nIncident: {$existing['Order_ID']}\nTiket: {$existing['No_Tiket']}", $message_id);
        return;
    }

    // [8] PROSES DATABASE
    try {
        $pdo->beginTransaction();

        // Insert ke ass_orders
        $stmtOrder = $pdo->prepare("
            INSERT INTO ass_orders (
                Order_ID, Permintaan, Kategori, Keterangan, No_Tiket,
                Status, progress_order, id_telegram, username_telegram, order_by, group_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmtOrder->execute([
            $incident,
            $permintaan,
            'indihome',         // Kategori tetap
            $keterangan,
            $noTiket,
            'Order',            // Status
            'On Check',         // Progress
            $user_id,
            $username,
            $roleUser,
            $chat_id
        ]);

        // Insert ke order_messages
        $stmtMessage = $pdo->prepare("
            INSERT INTO order_messages (no_tiket, message_id, group_id)
            VALUES (?, ?, ?)
        ");
        $stmtMessage->execute([$noTiket, $message_id, $chat_id]);

        $pdo->commit();

        /* [9] RESPON SUKSES - MIRIP handleOrder */
        replyMessage(
            $chat_id,
            "Hallo $nama. Permintaan Anda $incident $permintaan dengan no tiket $noTiket sedang kami check, silahkan tunggu.",
            $message_id
        );

    } catch (PDOException $e) {
        $pdo->rollBack();
        
        /* [10] RESPON ERROR - MIRIP handleOrder */
        replyMessage(
            $chat_id,
            "Terjadi kesalahan saat menyimpan order. Coba lagi nanti.\n\nError: " . $e->getMessage(),
            $message_id
        );
    }
}

//fungsi cek format assurance
function sendHelpMessageAssurance($chat_id, $message_id) {
    //informasi yang akan ditampilkan
    $helpMessage = "Panduan Penggunaan Perintah `/bantu`\n\n"
                . "Gunakan format berikut:\n"
                . "/moban #INCIDEN #PERMINTAAN #Keterangan\n"
                . "\nPermintaan:"
                . "\n- SENDMYI"
                . "\n- CEKPASSWORDWIFI"
                . "\n- CEKREDAMAN"
                . "\n- INTERNETERROR"
                . "\n- GANTIONT"
                . "\n- GANTISTB"
                . "\n- OMSET"
                . "\n- VOIPERROR"
                . "\n- USERERROR"
                . "\n\n Untuk informasi lebih lanjut, hubungi admin.
                
                \nPerintah untuk cek status order:\n/cek [Inciden]\nContoh: /cek INC2121313";

    //mengirim jawaban berupa reply
    replyMessage($chat_id, $helpMessage, $message_id);
}

//fungsi membuat tiket random
function generateTicket() {
    global $pdo;
    do {
        // -8 adalah berarti yang diambil 8 angka
        $no_tiket = 'TKT' . strtoupper(substr(uniqid(rand(), true), -8));

        // Menggunakan prepared statement untuk keamanan
        $query = "SELECT COUNT(*) FROM orders WHERE No_Tiket = :no_tiket";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':no_tiket', $no_tiket, PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetchColumn();
    } while ($count > 0); // Jika sudah ada di DB, ulangi generate

    return $no_tiket;
}


?>
