<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\JobPosting;
use App\Models\JobSkill;
use App\Models\Skill;
use App\Modules\Ai\OfflineAiEngine;
use App\Modules\Industry\Services\JobPostingImporter;
use App\Modules\Rps\Enums\ExtractionStatus;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Sample curated postings labelled "data contoh" (fictional companies, no
 * source URLs). For demo/development only, not validated industry data.
 * Idempotent via JobPostingImporter, and seeds verified job_skills so demo
 * analyses work out-of-the-box without requiring live LLM calls during db:seed.
 */
class SampleJobPostingSeeder extends Seeder
{
    public const DATASET_VERSION = 'sample-v1';

    public function run(JobPostingImporter $importer): void
    {
        $this->call([SkillSeeder::class, CareerSeeder::class]);

        $rows = array_map(
            fn (array $row) => $row + ['dataset_version' => self::DATASET_VERSION],
            $importer->readFile(database_path('data/sample_jobs.json')),
        );

        $errors = $importer->validate($rows);
        if ($errors !== []) {
            throw new RuntimeException('sample_jobs.json tidak valid: '.json_encode($errors));
        }

        $importer->import($rows);

        $this->seedSampleSkills();
    }

    private function seedSampleSkills(): void
    {
        $skillsByName = Skill::all()->keyBy(fn (Skill $s) => mb_strtolower($s->canonical_name));
        $model = (string) config('skillbridge.gemini.embedding_model', 'gemini-embedding-001');
        $dimensions = (int) config('skillbridge.gemini.embedding_dimensions', 768);
        $offlineEngine = new OfflineAiEngine;

        foreach ($this->sampleSkillsByTitle() as $title => $skillItems) {
            $posting = JobPosting::query()
                ->where('dataset_version', self::DATASET_VERSION)
                ->where('title', $title)
                ->first();

            if ($posting === null) {
                continue;
            }

            foreach ($skillItems as $item) {
                $skillModel = $skillsByName->get(mb_strtolower($item['canonical']));
                if ($skillModel === null) {
                    continue;
                }

                $embedText = "{$skillModel->canonical_name}: {$item['requirement_text']}";
                $textHash = hash('sha256', $embedText);

                JobSkill::query()->updateOrCreate(
                    [
                        'job_posting_id' => $posting->id,
                        'skill_id' => $skillModel->id,
                    ],
                    [
                        'skill' => $item['raw_skill'],
                        'requirement_text' => $item['requirement_text'],
                        'evidence' => $item['evidence'],
                        'embedding' => $offlineEngine->embedText($embedText, $dimensions),
                        'embedding_model' => $model,
                        'embedding_dimensions' => $dimensions,
                        'text_hash' => $textHash,
                    ]
                );
            }

            $posting->update([
                'extraction_status' => ExtractionStatus::Completed,
            ]);
        }
    }

