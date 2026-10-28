import { toCanvas } from "html-to-image";

// ===== Print langsung ke printer thermal (ESC/POS) dari Chrome =====
// Tanpa aplikasi pihak ketiga: Chrome terhubung ke printer lewat WebUSB (kabel OTG) atau Web Bluetooth (BLE).
// Struk dirender menjadi gambar lalu dikirim sebagai raster, jadi tampilannya sama persis dengan struk di layar.
// Koneksi & lebar kertas disimpan per perangkat (localStorage), bukan di server.

const MODE_KEY = "printer_mode";
const PAPER_KEY = "printer_paper";
const BT_ID_KEY = "printer_bt_id";

// Lebar area cetak dalam titik (203 dpi)
const PAPER_DOTS = { 58: 384, 80: 576 };

// Service BLE yang umum dipakai printer thermal (Xprinter & sejenisnya)
const BT_SERVICES = [
    "000018f0-0000-1000-8000-00805f9b34fb",
    "e7810a71-73ae-499d-8c15-faa9aef0c3f2",
    "49535343-fe7d-4ae5-8fa9-9fafd205e455",
    "0000ff00-0000-1000-8000-00805f9b34fb",
    "0000ffe0-0000-1000-8000-00805f9b34fb",
    "0000fee7-0000-1000-8000-00805f9b34fb",
];

