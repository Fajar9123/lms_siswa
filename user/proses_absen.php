<?php
// Pastikan file koneksi sudah disertakan
include 'koneksi.php';

// Atur header untuk merespons dalam format JSON
header('Content-Type: application/json');

// Pastikan permintaan adalah metode POST dan data tidak kosong
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari body permintaan JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validasi data
    if (isset($data['nis_siswa'], $data['id_jadwal'])) {
        $nis_siswa = $data['nis_siswa'];
        $id_jadwal = $data['id_jadwal'];
        $status = "Hadir";
        $tanggal = date("Y-m-d");

        // Periksa apakah siswa sudah absen hari ini untuk jadwal ini
        $check_sql = "SELECT id FROM absensiswa WHERE nis_siswa = ? AND id_jadwal = ? AND tanggal = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("sis", $nis_siswa, $id_jadwal, $tanggal);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Anda sudah melakukan absensi hari ini.']);
            $check_stmt->close();
            $conn->close();
            exit();
        }
        $check_stmt->close();

        // Siapkan pernyataan SQL untuk insert data
        $sql = "INSERT INTO absensiswa (nis_siswa, id_jadwal, tanggal, status) VALUES (?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("siss", $nis_siswa, $id_jadwal, $tanggal, $status);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Absensi berhasil direkam!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan absensi: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan pernyataan: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metode permintaan tidak valid.']);
}

$conn->close();
?>