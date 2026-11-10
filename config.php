<?php
/*
 * config.php - PENGATURAN Obrolan versi BROWSER
 * ---------------------------------------------------------------------------------
 * Edit file ini saja. index.php TIDAK perlu disentuh.
 * Taruh di folder yang SAMA dengan index.php, file api-*.php, dan presence.php.
 *
 * Cara menambah chatroom: salin satu blok [ ... ], di bawah 'rooms', lalu ubah isinya.
 * Cara menghapus chatroom: hapus blok-nya.
 *
 *   chatroom   : nama yang tampil di dropdown                              (wajib)
 *   api        : NAMA FILE api chatroom itu di server, mis. 'api-semua.php' (wajib)
 *                (boleh juga URL penuh seperti di obrolan.cfg aplikasi desktop; yang dipakai path-nya)
 *   background : gambar latar chatroom (opsional). Tulis path RELATIF dari folder ini,
 *                mis. 'background/komputer.jpeg'. File gambarnya harus ikut dicopy ke server.
 *                GIF bergerak juga bisa.
 *   bg_mode    : cover (bawaan) | stretch | tile | center                   (opsional)
 *   bg_warna   : warna latar bila tidak ada gambar, mis. '#ECEFF3'           (opsional)
 *   key        : (jarang perlu) nama kunci data online/centang. Bawaannya nama file api
 *                tanpa .php - SAMA dengan yang dipakai aplikasi desktop, jadi biarkan kosong.
 *
 * Tanda // di depan baris = baris itu diabaikan (komentar).
 */
return [
    'judul'       => 'Obrolan',   // judul tab browser
    'poll_ms'     => 10000,       // seberapa sering cek pesan & online (milidetik, minimal 2000)
    'halaman_cek' => true,        // true = index.php?cek=1 aktif (untuk memeriksa pengaturan). false = matikan.

    'rooms' => [
        [
            'chatroom'   => 'Semua Unit',
            'api'        => 'api-semua.php',
            'background' => 'bg/semua-unit.jpg',
            'bg_mode'    => 'cover',
            'bg_warna'   => '#ECEFF3',
        ],
        [
            'chatroom'   => 'Lantai 1',
            'api'        => 'api-lantai1.php',
            'background' => 'background/background-lantai 1.jpg',
            'bg_mode'    => 'tile',
        ],
        [
            'chatroom'   => 'Perbaikan IT',
            'api'        => 'perbaikan-it.php',
            'background' => 'background/komputer.jpeg',
            'bg_mode'    => 'stretch',
        ],
        [
            'chatroom'   => 'Sopir',
            'api'        => 'sopir.php',
            'background' => 'background/background-sopir.jpg',
            'bg_warna'   => '#DDEBDD',
        ],
        [
            'chatroom'   => 'Manajer Jaga',
            'api'        => 'manajer-jaga.php',
            'background' => 'background/background-manajer jaga.jpg',
        ],

        // CONTOH untuk ngetes kalau di servermu baru ada satu file api.php (data di obrolan.txt):
        // [
        //     'chatroom' => 'Tes',
        //     'api'      => 'api.php',
        // ],
    ],
];
