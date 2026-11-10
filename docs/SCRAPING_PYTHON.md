# Rencana Scraper Lowongan TI — Versi Python

Dokumen ini adalah rencana implementasi ulang `php artisan skillbridge:scrape-jobs` (Laravel) dalam **Python**, dengan perilaku yang sama: mengambil lowongan **rumpun TI** dari API publik yang mengizinkan akses otomatis, memetakannya ke **21 profesi**, lalu menulis **file JSON** yang ditinjau manusia sebelum diimpor ke database lewat `php artisan skillbridge:import-jobs`.

> Scraper **tidak menulis ke database**. Output-nya adalah file JSON dengan format yang sama persis seperti yang diterima `skillbridge:import-jobs`.

---

## 1. Prinsip (wajib dipatuhi)

1. **Hanya sumber yang mengizinkan.** Remotive, Arbeitnow, Himalayas, Jobicy (API publik, tanpa key).
   **Tidak boleh**: LinkedIn (`robots.txt` melarang semua akses otomatis), JobStreet (`Disallow: */job/`, `/api/jobsearch/`), atau situs lain yang syaratnya melarang.
2. **Atribusi wajib.** Setiap lowongan menyimpan `source_name` dan `source_url` (link asli, jangan diubah).
3. **Hormati rate limit.** Jeda antar-request sesuai tabel di §3. Jalankan paling sering beberapa kali sehari (Jobicy: maksimal 1×/jam).
4. **Jangan menebak.** Lowongan yang judulnya tidak cocok dengan profesi TI, datanya tidak lengkap, atau duplikat → **dilewati** dan dihitung, bukan dipaksakan.
5. **Data = tidak tepercaya.** Isi lowongan hanya disimpan sebagai teks; tidak dieksekusi atau dipakai sebagai instruksi.
6. **Manusia meninjau** file JSON sebelum impor.

---

## 2. Alur

```
API publik ──> fetch (per sumber, filter kategori TI)
          ──> normalisasi (HTML → teks, trim)
          ──> filter judul non-TI (exclude_title_keywords)
          ──> petakan judul → profesi (career_keywords, first match wins)
          ──> validasi + dedup (by source_url) + limit
          ──> tulis JSON  (storage/app/private/scraped/<source>-<YYYY-MM-DD>.json)
          ──> [manusia meninjau]
          ──> php artisan skillbridge:import-jobs <file.json>  ──> tabel job_postings
```

---

## 3. Sumber & konfigurasi

| Sumber | Endpoint | Parameter | Field yang dibaca | Filter TI di sumber | Jeda | Aturan |
|---|---|---|---|---|---|---|
| **Remotive** | `GET https://remotive.com/api/remote-jobs` | `category=<slug>` (1 request per kategori) | `company_name`, `title`, `description`, `url` | kategori: `software-development`, `data`, `devops`, `qa`, `artificial-intelligence`, `information-technology`, `design` | **35 dtk** (maks 2 req/menit) | Sebut Remotive + link balik; jangan diteruskan ke job board lain |
| **Arbeitnow** | `GET https://www.arbeitnow.com/api/job-board-api` | `page=N` (berhenti bila `links.next` kosong atau `max_pages=10`) | `company_name`, `title`, `description`, `url` | — (hanya filter judul) | 2 dtk | API gratis tanpa key |
| **Himalayas** | `GET https://himalayas.app/jobs/api/search` | `country=Indonesia`, `page=N` (berhenti bila `jobs` kosong atau `max_pages=25`) | `companyName`, `title`, `description`, `applicationLink`, `parentCategories` | `parentCategories` ∩ {`Developer`, `Data Science`, `IT`, `DevOps`, `Engineering`, `Software Engineering`, `Design`, `Cybersecurity`, `QA`, `Research & Development`} | 2 dtk | Link ke Himalayas sebagai sumber; `applicationLink` dipakai apa adanya |
| **Jobicy** | `GET https://jobicy.com/api/jobs` | `count=100`, `industry=<slug>` (1 request per industri), `geo` opsional (mis. `apac`) | `companyName`, `jobTitle`, `jobDescription`, `url` | industri: `engineering`, `data-science`, `admin` (DevOps), `qa-testing`, `cybersecurity`, `technical-support`, `web-app-design` | 5 dtk | Sebut Jobicy; link tetap ke jobicy.com; sinkronisasi maks 1×/jam |

