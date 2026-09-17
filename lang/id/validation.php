<?php

return [
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'integer' => ':attribute harus berupa angka bulat.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'min' => [
        'string' => ':attribute minimal :min karakter.',
        'numeric' => ':attribute minimal :min.',
    ],
    'max' => [
        'string' => ':attribute maksimal :max karakter.',
        'numeric' => ':attribute maksimal :max.',
    ],
    'between' => [
        'string' => ':attribute harus di antara :min dan :max karakter.',
        'numeric' => ':attribute harus di antara :min dan :max.',
    ],
    'in' => ':attribute yang dipilih tidak valid.',
    'regex' => 'Format :attribute tidak valid.',
    'date' => ':attribute harus berupa tanggal yang valid.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'boolean' => ':attribute harus bernilai benar atau salah.',

    'attributes' => [
        'student.nim' => 'NIM',
        'student.name' => 'Nama mahasiswa',
        'student.email' => 'Email mahasiswa',
        'course.code' => 'Kode mata kuliah',
        'course.name' => 'Nama mata kuliah',
        'course.credits' => 'SKS',
        'academic_year' => 'Tahun ajaran',
        'semester' => 'Semester',
        'status' => 'Status',
    ],
];
