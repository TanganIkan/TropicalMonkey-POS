import { Notyf } from "notyf";
import "notyf/notyf.min.css";
import Chart from "chart.js/auto";

window.Chart = Chart;

// ===== Tema terang / gelap =====
// Pilihan disimpan di localStorage: 'light' | 'dark' | 'system' (ikuti pengaturan perangkat)

const THEME_KEY = "theme";
const systemDark = window.matchMedia("(prefers-color-scheme: dark)");

window.getTheme = () => {
    try {
        return localStorage.getItem(THEME_KEY) || "system";
    } catch {
        return "system";
    }
};

window.applyTheme = (mode = window.getTheme()) => {
    const dark = mode === "dark" || (mode === "system" && systemDark.matches);
    document.documentElement.classList.toggle("dark", dark);
    window.dispatchEvent(new CustomEvent("theme-changed", { detail: { mode, dark } }));
};

window.setTheme = (mode) => {
    try {
        localStorage.setItem(THEME_KEY, mode);
    } catch {}
    window.applyTheme(mode);
};

// Warna dari variabel tema, untuk komponen non-CSS (mis. Chart.js)
window.themeColor = (name, alpha = 1) => {
    const rgb = getComputedStyle(document.documentElement).getPropertyValue(`--${name}`).trim().split(/\s+/).join(",");
    return `rgba(${rgb},${alpha})`;
};

systemDark.addEventListener("change", () => window.getTheme() === "system" && window.applyTheme());
document.addEventListener("livewire:navigated", () => window.applyTheme());

// ===== Toast (Notyf) =====
// Server mengirim: $this->dispatch('notify', ['title' => ..., 'text' => ..., 'icon' => success|error|warning|info])

const ICONS = {
    success: "M5 13l4 4L19 7",
    error: "M6 18L18 6M6 6l12 12",
    warning: "M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z",
    info: "M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z",
};

const makeNotyf = () =>
    new Notyf({
        duration: 4500,
        dismissible: true,
        position: { x: "right", y: "top" },
        // Warna & ikon diatur lewat CSS (.toast-*) di app.css
        types: Object.entries({ success: "#ffffff", error: "#ffffff", warning: "#fbbf24", info: "#0a0a0a" }).map(([type, color]) => ({
            type,
            className: `toast-${type}`,
            background: "transparent",
            icon: { className: `toast-icon toast-icon-${type}`, tagName: "span", text: "", color },
        })),
    });

let notyf = null;
const toaster = () => {
    // wire:navigate mengganti isi <body>, jadi container lama bisa hilang
    if (!notyf || !document.body.contains(document.querySelector(".notyf"))) {
        notyf = makeNotyf();
    }
    return notyf;
};

const escapeHtml = (value) =>
    String(value ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

window.notify = function ({ title = "", text = "", icon = "success" } = {}) {
    const type = ["success", "error", "warning", "info"].includes(icon) ? icon : "info";
    const message = (title ? `<strong>${escapeHtml(title)}</strong>` : "") + escapeHtml(text);

    toaster().open({ type, message, duration: type === "error" || type === "warning" ? 6500 : 4500 });
};

window.addEventListener("notify", (event) => {
    const data = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    window.notify(data ?? {});
});

// ===== Dialog konfirmasi (bento) =====

window.confirmDialog = function ({ title, text = "", confirmText = "Ya, Lanjutkan", cancelText = "Batal", danger = false } = {}) {
    return new Promise((resolve) => {
        const overlay = document.createElement("div");
        overlay.className = "fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-3 sm:p-4 bg-black/40 backdrop-blur-sm opacity-0 transition-opacity duration-150";
        overlay.innerHTML = `
            <div role="dialog" aria-modal="true" class="w-full max-w-sm bg-white rounded-3xl p-6 shadow-lift translate-y-4 transition-transform duration-200">
                <div class="w-11 h-11 rounded-2xl flex items-center justify-center mb-4 ${danger ? "bg-red-50 text-red-600" : "bg-zinc-100 text-zinc-900"}">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="${danger ? ICONS.warning : ICONS.info}"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-zinc-900">${escapeHtml(title)}</h3>
                ${text ? `<p class="text-sm text-zinc-500 mt-1.5">${escapeHtml(text)}</p>` : ""}
                <div class="grid grid-cols-2 gap-2 mt-6">
                    <button type="button" data-cancel class="btn-secondary">${escapeHtml(cancelText)}</button>
                    <button type="button" data-confirm class="${danger ? "btn bg-red-600 text-snow hover:bg-red-700" : "btn-primary"}">${escapeHtml(confirmText)}</button>
                </div>
            </div>`;

        const panel = overlay.firstElementChild;
        const close = (result) => {
            overlay.classList.add("opacity-0");
            document.removeEventListener("keydown", onKey);
            setTimeout(() => overlay.remove(), 150);
            resolve(result);
        };
        const onKey = (e) => {
            if (e.key === "Escape") close(false);
            if (e.key === "Enter") close(true);
        };

        overlay.addEventListener("click", (e) => e.target === overlay && close(false));
        overlay.querySelector("[data-cancel]").addEventListener("click", () => close(false));
        overlay.querySelector("[data-confirm]").addEventListener("click", () => close(true));
        document.addEventListener("keydown", onKey);

        document.body.appendChild(overlay);
        requestAnimationFrame(() => {
            overlay.classList.remove("opacity-0");
            panel.classList.remove("translate-y-4");
        });
        overlay.querySelector("[data-confirm]").focus();
    });
};

// Fungsi global untuk konfirmasi aksi umum (non-hapus)
window.confirmAction = function (onConfirm, title, text, confirmText = "Ya, Lanjutkan") {
    window.confirmDialog({ title, text, confirmText }).then((ok) => ok && onConfirm());
};

// Fungsi global untuk konfirmasi hapus
window.confirmDeletion = function (onConfirm, itemName = "Data ini") {
    window
        .confirmDialog({ title: "Yakin ingin menghapus?", text: `${itemName} akan dihapus secara permanen dari sistem.`, confirmText: "Ya, Hapus", danger: true })
        .then((ok) => ok && onConfirm());
};

// Fungsi global untuk konfirmasi checkout/pembayaran
window.confirmCheckout = function (onConfirm) {
    window
        .confirmDialog({ title: "Selesaikan transaksi?", text: "Pastikan nominal dan metode pembayaran sudah sesuai.", confirmText: "Ya, Bayar", cancelText: "Cek Lagi" })
        .then((ok) => ok && onConfirm());
};

// Fungsi global untuk konfirmasi logout
window.confirmLogout = function (onConfirm) {
    window
        .confirmDialog({ title: "Keluar dari akun?", text: "Shift Anda akan ditutup otomatis.", confirmText: "Ya, Logout", danger: true })
        .then((ok) => ok && onConfirm());
};

// Info lupa password
window.showForgotPassword = function () {
    window.notify({ title: "Lupa password?", text: "Hubungi Owner atau Administrator toko untuk reset password akun Anda.", icon: "info" });
};