Umum:
- `User-Agent`: `SkillBridgeDatasetBot/1.0 (academic research)` (generik, tanpa email pribadi).
- Timeout: 30 detik.
- HTTP error (4xx/5xx, termasuk 429) → **gagal**, tidak menulis file.

---

## 4. Format output JSON

Path default: `backend/storage/app/private/scraped/<source>-<YYYY-MM-DD>.json` (folder privat, tidak di-commit).

```json
{
  "_note": "Dikumpulkan otomatis dari Arbeitnow pada 2026-11-10. Tinjau sebelum diimpor. Atribusi wajib: source_name + source_url dipertahankan.",
  "jobs": [
    {
      "career": "backend-developer",
      "company": "Acme GmbH",
      "title": "Senior Backend Engineer",
      "description": "About the Role\nWe build REST APIs with Laravel.\n- PostgreSQL\n- Docker",
      "source_name": "Arbeitnow",
      "source_url": "https://www.arbeitnow.com/jobs/companies/acme/senior-backend-engineer-123",
      "collected_at": "2026-11-10",
      "dataset_version": "arbeitnow-2026-11-10"
    }
  ]
}
```

Aturan validasi (harus sama dengan `JobPostingImporter`, kalau tidak impor akan ditolak):

| Field | Aturan |
|---|---|
| `career` | wajib, salah satu slug di §6 |
| `company`, `title` | wajib, string, maks 255 karakter |
| `description` | wajib, teks biasa, dipotong maks **20.000** karakter |
| `source_name` | nama sumber (`Remotive`/`Arbeitnow`/`Himalayas`/`Jobicy`) |
| `source_url` | URL http/https valid, maks 255 karakter, **unik** dalam satu file |
| `collected_at` | `YYYY-MM-DD` (tanggal scrape) |
| `dataset_version` | default `<source>-<YYYY-MM-DD>` |

Tidak diambil: gaji, lokasi, tipe kerja, senioritas, logo, tag, tanggal terbit/kedaluwarsa (tidak ada kolomnya di `job_postings`). `qualifications` tidak diisi (API tidak memisahkannya dari deskripsi).

JSON ditulis dengan `ensure_ascii=False`, `indent=4`, UTF-8.

---

## 5. Normalisasi HTML → teks

Urutan (setara `JobScraper::toText`):
1. Ganti `<br>`, `</p>`, `</li>`, `</h1..6>`, `</div>` → `\n`.
2. Ganti `<li ...>` → `- `.
3. Hapus semua tag, decode entitas HTML (`&nbsp;`, `&amp;`, …).
4. Rapatkan spasi/tab/NBSP berurutan jadi satu spasi.
5. Rapatkan spasi di sekitar baris baru dan baris kosong berurutan jadi satu `\n`.
6. `strip()`.

Contoh: `<h2>Role</h2><p>Build REST APIs&nbsp;with <b>Laravel</b>.</p><ul><li>PostgreSQL</li><li>Docker</li></ul>`
→ `"Role\nBuild REST APIs with Laravel.\n- PostgreSQL\n- Docker"`

---

## 6. Pemetaan judul → profesi

Pencocokan **case-insensitive, utuh per kata**: kata kunci tidak boleh menempel pada huruf/angka lain. Regex Python:
`(?<![^\W_])` + `re.escape(keyword)` + `(?![^\W_])` (setara PHP `(?<![\p{L}\p{N}])…(?![\p{L}\p{N}])`). Jadi `"api"` **tidak** cocok dengan `"Rapid"`, tetapi `.net`, `c#`, `ai/ml` tetap cocok (sudah diuji).

