// import "./bootstrap";
import Swal from "sweetalert2";
import Chart from "chart.js/auto";

window.Swal = Swal;
window.Chart = Chart;

// Listener global untuk alert notifikasi
window.addEventListener("swal", (event) => {
    let data = event.detail[0];

    Swal.fire({
        title: data.title,
        text: data.text,
        icon: data.icon,
        confirmButtonColor: "#111827", // <-- Diubah ke warna primary (Hitam/Gray-900)
        confirmButtonText: "Oke",
        heightAuto: false,
        scrollbarPadding: false,
    });
});

// Fungsi global untuk konfirmasi hapus
window.confirmDeletion = function (onConfirm, itemName = "Data ini") {
    Swal.fire({
        title: "Yakin ingin menghapus?",
        text: `${itemName} akan dihapus secara permanen dari sistem!`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444", // Tetap merah untuk aksi destruktif (hapus)
        cancelButtonColor: "#6b7280", // Warna abu-abu untuk tombol batal
        confirmButtonText: "Ya, hapus!",
        cancelButtonText: "Batal",
        heightAuto: false,
        scrollbarPadding: false,
    }).then((result) => {
        if (result.isConfirmed) {
            onConfirm();
        }
    });
};

// Fungsi global untuk konfirmasi checkout/pembayaran
window.confirmCheckout = function (onConfirm) {
    Swal.fire({
        title: "Selesaikan Transaksi?",
        text: "Pastikan nominal dan metode pembayaran sudah sesuai.",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#111827", // <-- Diubah ke warna primary (Hitam/Gray-900)
        cancelButtonColor: "#6b7280",
        confirmButtonText: "Ya, Bayar Sekarang!",
        cancelButtonText: "Cek Lagi",
        heightAuto: false,
        scrollbarPadding: false,
    }).then((result) => {
        if (result.isConfirmed) {
            onConfirm();
        }
    });
};

// Fungsi global untuk konfirmasi logout
window.confirmLogout = function (onConfirm) {
    Swal.fire({
        title: "Yakin ingin keluar?",
        text: "Sesi kasir Anda akan diakhiri.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#ef4444", // Warna merah untuk aksi logout
        cancelButtonColor: "#6b7280", // Warna abu-abu
        confirmButtonText: "Ya, Logout",
        cancelButtonText: "Batal",
        heightAuto: false,
        scrollbarPadding: false,
    }).then((result) => {
        if (result.isConfirmed) {
            onConfirm(); // Menjalankan fungsi logout jika dikonfirmasi
        }
    });
};
