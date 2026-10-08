# Cetak Nota ke Printer Thermal Bluetooth (ESC/POS)

Dokumen ini menjelaskan cara mencetak nota langsung ke printer thermal — tanpa PDF,
tanpa dialog cetak peramban — memakai **Web Bluetooth**.

Contoh perangkat yang sudah diuji desainnya: **CodeSoft HP-M200** (58mm, Bluetooth).

## Tiga jalur cetak yang tersedia di aplikasi

| Jalur | Cara kerja | Dipakai dari | Syarat |
|---|---|---|---|
| **1. Thermal Bluetooth** (utama) | Aplikasi mengirim perintah ESC/POS langsung ke printer lewat BLE | Tombol **Cetak Thermal** di Kasir & Form SA | Chrome/Edge (Android atau desktop), HTTPS, printer BLE |
| **2. Cetak 58mm lewat dialog** | Halaman `/cetak/nota-thermal/:id` (lebar 58mm, monospace) → dialog cetak peramban | Tombol **Cetak 58mm** di Kasir | Printer terpasang sebagai printer di perangkat + driver/ukuran kertas 58mm |
| **3. A4 / PDF** | Halaman `/cetak/nota/:id` atau Pratinjau PDF | Form SA, Kasir, Laporan | Apa saja (jalur paling aman) |

Jalur 1 dipakai kasir sehari-hari karena **satu klik** dan tidak ada pilihan kertas/margin.
Jalur 2 adalah cadangan — misalnya bila perangkat kasir ternyata iPad/iOS, yang **tidak
mendukung Web Bluetooth sama sekali**. Jalur 3 untuk invoice resmi A4.

## Menyiapkan printer (sekali saja)

1. Nyalakan printer, aktifkan Bluetooth (biasanya tombol tahan sampai lampu berkedip).
2. Di aplikasi: **Pengaturan → Printer Thermal → Pilih Printer**.
3. Pada daftar yang muncul, pilih **HP-M200**.
4. Tekan **Cetak Uji**. Kalau teks tercetak rapi dan kertas terpotong, selesai.

Setelah itu tombol **Cetak Thermal** di Kasir langsung mencetak, dan pada modal pembayaran
ada opsi **cetak nota otomatis setelah pembayaran** (aktif bila printer tersambung).

## Syarat yang tidak bisa ditawar

| Syarat | Keterangan |
|---|---|
| Peramban **Chrome/Edge** | Safari (termasuk iPad/iPhone) dan Firefox tidak punya Web Bluetooth |
| **HTTPS** untuk halaman aplikasi | Web Bluetooth hanya berjalan pada HTTPS atau `localhost` |
| Printer harus **BLE (GATT)** | Printer Bluetooth Classic/SPP saja tidak akan muncul di daftar |
| Kertas **58mm** | 32 karakter per baris — encoder sudah membungkus teks agar pas |
| Halaman harus **tetap terbuka** | Web Bluetooth tidak berjalan di latar belakang; jangan tutup tab saat mencetak |

## Yang sudah terbukti teruji (tanpa printer fisik)

| Pemeriksaan | Cara | Hasil |
|---|---|---|
| Byte ESC/POS benar | `node tools/uji-escpos.mjs` | 38 pemeriksaan lulus: `ESC @` di awal, `GS V 66 0` (potong) di akhir, tebal & huruf ganda, transliterasi karakter Indonesia |
| Tidak ada baris melebihi 32 kolom | uji di atas + uji di peramban | baris terpanjang **tepat 32** |
| Nomor SA & label tidak terpotong | uji di atas | "No. SA-202610-0004" utuh; "Pelanggan" tidak jadi "Pelan" |
| Penyambungan & pengiriman byte | printer tiruan (fake GATT) di peramban | 334 byte (slip uji) & 767 byte (nota) diterima "printer", diawali `ESC @`, diakhiri perintah potong |

Yang **belum** teruji dan hanya bisa dibuktikan di bengkel: hasil cetak fisik pada HP-M200,
karena perangkatnya tidak ada di mesin pengembang.

## Bila nota tidak tercetak — langkah diagnosis

| Gejala | Kemungkinan penyebab | Tindakan |
|---|---|---|
| Tombol Pilih Printer tidak menemukan printer | Printer hanya Bluetooth Classic (SPP), atau printer dipakai aplikasi lain | Lepaskan koneksi di aplikasi lain, matikan-nyalakan printer, coba lagi |
| Muncul pesan "tidak ditemukan karakteristik yang bisa ditulis" | Profil GATT printer belum dikenal | Buka **Pengaturan → Printer Thermal**, salin tabel **Diagnostik GATT**, kirimkan ke pengembang |
| Teks tercetak tapi berisi karakter aneh | Halaman kode printer bukan CP437 | Kirim contoh hasil cetak — encoder perlu penyesuaian halaman kode |
| Teks terpotong di kanan | Lebar kertas bukan 58mm | Pastikan kertas 58mm; untuk 80mm bisa ditambahkan lebar 48 kolom |
| Muncul pesan "peramban tidak mendukung Web Bluetooth" | Dibuka di Safari/Firefox, atau bukan HTTPS | Pakai Chrome/Edge dan pastikan alamat berawalan `https://` |
| Cetak gagal setelah beberapa transaksi | Koneksi BLE terputus | Tekan **Sambungkan Ulang** di Pengaturan → Printer Thermal |

## Catatan teknis untuk pengembang

- Encoder: `frontend/src/utils/escpos.js` (murni, tanpa dependensi — mudah diuji).
- Susunan nota: `frontend/src/utils/notaThermal.js`.
- Manajer Web Bluetooth: `frontend/src/composables/usePrinterThermal.js`.
- UI: `frontend/src/components/PrinterThermalPanel.vue` & `TombolCetakThermal.vue`.
- Uji encoder: `cd frontend && node tools/uji-escpos.mjs`.

Poin penting yang sudah membayangi kegagalan (jangan diubah tanpa alasan):

1. **`requestDevice()` wajib dipanggil langsung dari gestur klik.** Karena itu tombol
   "Cetak Thermal" tidak mencoba menyambung sendiri; ia membuka modal berisi tombol
   "Pilih Printer" agar gesturnya segar.
2. **Byte dikirim bertahap** (100 byte bila `writeWithoutResponse`, 20 byte bila hanya
   `write`) dengan jeda 12–25 ms, karena BLE memakai paket kecil.
3. **Label tidak boleh dipotong** — `kolomNota()` di `escpos.js` memindahkan nilai panjang
   ke baris berikutnya alih-alih memangkas label.
4. **Teks ditransliterasi ke ASCII** (CP437) sebelum dikirim: "—", "×", "·" akan tercetak
   sebagai sampah bila tidak diganti.
