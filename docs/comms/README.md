# Protokol Komunikasi Antar-Agent

Folder ini adalah "papan pesan" bersama untuk agent AI (dan manusia) milik Orang A, B, dan C. Tujuannya: setiap agent tahu apa yang sudah dikerjakan agent lain, kontrak apa yang tersedia, dan apa yang sedang ditunggu, **tanpa** harus bertanya lewat chat.

## File
| File | Penulis (satu-satunya) | Isi |
|---|---|---|
| `A.md` | Orang A — Backend Inti & Infra | status, handoff, permintaan dari A |
| `B.md` | Orang B — AI Pipeline | status, handoff, permintaan dari B |
| `C.md` | Orang C — Frontend & UX | status, handoff, permintaan dari C |

**Aturan inti: tulis hanya di file milikmu, baca semua file.** Dengan begitu tidak ada konflik merge di folder ini.

## Wajib bagi setiap agent
1. **Awal sesi:** baca `docs/comms/A.md`, `B.md`, `C.md` setelah membaca `CLAUDE.md` dan `docs/TEAM.md`. Cari bagian **Permintaan** yang ditujukan ke kamu (`→ A`, `→ B`, `→ C`) dan **Handoff** yang relevan dengan task-mu.
2. **Akhir sesi (sebelum PR):** perbarui file milikmu:
   - pindahkan task di **Status** (Sedang dikerjakan → Selesai),
   - tambahkan entri **Handoff** jika ada hal yang dipakai orang lain (endpoint, tabel, config, env, class publik),
   - tambahkan atau tutup **Permintaan**.
3. **Jangan mengedit file orang lain**, termasuk untuk menjawab. Jawab permintaan di file milikmu dengan merujuk ID-nya (mis. "Jawab `REQ-C-001`: ...").
4. **Jangan mengarang** status orang lain. Jika informasi belum ada di file mereka, anggap belum tersedia dan pakai mock / tanyakan ke manusia.
5. File ini ikut ke `main` lewat PR task seperti biasa. Setelah merge, agent lain mendapatkannya lewat `git pull`.

## Format entri

### Status
```
- [x] T0.1 backend — selesai (branch feat/T0.1-scaffold-backend, PR #?)
- [ ] T0.2 config skillbridge — sedang dikerjakan
```

### Handoff (informasi untuk agent lain, tambah di atas = terbaru)
```
#### HO-A-001 · 2026-11-10 · T0.1 · untuk: B, C
Ringkas apa yang tersedia dan cara memakainya. Rujuk path file / endpoint.
Breaking change? ya/tidak.
```

### Permintaan (butuh sesuatu dari orang lain)
```
#### REQ-A-001 · 2026-11-10 · → B · status: open | answered | closed
Apa yang dibutuhkan, kenapa, dan kapan (merge point #?).
```
Penerima menjawab di file miliknya: `Jawab REQ-A-001: ...`. Pembuat permintaan yang mengubah status menjadi `closed`.

## Penomoran
`HO-<penulis>-<nnn>` dan `REQ-<penulis>-<nnn>`, berurutan per penulis, tidak pernah dipakai ulang.

## Yang tidak ditulis di sini
- Secret, API key, isi `.env`.
- Kontrak API lengkap → tetap di `docs/API.md`; skema → `docs/DATABASE.md`. Di sini cukup rujukannya.
- Diskusi panjang → cukup ringkasan keputusan.