### 6.1 Judul non-TI (dibuang lebih dulu)
```
sales, marketing, recruiter, recruitment, talent acquisition, account executive,
account manager, customer success, customer support, copywriter, content writer,
teacher, tutor, accountant, lawyer, nurse, business developer, business development,
content developer, curriculum developer, mechanical, civil engineer, chemical engineer,
electrical engineer, process engineer, graphic designer, interior designer, fashion
```

### 6.2 Profesi (urutan penting — **first match wins**, spesifik → umum)

| # | Slug | Kata kunci |
|---|---|---|
| 1 | `full-stack-developer` | full stack, full-stack, fullstack |
| 2 | `machine-learning-engineer` | machine learning, ml engineer, mlops, ai engineer, ai/ml, deep learning, computer vision, nlp, llm, genai, generative ai |
| 3 | `data-scientist` | data scientist, data science |
| 4 | `data-engineer` | data engineer, analytics engineer, etl, big data, data platform, data warehouse |
| 5 | `data-analyst` | data analyst, business intelligence, bi analyst, bi developer, analytics analyst, reporting analyst, power bi, tableau |
| 6 | `database-administrator` | database administrator, dba, database engineer, database developer |
| 7 | `cybersecurity-engineer` | security engineer, security analyst, cybersecurity, cyber security, information security, infosec, appsec, application security, penetration tester, pentester, soc analyst, devsecops, security architect |
| 8 | `devops-engineer` | devops, site reliability, sre, platform engineer, release engineer, build engineer, kubernetes |
| 9 | `cloud-engineer` | cloud engineer, cloud architect, cloud developer, aws, azure, gcp, google cloud |
| 10 | `network-engineer` | network engineer, network administrator, network architect, noc engineer, noc analyst |
| 11 | `system-administrator` | system administrator, systems administrator, sysadmin, linux administrator, windows administrator, infrastructure engineer, systems engineer, it infrastructure |
| 12 | `qa-engineer` | qa, quality assurance, test engineer, software tester, tester, sdet, test automation, automation tester, quality engineer |
| 13 | `mobile-developer` | mobile, android, ios, flutter, react native, kotlin, swift, swiftui, xamarin |
| 14 | `game-developer` | game developer, game programmer, game engineer, gameplay, unity, unreal |
| 15 | `embedded-iot-engineer` | embedded, firmware, iot, internet of things, microcontroller |
| 16 | `ui-ux-designer` | ui/ux, ui ux, ux designer, ui designer, ux researcher, product designer, interaction designer, ux/ui, ux |
| 17 | `frontend-developer` | frontend, front-end, front end, react, reactjs, vue, vuejs, angular, nextjs, next.js, ui developer, ui engineer, javascript developer, typescript developer |
| 18 | `backend-developer` | backend, back-end, back end, api, laravel, php, node, nodejs, node.js, golang, go developer, django, rails, ruby, java developer, java engineer, python developer, python engineer, .net, c#, spring boot, elixir, rust |
| 19 | `system-analyst` | system analyst, systems analyst, it business analyst, business systems analyst, it analyst, technical business analyst, erp consultant, sap consultant, functional consultant |
| 20 | `it-support` | it support, helpdesk, help desk, service desk, technical support, desktop support, it technician, support engineer, it specialist, it officer |
| 21 | `software-engineer` | software engineer, software developer, software development engineer, programmer, application developer, web developer, developer, coder, staff engineer, principal engineer, tech lead, engineering manager, solutions architect, software architect |

Slug harus **sama persis** dengan `backend/database/seeders/CareerSeeder.php`. Bila menambah profesi, perbarui keduanya.

Contoh hasil yang diharapkan (jadikan test):