    /**
     * @return array<string, list<array{canonical: string, raw_skill: string, requirement_text: string, evidence: string}>>
     */
    private function sampleSkillsByTitle(): array
    {
        return [
            'Backend Developer (PHP/Laravel)' => [
                [
                    'canonical' => 'Laravel Framework',
                    'raw_skill' => 'Laravel',
                    'requirement_text' => 'Merancang dan membangun REST API menggunakan Laravel',
                    'evidence' => 'Merancang dan membangun REST API menggunakan Laravel.',
                ],
                [
                    'canonical' => 'REST API',
                    'raw_skill' => 'REST API',
                    'requirement_text' => 'Merancang dan membangun REST API serta menerapkan autentikasi pada API',
                    'evidence' => 'Merancang dan membangun REST API menggunakan Laravel.',
                ],
                [
                    'canonical' => 'PostgreSQL',
                    'raw_skill' => 'PostgreSQL',
                    'requirement_text' => 'Mengelola basis data PostgreSQL termasuk perancangan skema dan optimasi query',
                    'evidence' => 'Mengelola basis data PostgreSQL atau MySQL termasuk perancangan skema dan optimasi query.',
                ],
                [
                    'canonical' => 'PHP',
                    'raw_skill' => 'PHP',
                    'requirement_text' => 'Menguasai PHP dan pemrograman berorientasi objek',
                    'evidence' => 'Menguasai PHP dan pemrograman berorientasi objek.',
                ],
                [
                    'canonical' => 'MVC',
                    'raw_skill' => 'MVC',
                    'requirement_text' => 'Memahami pola arsitektur MVC',
                    'evidence' => 'Memahami pola MVC.',
                ],
                [
                    'canonical' => 'Git',
                    'raw_skill' => 'Git',
                    'requirement_text' => 'Menggunakan Git untuk version control',
                    'evidence' => 'Terbiasa menggunakan Git untuk version control.',
                ],
                [
                    'canonical' => 'Unit Testing',
                    'raw_skill' => 'Unit Test',
                    'requirement_text' => 'Menulis unit test untuk logika bisnis',
                    'evidence' => 'Menulis unit test untuk logika bisnis.',
                ],
                [
                    'canonical' => 'Docker',
                    'raw_skill' => 'Docker',
                    'requirement_text' => 'Pengalaman kontainerisasi menggunakan Docker',
                    'evidence' => 'Pengalaman dengan Docker menjadi nilai tambah.',
                ],
            ],
            'Junior Backend Engineer' => [
                [
                    'canonical' => 'SQL',
                    'raw_skill' => 'SQL',
                    'requirement_text' => 'Menulis query SQL yang efisien dan menjaga integritas data',
                    'evidence' => 'Menulis query SQL yang efisien dan menjaga integritas data.',
                ],
                [
                    'canonical' => 'CI/CD',
                    'raw_skill' => 'CI/CD',
                    'requirement_text' => 'Menyiapkan pipeline CI/CD sederhana',
                    'evidence' => 'Menyiapkan pipeline CI/CD sederhana dan deployment ke server Linux.',
                ],
                [
                    'canonical' => 'Linux',
                    'raw_skill' => 'Linux',
                    'requirement_text' => 'Deployment ke server Linux dan penggunaan Linux command line',
                    'evidence' => 'Terbiasa dengan Linux command line dan Git.',
                ],
                [
                    'canonical' => 'Redis',
                    'raw_skill' => 'Redis',
                    'requirement_text' => 'Menggunakan Redis untuk cache dan antrean pekerjaan',
                    'evidence' => 'Menggunakan Redis untuk cache dan antrean pekerjaan.',
                ],
                [
                    'canonical' => 'HTTP',
                    'raw_skill' => 'HTTP',
                    'requirement_text' => 'Memahami protokol HTTP dan arsitektur client-server',
                    'evidence' => 'Memahami protokol HTTP dan arsitektur client-server.',
                ],
                [
                    'canonical' => 'REST API',
                    'raw_skill' => 'RESTful API',
                    'requirement_text' => 'Mampu membuat RESTful API dan integrasi API pihak ketiga',
                    'evidence' => 'Mampu membuat RESTful API.',
                ],
                [
                    'canonical' => 'Git',
                    'raw_skill' => 'Git',
                    'requirement_text' => 'Menggunakan Git untuk manajemen kode sumber',
                    'evidence' => 'Terbiasa dengan Linux command line dan Git.',
                ],
            ],
            'Frontend Developer (React)' => [
                [
                    'canonical' => 'React',
                    'raw_skill' => 'React',
                    'requirement_text' => 'Membangun antarmuka web dengan React',
                    'evidence' => 'Membangun antarmuka web dengan React dan TypeScript.',
                ],
                [
                    'canonical' => 'TypeScript',
                    'raw_skill' => 'TypeScript',
                    'requirement_text' => 'Membangun antarmuka web menggunakan TypeScript',
                    'evidence' => 'Membangun antarmuka web dengan React dan TypeScript.',
                ],
                [
                    'canonical' => 'Tailwind CSS',
                    'raw_skill' => 'Tailwind CSS',
                    'requirement_text' => 'Membuat tampilan responsif dengan Tailwind CSS',
                    'evidence' => 'Membuat tampilan responsif dengan Tailwind CSS.',
                ],
                [
                    'canonical' => 'JavaScript',
                    'raw_skill' => 'JavaScript (ES6)',
                    'requirement_text' => 'Menguasai JavaScript (ES6) untuk pengembangan antarmuka',
                    'evidence' => 'Menguasai HTML, CSS, dan JavaScript (ES6).',
                ],
                [
                    'canonical' => 'HTML',
                    'raw_skill' => 'HTML',
                    'requirement_text' => 'Menguasai HTML untuk struktur halaman web',
                    'evidence' => 'Menguasai HTML, CSS, dan JavaScript (ES6).',
                ],
                [
                    'canonical' => 'CSS',
                    'raw_skill' => 'CSS',
                    'requirement_text' => 'Menguasai CSS dan responsive web design',
                    'evidence' => 'Menguasai HTML, CSS, dan JavaScript (ES6).',
                ],
                [
                    'canonical' => 'Fetch API',
                    'raw_skill' => 'Fetch API',
                    'requirement_text' => 'Mengonsumsi REST API menggunakan Fetch API',
                    'evidence' => 'Mengonsumsi REST API menggunakan Fetch API.',
                ],
            ],
            'Web Frontend Engineer' => [
                [
                    'canonical' => 'Vue.js',
                    'raw_skill' => 'Vue.js',
                    'requirement_text' => 'Mengembangkan halaman web interaktif menggunakan Vue.js',
                    'evidence' => 'Mengembangkan halaman web interaktif menggunakan Vue.js.',
                ],
                [
                    'canonical' => 'JavaScript',
                    'raw_skill' => 'JavaScript',
                    'requirement_text' => 'Menguasai JavaScript dan asynchronous JavaScript (AJAX)',
                    'evidence' => 'Menguasai JavaScript, HTML5, dan CSS3.',
                ],
                [
                    'canonical' => 'HTML',
                    'raw_skill' => 'HTML5',
                    'requirement_text' => 'Menguasai HTML5 untuk struktur dokumen web',
                    'evidence' => 'Menguasai JavaScript, HTML5, dan CSS3.',
                ],
                [
                    'canonical' => 'CSS',
                    'raw_skill' => 'CSS3',
                    'requirement_text' => 'Menguasai CSS3 untuk tata letak halaman web',
                    'evidence' => 'Menguasai JavaScript, HTML5, dan CSS3.',
                ],
                [
                    'canonical' => 'Tailwind CSS',
                    'raw_skill' => 'Tailwind CSS',
                    'requirement_text' => 'Pengalaman menggunakan framework CSS seperti Tailwind CSS',
                    'evidence' => 'Pengalaman dengan Bootstrap atau Tailwind CSS.',
                ],
            ],
            'Full Stack Developer' => [
                [
                    'canonical' => 'React',
                    'raw_skill' => 'React',
                    'requirement_text' => 'Mengembangkan frontend aplikasi web menggunakan React',
                    'evidence' => 'Mengembangkan aplikasi web end-to-end: frontend React dan backend Laravel.',
                ],
                [
                    'canonical' => 'Laravel Framework',
                    'raw_skill' => 'Laravel',
                    'requirement_text' => 'Mengembangkan backend aplikasi web menggunakan Laravel',
                    'evidence' => 'Mengembangkan aplikasi web end-to-end: frontend React dan backend Laravel.',
                ],
                [
                    'canonical' => 'REST API',
                    'raw_skill' => 'REST API',
                    'requirement_text' => 'Merancang basis data relasional dan REST API',
                    'evidence' => 'Merancang basis data relasional dan REST API.',
                ],
                [
                    'canonical' => 'Authentication',
                    'raw_skill' => 'Autentikasi Session',
                    'requirement_text' => 'Menerapkan autentikasi berbasis session',
                    'evidence' => 'Menerapkan autentikasi berbasis session.',
                ],
                [
                    'canonical' => 'Docker',
                    'raw_skill' => 'Docker',
                    'requirement_text' => 'Melakukan deployment aplikasi menggunakan Docker',
                    'evidence' => 'Melakukan deployment menggunakan Docker.',
                ],
                [
                    'canonical' => 'PHP',
                    'raw_skill' => 'PHP',
                    'requirement_text' => 'Menguasai pemrograman PHP',
                    'evidence' => 'Menguasai PHP, JavaScript, dan SQL.',
                ],
                [
                    'canonical' => 'JavaScript',
                    'raw_skill' => 'JavaScript',
                    'requirement_text' => 'Menguasai pemrograman JavaScript',
                    'evidence' => 'Menguasai PHP, JavaScript, dan SQL.',
                ],
                [
                    'canonical' => 'SQL',
                    'raw_skill' => 'SQL',
                    'requirement_text' => 'Menguasai SQL untuk pengelolaan basis data relasional',
                    'evidence' => 'Menguasai PHP, JavaScript, dan SQL.',
                ],
            ],
            'Software Engineer (Full Stack)' => [
                [
                    'canonical' => 'Node.js',
                    'raw_skill' => 'Node.js',
                    'requirement_text' => 'Membangun fitur produk dengan Node.js di sisi server',
                    'evidence' => 'Membangun fitur produk dengan Node.js dan Express.js di sisi server serta React di sisi klien.',
                ],
                [
                    'canonical' => 'Express.js',
                    'raw_skill' => 'Express.js',
                    'requirement_text' => 'Membangun layanan server dengan Express.js',
                    'evidence' => 'Membangun fitur produk dengan Node.js dan Express.js di sisi server serta React di sisi klien.',
                ],
                [
                    'canonical' => 'React',
                    'raw_skill' => 'React',
                    'requirement_text' => 'Membangun antarmuka pengguna dengan React di sisi klien',
                    'evidence' => 'Membangun fitur produk dengan Node.js dan Express.js di sisi server serta React di sisi klien.',
                ],
                [
                    'canonical' => 'TypeScript',
                    'raw_skill' => 'TypeScript',
                    'requirement_text' => 'Menguasai TypeScript untuk pengembangan perangkat lunak',
                    'evidence' => 'Menguasai TypeScript atau JavaScript.',
                ],
                [
                    'canonical' => 'PostgreSQL',
                    'raw_skill' => 'PostgreSQL',
                    'requirement_text' => 'Pengalaman mengelola basis data PostgreSQL',
                    'evidence' => 'Pengalaman dengan PostgreSQL.',
                ],
                [
                    'canonical' => 'Unit Testing',
                    'raw_skill' => 'Unit Test',
                    'requirement_text' => 'Menulis unit test dan automated testing',
                    'evidence' => 'Menulis unit test dan automated testing.',
                ],
            ],
            'Data Analyst' => [
                [
                    'canonical' => 'SQL',
                    'raw_skill' => 'SQL',
                    'requirement_text' => 'Menulis query SQL untuk analisis data',
                    'evidence' => 'Menulis query SQL untuk analisis.',
                ],
                [
                    'canonical' => 'Data Visualization',
                    'raw_skill' => 'Visualisasi Data',
                    'requirement_text' => 'Membuat dashboard dan visualisasi data',
                    'evidence' => 'Membuat dashboard dan visualisasi data menggunakan Power BI atau Tableau.',
                ],
                [
                    'canonical' => 'Power BI',
                    'raw_skill' => 'Power BI',
                    'requirement_text' => 'Membuat dashboard analitik menggunakan Power BI',
                    'evidence' => 'Membuat dashboard dan visualisasi data menggunakan Power BI atau Tableau.',
                ],
                [
                    'canonical' => 'Microsoft Excel',
                    'raw_skill' => 'Microsoft Excel',
                    'requirement_text' => 'Menguasai Microsoft Excel untuk analisis data',
                    'evidence' => 'Menguasai SQL dan Microsoft Excel.',
                ],
                [
                    'canonical' => 'Statistics',
                    'raw_skill' => 'Statistika',
                    'requirement_text' => 'Memahami statistika dasar untuk interpretasi data',
                    'evidence' => 'Memahami statistika dasar.',
                ],
                [
                    'canonical' => 'Python',
                    'raw_skill' => 'Python',
                    'requirement_text' => 'Menggunakan Python untuk analisis data',
                    'evidence' => 'Mampu menggunakan Python dengan Pandas menjadi nilai tambah.',
                ],
                [
                    'canonical' => 'Pandas',
                    'raw_skill' => 'Pandas',
                    'requirement_text' => 'Menggunakan pustaka Pandas untuk manipulasi data',
                    'evidence' => 'Mampu menggunakan Python dengan Pandas menjadi nilai tambah.',
                ],
            ],
            'Junior Data Analyst' => [
                [
                    'canonical' => 'Data Cleaning',
                    'raw_skill' => 'Data Cleaning',
                    'requirement_text' => 'Melakukan data cleaning dan data wrangling pada data penjualan',
                    'evidence' => 'Melakukan data cleaning dan data wrangling pada data penjualan.',
                ],
                [
                    'canonical' => 'Microsoft Excel',
                    'raw_skill' => 'Excel',
                    'requirement_text' => 'Membuat laporan berkala menggunakan Excel dan spreadsheet',
                    'evidence' => 'Membuat laporan berkala dengan Excel dan visualisasi data.',
                ],
                [
                    'canonical' => 'Data Visualization',
                    'raw_skill' => 'Visualisasi Data',
                    'requirement_text' => 'Membuat laporan berkala dan visualisasi data penjualan',
                    'evidence' => 'Membuat laporan berkala dengan Excel dan visualisasi data.',
                ],
                [
                    'canonical' => 'Statistics',
                    'raw_skill' => 'Statistik Deskriptif',
                    'requirement_text' => 'Memahami statistik deskriptif untuk pelaporan data',
                    'evidence' => 'Memahami statistik deskriptif.',
                ],
                [
                    'canonical' => 'SQL',
                    'raw_skill' => 'SQL',
                    'requirement_text' => 'Terbiasa menggunakan SQL untuk pengambilan data',
                    'evidence' => 'Terbiasa dengan spreadsheet dan SQL.',
                ],
            ],
        ];
    }
}
