# Lungs Disease X-Ray Detection

## Deskripsi Proyek

Proyek "Lungs X-Ray Detection System" adalah aplikasi web inovatif yang dirancang untuk membantu dalam deteksi awal penyakit paru-paru melalui analisis citra X-ray dada menggunakan kecerdasan buatan (AI). Aplikasi ini memungkinkan pengguna untuk mengunggah gambar X-ray, yang kemudian akan diproses oleh model AI berbasis Google Vertex AI untuk memberikan diagnosa potensi penyakit seperti Pneumonia, COVID-19, Tuberculosis, atau Fibrosis, beserta tingkat kepercayaan dan penjelasan singkat.

Tujuan utama proyek ini adalah untuk menyediakan alat bantu yang user-friendly dan efisien bagi tenaga medis atau individu untuk mendapatkan analisis awal X-ray paru-paru, mempercepat proses skrining, dan mendukung keputusan diagnostik.


## Fitur Utama

*   **Autentikasi Pengguna:** Sistem pendaftaran dan login yang aman untuk pengguna.
*   **Unggah Citra X-Ray:** Kemampuan untuk mengunggah gambar X-ray dada dalam format umum (JPEG, PNG, JPG).
*   **Analisis AI Real-time:** Integrasi dengan Google Vertex AI untuk menganalisis citra X-ray dan mendeteksi potensi penyakit paru-paru.
*   **Hasil Diagnosa Komprehensif:** Menampilkan diagnosa utama, tingkat kepercayaan (confidence), detail persentase untuk berbagai kondisi (Normal, Pneumonia, COVID-19, Tuberculosis, Fibrosis), dan penjelasan berdasarkan temuan AI.
*   **Riwayat Diagnosa:** Menyimpan dan menampilkan riwayat diagnosa yang telah dilakukan oleh pengguna.
*   **Antarmuka Pengguna yang Responsif:** Desain yang user-friendly dan adaptif untuk berbagai perangkat.

## Teknologi yang Digunakan

*   **Backend:**
    *   PHP 8.x
    *   Laravel Framework 9.x / 10.x
    *   Composer
    *   MySQL
    *   Google Vertex AI (untuk model AI)
    *   Carbon (untuk penanganan tanggal dan waktu)
*   **Frontend:**
    *   HTML5
    *   CSS3
    *   JavaScript
    *   Blade Templating Engine (Laravel)
*   **Deployment (Contoh):**
    *   Nginx / Apache
    *   Docker (opsional)
    *   Google Cloud Platform (untuk hosting Vertex AI dan mungkin aplikasi)



## Penggunaan

1.  **Akses Aplikasi:** Buka browser Anda dan navigasi ke `http://127.0.0.1:8000`.
2.  **Daftar/Login:** Buat akun baru atau masuk menggunakan kredensial yang sudah ada.
3.  **Unggah X-Ray:** Di halaman dashboard, Anda akan menemukan formulir untuk mengunggah gambar X-ray. Pilih file gambar (JPEG, PNG, JPG) dan masukkan nama pasien.
4.  **Lihat Hasil Diagnosa:** Setelah mengunggah, sistem akan memproses gambar dan menampilkan hasil diagnosa dari AI, termasuk penyakit yang diprediksi, tingkat kepercayaan, dan penjelasan.
5.  **Riwayat:** Anda dapat melihat semua riwayat diagnosa yang telah Anda lakukan.

## Kontribusi

Proyek ini merupakan hasil kerja tim.

*   **Peran saya dalam Project ini yaitu :**
    *   **Pengembangan Antarmuka Pengguna (UI):** Bertanggung jawab penuh dalam membangun tampilan aplikasi sesuai dengan desain Figma yang telah disepakati, memastikan konsistensi visual dan fungsionalitas.
    *   **Penanganan Input dan Output Data:** Mengimplementasikan logika frontend untuk menangani input gambar X-ray dari pengguna dan menampilkan hasil diagnosa yang diterima dari backend secara dinamis.
    *   **Desain Pengalaman Pengguna (UX):** Fokus pada penciptaan tampilan yang user-friendly, intuitif, dan mudah diakses, memastikan pengalaman pengguna yang optimal.