| Judul | Hasil |
|---|---|
| Senior Backend Engineer | `backend-developer` |
| Fullstack Developer (React/Node) | `full-stack-developer` |
| Senior React Native Developer | `mobile-developer` |
| Site Reliability Engineer | `devops-engineer` |
| SOC Analyst L2 | `cybersecurity-engineer` |
| QA Automation Engineer | `qa-engineer` |
| Senior Product Designer | `ui-ux-designer` |
| Senior .NET Developer | `backend-developer` |
| Senior Software Engineer | `software-engineer` |
| Rapid Prototyper | `None` |
| API Sales Manager | `None` (non-TI) |
| Technical Recruiter - Backend | `None` (non-TI) |
| Mechanical Engineer | `None` |

> Catatan regex untuk kata kunci yang diawali/diakhiri simbol (`.net`, `c#`, `ai/ml`, `next.js`): batas kata dicek terhadap **huruf/angka** di sekitarnya, bukan `\b`. `\b` tidak bekerja untuk `.net` / `c#`.

---

## 7. Struktur proyek

```
tools/scraper/
├── README.md
├── requirements.txt        # requests, beautifulsoup4 (opsional), pytest, responses
├── pyproject.toml          # opsional
├── scraper/
│   ├── __init__.py
│   ├── config.py           # §3 & §6 (sumber, kategori, keyword, exclude)
│   ├── models.py           # dataclass ScrapedJob
│   ├── text.py             # html_to_text()
│   ├── classify.py         # career_for(title)
│   ├── collect.py          # collect(source, limit, dataset_version, collected_at)
│   ├── sources/
│   │   ├── base.py         # Protocol JobSource: name, fetch()
│   │   ├── remotive.py
│   │   ├── arbeitnow.py
│   │   ├── himalayas.py
│   │   └── jobicy.py
│   └── cli.py              # python -m scraper <source> --limit --out --dataset-version
└── tests/
    ├── test_text.py
    ├── test_classify.py
    ├── test_sources.py     # HTTP di-mock (responses), tanpa request sungguhan
    └── test_cli.py
```

Dependensi minimal: `requests`. HTML → teks bisa pakai regex + `html.unescape` (stdlib) agar hasilnya sama dengan versi PHP; `beautifulsoup4` opsional.

---

## 8. Kerangka kode

### 8.1 `models.py`
```python
from dataclasses import dataclass

@dataclass(frozen=True)
class ScrapedJob:
    company: str
    title: str
    description: str  # raw, may contain HTML
    url: str
```

### 8.2 `text.py`
```python
import html
import re

def html_to_text(raw: str) -> str:
    s = re.sub(r"<\s*(br|/p|/li|/h[1-6]|/div)\s*/?>", "\n", raw, flags=re.I)
    s = re.sub(r"<\s*li[^>]*>", "- ", s, flags=re.I)
    s = re.sub(r"<[^>]+>", "", s)
    s = html.unescape(s)
    s = re.sub(r"[ \t ]+", " ", s)
    s = re.sub(r" *\n[ \n]*", "\n", s)
    return s.strip()
```

### 8.3 `classify.py`
```python
import re
from functools import lru_cache
from .config import CAREER_KEYWORDS, EXCLUDE_TITLE_KEYWORDS

@lru_cache(maxsize=None)
def _pattern(keyword: str) -> re.Pattern[str]:
    # Whole-word on letters/digits (works for ".net", "c#", "ai/ml").
    return re.compile(r"(?<![^\W_])" + re.escape(keyword.lower()) + r"(?![^\W_])")

def _matches_any(title: str, keywords: list[str]) -> bool:
    t = title.lower()
    return any(_pattern(k).search(t) for k in keywords)

def career_for(title: str) -> str | None:
    if _matches_any(title, EXCLUDE_TITLE_KEYWORDS):
        return None
    for slug, keywords in CAREER_KEYWORDS.items():  # dict keeps insertion order
        if _matches_any(title, keywords):
            return slug
    return None
```
> `(?<![^\W_])` = "tidak didahului huruf/angka" (underscore dianggap pemisah). Sesuaikan bila ingin identik 100% dengan PHP `(?<![\p{L}\p{N}])`.