const store = {
    get(key, fallback = null) {
        try {
            return localStorage.getItem(key) ?? fallback;
        } catch {
            return fallback;
        }
    },
    set(key, value) {
        try {
            value === null ? localStorage.removeItem(key) : localStorage.setItem(key, value);
        } catch {}
    },
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ----- Transport USB -----

class UsbTransport {
    constructor(device) {
        this.device = device;
        this.endpoint = null;
        this.type = "usb";
    }

    get name() {
        return this.device.productName || "Printer USB";
    }

    async open() {
        const d = this.device;
        if (!d.opened) await d.open();
        if (d.configuration === null) await d.selectConfiguration(1);

        // Cari interface dengan endpoint bulk OUT, utamakan kelas printer (7)
        const candidates = [];
        for (const iface of d.configuration.interfaces) {
            for (const alt of iface.alternates) {
                const out = alt.endpoints.find((e) => e.direction === "out" && e.type === "bulk");
                if (out) candidates.push({ iface, alt, out });
            }
        }
        candidates.sort((a, b) => (b.alt.interfaceClass === 7) - (a.alt.interfaceClass === 7));
        const picked = candidates[0];
        if (!picked) throw new Error("Perangkat USB ini bukan printer.");

        if (!picked.iface.claimed) await d.claimInterface(picked.iface.interfaceNumber);
        if (picked.alt.alternateSetting !== 0) await d.selectAlternateInterface(picked.iface.interfaceNumber, picked.alt.alternateSetting);
        this.endpoint = picked.out.endpointNumber;
    }

    get connected() {
        return this.device.opened && this.endpoint !== null;
    }

    async write(bytes) {
        for (let i = 0; i < bytes.length; i += 4096) {
            const result = await this.device.transferOut(this.endpoint, bytes.subarray(i, i + 4096));
            if (result.status !== "ok") throw new Error("Gagal mengirim data ke printer USB.");
        }
    }

    async close() {
        try {
            await this.device.close();
        } catch {}
    }
}

// ----- Transport Bluetooth (BLE) -----

class BluetoothTransport {
    constructor(device) {
        this.device = device;
        this.characteristic = null;
        this.type = "bluetooth";
    }

    get name() {
        return this.device.name || "Printer Bluetooth";
    }

    async open() {
        const server = this.device.gatt.connected ? this.device.gatt : await this.device.gatt.connect();
        for (const uuid of BT_SERVICES) {
            let service;
            try {
                service = await server.getPrimaryService(uuid);
            } catch {
                continue;
            }
            const chars = await service.getCharacteristics();
            const writable = chars.find((c) => c.properties.writeWithoutResponse) || chars.find((c) => c.properties.write);
            if (writable) {
                this.characteristic = writable;
                return;
            }
        }
        this.device.gatt.disconnect();
        throw new Error("Printer Bluetooth ini tidak dikenali. Gunakan koneksi USB.");
    }

    get connected() {
        return this.device.gatt.connected && this.characteristic !== null;
    }

    async write(bytes) {
        const c = this.characteristic;
        const fast = c.properties.writeWithoutResponse;
        // Paket kecil + jeda agar buffer printer tidak meluap (BLE lebih lambat dari USB)
        for (let i = 0; i < bytes.length; i += 180) {
            const chunk = bytes.subarray(i, i + 180);
            if (fast) {
                await c.writeValueWithoutResponse(chunk);
                await sleep(12);
            } else {
                await c.writeValueWithResponse(chunk);
            }
        }
    }

    async close() {
        try {
            this.device.gatt.disconnect();
        } catch {}
    }
}

// ----- ESC/POS -----

// Gambar (canvas) -> perintah raster GS v 0, dicetak per pita agar tidak membebani buffer printer
function rasterCommands(canvas) {
    const { width, height } = canvas;
    const pixels = canvas.getContext("2d").getImageData(0, 0, width, height).data;
    const bytesPerRow = Math.ceil(width / 8);
    const band = 128;
    const parts = [];

    for (let top = 0; top < height; top += band) {
        const rows = Math.min(band, height - top);
        const header = [0x1d, 0x76, 0x30, 0x00, bytesPerRow & 0xff, bytesPerRow >> 8, rows & 0xff, rows >> 8];
        const data = new Uint8Array(bytesPerRow * rows);
        for (let y = 0; y < rows; y++) {
            for (let x = 0; x < width; x++) {
                const i = ((top + y) * width + x) * 4;
                const luminance = pixels[i] * 0.299 + pixels[i + 1] * 0.587 + pixels[i + 2] * 0.114;
                const alpha = pixels[i + 3];
                if (alpha > 0 && luminance < 160) data[y * bytesPerRow + (x >> 3)] |= 0x80 >> (x & 7);
            }
        }
        parts.push(Uint8Array.from(header), data);
    }
    return parts;
}

function concat(parts) {
    const out = new Uint8Array(parts.reduce((n, p) => n + p.length, 0));
    let offset = 0;
    for (const p of parts) {
        out.set(p, offset);
        offset += p.length;
    }
    return out;
}

function receiptBytes(canvas) {
    return concat([
        Uint8Array.from([0x1b, 0x40]), // inisialisasi
        ...rasterCommands(canvas),
        Uint8Array.from([0x1b, 0x64, 0x04]), // maju 4 baris
        Uint8Array.from([0x1d, 0x56, 0x42, 0x00]), // potong kertas (diabaikan printer tanpa pemotong)
    ]);
}

// Render elemen struk menjadi canvas selebar kertas. Salinan diberi kelas .receipt-raster
// (gaya cetak: huruf besar & garis hitam) dan dipasang di luar layar selama dirender.
async function renderReceipt(element, dots) {
    const clone = element.cloneNode(true);
    clone.removeAttribute("id");
    clone.classList.add("receipt-raster");
    clone.querySelectorAll('[class*="print:hidden"]').forEach((el) => el.remove());

    const holder = document.createElement("div");
    holder.className = "theme-light";
    holder.style.cssText = "position:fixed;left:-10000px;top:0;";
    holder.appendChild(clone);
    document.body.appendChild(holder);

    try {
        return await toCanvas(clone, {
            backgroundColor: "#ffffff",
            pixelRatio: dots / clone.offsetWidth,
            skipFonts: true,
        });
    } finally {
        holder.remove();
    }
}

// ----- Pengelola printer -----

const printer = {
    transport: null,
    queue: Promise.resolve(),

    get usbSupported() {
        return "usb" in navigator;
    },
    get bluetoothSupported() {
        return "bluetooth" in navigator;
    },
    get connected() {
        return !!this.transport?.connected;
    },
    get paper() {
        return store.get(PAPER_KEY, "58") === "80" ? "80" : "58";
    },
    set paper(value) {
        store.set(PAPER_KEY, value === "80" ? "80" : "58");
        this.emit();
    },
    state() {
        return {
            connected: this.connected,
            name: this.connected ? this.transport.name : null,
            type: this.connected ? this.transport.type : store.get(MODE_KEY),
            paper: this.paper,
            usb: this.usbSupported,
            bluetooth: this.bluetoothSupported,
        };
    },
    emit() {
        window.dispatchEvent(new CustomEvent("printer-changed", { detail: this.state() }));
    },

    async use(transport) {
        await transport.open();
        if (this.transport && this.transport !== transport) await this.transport.close();
        this.transport = transport;
        store.set(MODE_KEY, transport.type);
        if (transport.type === "bluetooth") store.set(BT_ID_KEY, transport.device.id);
        this.emit();
        return transport.name;
    },

    // Dipanggil dari tombol (butuh klik pengguna untuk izin pertama kali)
    async connectUsb() {
        const device = await navigator.usb.requestDevice({ filters: [] });
        return this.use(new UsbTransport(device));
    },

    async connectBluetooth() {
        const device = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: BT_SERVICES });
        device.addEventListener("gattserverdisconnected", () => this.emit());
        return this.use(new BluetoothTransport(device));
    },

    async disconnect() {
        await this.transport?.close();
        this.transport = null;
        store.set(MODE_KEY, null);
        store.set(BT_ID_KEY, null);
        this.emit();
    },

    // Sambung ulang tanpa klik: USB selalu bisa (izin tersimpan), Bluetooth hanya jika browser mendukung getDevices()
    async reconnect() {
        if (this.connected) return true;
        const mode = store.get(MODE_KEY);
        try {
            if (mode === "usb" && this.usbSupported) {
                const [device] = await navigator.usb.getDevices();
                if (device) await this.use(new UsbTransport(device));
            } else if (mode === "bluetooth" && this.bluetoothSupported) {
                if (this.transport?.type === "bluetooth") {
                    await this.transport.open();
                } else if (navigator.bluetooth.getDevices) {
                    const device = (await navigator.bluetooth.getDevices()).find((d) => d.id === store.get(BT_ID_KEY));
                    if (device) {
                        device.addEventListener("gattserverdisconnected", () => this.emit());
                        await this.use(new BluetoothTransport(device));
                    }
                }
            }
        } catch {
            // Printer mati / di luar jangkauan: biarkan, nanti kembali ke print browser
        }
        this.emit();
        return this.connected;
    },

    // Kirim berurutan agar dua cetakan tidak bertabrakan
    send(bytes) {
        const job = this.queue.then(() => this.transport.write(bytes));
        this.queue = job.catch(() => {});
        return job;
    },

    async printElement(element) {
        if (!(await this.reconnect())) throw new Error("Printer belum terhubung.");
        const canvas = await renderReceipt(element, PAPER_DOTS[this.paper]);
        await this.send(receiptBytes(canvas));
    },

    async testPrint() {
        if (!(await this.reconnect())) throw new Error("Printer belum terhubung.");
        const el = document.createElement("div");
        el.className = "bg-white text-black font-mono text-center";
        el.innerHTML = `<div class="pb-3 mb-3 border-b border-dashed"><h2 class="font-black uppercase tracking-widest">Tes Print</h2></div>
            <p class="text-xs font-bold"></p><p class="text-xs">${new Date().toLocaleString("id-ID")}</p>
            <p class="text-xs mt-3">Printer siap dipakai.</p>`;
        el.querySelector("p").textContent = `${this.transport.name} · ${this.paper} mm`;
        await this.printElement(el);
    },
};

if (printer.usbSupported) {
    navigator.usb.addEventListener("disconnect", (e) => {
        if (printer.transport?.device === e.device) {
            printer.transport = null;
            printer.emit();
        }
    });
    navigator.usb.addEventListener("connect", () => printer.reconnect());
}
printer.reconnect();

export default printer;
