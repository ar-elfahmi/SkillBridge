<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Career;
use Illuminate\Database\Seeder;

/**
 * Careers in the IT family (rumpun TI). Idempotent: keyed by slug.
 * Keep in sync with config('skillbridge.scraping.career_keywords').
 */
class CareerSeeder extends Seeder
{
    /**
     * @return array<string, array{name: string, description: string}>
     */
    public static function careers(): array
    {
        return [
            'backend-developer' => [
                'name' => 'Backend Developer',
                'description' => 'Membangun dan memelihara logika sisi server, API, dan basis data aplikasi.',
            ],
            'frontend-developer' => [
                'name' => 'Frontend Developer',
                'description' => 'Membangun antarmuka web yang interaktif, responsif, dan mudah diakses.',
            ],
            'full-stack-developer' => [
                'name' => 'Full Stack Developer',
                'description' => 'Mengembangkan aplikasi web dari antarmuka hingga server dan basis data.',
            ],
            'data-analyst' => [
                'name' => 'Data Analyst',
                'description' => 'Mengolah, menganalisis, dan memvisualisasikan data untuk mendukung keputusan.',
            ],
            'software-engineer' => [
                'name' => 'Software Engineer',
                'description' => 'Merancang, membangun, dan menguji perangkat lunak secara umum lintas platform.',
            ],
            'mobile-developer' => [
                'name' => 'Mobile Developer',
                'description' => 'Membangun aplikasi Android/iOS, native maupun lintas platform.',
            ],
            'ui-ux-designer' => [
                'name' => 'UI/UX Designer',
                'description' => 'Merancang antarmuka dan pengalaman pengguna berbasis riset dan prototipe.',
            ],
            'qa-engineer' => [
                'name' => 'QA Engineer',
                'description' => 'Menjamin kualitas perangkat lunak melalui pengujian manual dan otomatis.',
            ],
            'devops-engineer' => [
                'name' => 'DevOps Engineer',
                'description' => 'Mengelola CI/CD, otomasi infrastruktur, dan keandalan sistem produksi.',
            ],
            'cloud-engineer' => [
                'name' => 'Cloud Engineer',
                'description' => 'Merancang dan mengelola infrastruktur serta layanan di platform cloud.',
            ],
            'data-engineer' => [
                'name' => 'Data Engineer',
                'description' => 'Membangun pipeline, gudang data, dan pemrosesan data berskala besar.',
            ],
            'data-scientist' => [
                'name' => 'Data Scientist',
                'description' => 'Membangun model statistik dan machine learning untuk menjawab masalah bisnis.',
            ],
            'machine-learning-engineer' => [
                'name' => 'Machine Learning / AI Engineer',
                'description' => 'Mengembangkan, melatih, dan menerapkan model machine learning dan AI ke produksi.',
            ],
            'cybersecurity-engineer' => [
                'name' => 'Cybersecurity Engineer',
                'description' => 'Melindungi sistem dan jaringan melalui pengujian, pemantauan, dan penanganan insiden keamanan.',
            ],
            'network-engineer' => [
                'name' => 'Network Engineer',
                'description' => 'Merancang, mengonfigurasi, dan memelihara jaringan komputer.',
            ],
            'system-administrator' => [
                'name' => 'System Administrator',
                'description' => 'Mengelola server, sistem operasi, dan infrastruktur TI organisasi.',
            ],
            'database-administrator' => [
                'name' => 'Database Administrator',
                'description' => 'Mengelola kinerja, keamanan, cadangan, dan ketersediaan basis data.',
            ],
            'system-analyst' => [
                'name' => 'System / IT Business Analyst',
                'description' => 'Menganalisis kebutuhan bisnis dan menerjemahkannya menjadi spesifikasi sistem informasi.',
            ],
            'game-developer' => [
                'name' => 'Game Developer',
                'description' => 'Membangun gim dan mekanik permainan dengan game engine.',
            ],
            'embedded-iot-engineer' => [
                'name' => 'Embedded / IoT Engineer',
                'description' => 'Mengembangkan firmware dan perangkat lunak untuk perangkat tertanam dan IoT.',
            ],
            'it-support' => [
                'name' => 'IT Support',
                'description' => 'Memberikan dukungan teknis perangkat keras, perangkat lunak, dan layanan TI pengguna.',
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::careers() as $slug => $attributes) {
            Career::query()->updateOrCreate(['slug' => $slug], $attributes);
        }
    }
}