### 8.4 `sources/base.py` + contoh `remotive.py`
```python
from typing import Iterable, Protocol
from ..models import ScrapedJob

class JobSource(Protocol):
    name: str
    def fetch(self) -> Iterable[ScrapedJob]: ...
```
```python
import time
import requests
from ..config import HTTP, SOURCES
from ..models import ScrapedJob

class RemotiveSource:
    name = "Remotive"

    def __init__(self, session: requests.Session | None = None, sleep=time.sleep):
        self.s = session or requests.Session()
        self.s.headers.update({"User-Agent": HTTP["user_agent"], "Accept": "application/json"})
        self.sleep = sleep  # injectable for tests

    def fetch(self):
        cfg = SOURCES["remotive"]
        for i, category in enumerate(cfg["categories"]):
            if i > 0:
                self.sleep(cfg["delay_seconds"])
            r = self.s.get(cfg["endpoint"], params={"category": category}, timeout=HTTP["timeout"])
            r.raise_for_status()
            for job in r.json().get("jobs", []) or []:
                if isinstance(job, dict):
                    yield ScrapedJob(
                        company=str(job.get("company_name", "")),
                        title=str(job.get("title", "")),
                        description=str(job.get("description", "")),
                        url=str(job.get("url", "")),
                    )
```
Sumber lain mengikuti pola yang sama:
- **Arbeitnow**: loop `page=1..max_pages`, data di `data`, berhenti bila `links.next` kosong.
- **Himalayas**: loop `page`, param `country`, data di `jobs`, berhenti bila kosong; lewati job bila `parentCategories` (lowercase) tidak beririsan dengan `it_categories`; `url = applicationLink`.
- **Jobicy**: loop `industries`, param `count=100`, `industry`, `geo` (bila diisi); field `jobTitle`, `jobDescription`, `companyName`, `url`.

### 8.5 `collect.py`
```python
from urllib.parse import urlparse
from .classify import career_for
from .text import html_to_text

MAX_TEXT = 20_000

def _valid_url(u: str) -> bool:
    p = urlparse(u)
    return p.scheme in ("http", "https") and bool(p.netloc)

def collect(source, limit: int, dataset_version: str, collected_at: str):
    rows, seen, skipped = [], set(), 0
    for job in source.fetch():
        if len(rows) >= limit:
            break
        career = career_for(job.title)
        company, title, url = job.company.strip(), job.title.strip(), job.url.strip()
        desc = html_to_text(job.description)
        if (career is None or not company or not title or not desc
                or not _valid_url(url) or len(url) > 255
                or len(company) > 255 or len(title) > 255 or url in seen):
            skipped += 1
            continue
        seen.add(url)
        rows.append({
            "career": career, "company": company, "title": title,
            "description": desc[:MAX_TEXT], "source_name": source.name,
            "source_url": url, "collected_at": collected_at,
            "dataset_version": dataset_version,
        })
    return rows, skipped
```

### 8.6 `cli.py`
```python
import argparse, datetime, json, pathlib, sys
import requests
from .collect import collect
from .sources.remotive import RemotiveSource
from .sources.arbeitnow import ArbeitnowSource
from .sources.himalayas import HimalayasSource
from .sources.jobicy import JobicySource

SOURCES = {"remotive": RemotiveSource, "arbeitnow": ArbeitnowSource,
           "himalayas": HimalayasSource, "jobicy": JobicySource}
DEFAULT_DIR = pathlib.Path("backend/storage/app/private/scraped")

def main(argv=None) -> int:
    ap = argparse.ArgumentParser(prog="scraper")
    ap.add_argument("source", choices=SOURCES)
    ap.add_argument("--limit", type=int, default=200)
    ap.add_argument("--out")
    ap.add_argument("--dataset-version")
    a = ap.parse_args(argv)
    if a.limit < 1:
        print("--limit harus >= 1.", file=sys.stderr); return 1

    today = datetime.date.today().isoformat()
    version = a.dataset_version or f"{a.source}-{today}"
    src = SOURCES[a.source]()
    print(f"Mengambil lowongan dari {src.name}...")
    try:
        rows, skipped = collect(src, a.limit, version, today)
    except requests.RequestException as e:
        print(f"Gagal mengambil data dari {src.name}: {e}", file=sys.stderr); return 1

    out = pathlib.Path(a.out) if a.out else DEFAULT_DIR / f"{version}.json"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps({
        "_note": f"Dikumpulkan otomatis dari {src.name} pada {today}. Tinjau sebelum diimpor. "
                 "Atribusi wajib: source_name + source_url dipertahankan.",
        "jobs": rows,
    }, ensure_ascii=False, indent=4), encoding="utf-8")
    print(f"{len(rows)} lowongan disimpan ke {out} ({skipped} dilewati: profesi tidak cocok/data tidak lengkap/duplikat).")
    print(f"Tinjau file, lalu: php artisan skillbridge:import-jobs {out}")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
```
Tambahkan `scraper/__main__.py` berisi `from .cli import main; raise SystemExit(main())`.

---

## 9. Test (pytest, tanpa request sungguhan)

Pakai `responses` untuk mock HTTP, dan injeksi `sleep=lambda s: None`.

| Test | Yang dicek |
|---|---|
| `test_html_to_text` | contoh §5 menghasilkan teks persis |
| `test_career_for_table` | semua baris tabel §6.2 |
| `test_remotive_writes_importable_json` | 2 request (1 per kategori), 1 sleep, dedup URL ganda, `source_name="Remotive"` |
| `test_arbeitnow_paginates_until_limit` | `--limit 2` berhenti setelah 2 baris valid |
| `test_himalayas_it_categories_and_country` | job `Marketing` dibuang, request memuat `country=Indonesia` |
| `test_jobicy_each_industry` | 1 request per industri, URL sama di beberapa industri → 1 baris |
| `test_http_error_no_file` | respons 429 → exit code 1, file tidak dibuat |
| `test_slugs_match_seeder` | semua slug di `CAREER_KEYWORDS` ada di `CareerSeeder.php` (parse file PHP dengan regex `'([a-z-]+)' => \[`) |
| `test_output_passes_import` (manual/CI opsional) | `php artisan skillbridge:import-jobs <file> --dry-run` sukses |

---

## 10. Cara pakai

```bash
cd tools/scraper
python -m venv .venv && . .venv/bin/activate      # Windows: .venv\Scripts\activate
pip install -r requirements.txt

python -m scraper arbeitnow --limit 1000
python -m scraper himalayas
python -m scraper jobicy
python -m scraper remotive

# tinjau file JSON, lalu dari folder backend/:
php artisan skillbridge:import-jobs storage/app/private/scraped/arbeitnow-2026-11-10.json --dry-run
php artisan skillbridge:import-jobs storage/app/private/scraped/arbeitnow-2026-11-10.json
```

Data masuk ke tabel `job_postings` (`created_by = null`, `extraction_status = pending`). Ekstraksi skill (`job_skills`) baru berjalan setelah T5.3.

---

## 11. Gambaran hasil (uji 2026-11-10, versi Laravel)

| Sumber | Lowongan TI lolos |
|---|---|
| Arbeitnow | 492 |
| Jobicy | 193 |
| Himalayas (Indonesia) | 70 |
| Remotive | 5 |
| **Total** | **760** |

Catatan: lowongan Arbeitnow sebagian berbahasa Jerman/Prancis; beberapa judul ambigu (mis. "Manager – GenAI Strategy") — tetap tinjau JSON sebelum impor.

---

## 12. Checklist selesai

- [ ] 4 sumber berjalan dengan jeda & User-Agent sesuai §3
- [ ] Filter TI di sumber + filter judul non-TI + pemetaan 21 profesi (§6)
- [ ] Output lolos `skillbridge:import-jobs --dry-run`
- [ ] Semua test §9 lulus, tanpa request HTTP sungguhan
- [ ] Tidak ada scraping LinkedIn/JobStreet/situs yang melarang
- [ ] File hasil tidak di-commit (folder `storage/app/private/` sudah di-ignore)
