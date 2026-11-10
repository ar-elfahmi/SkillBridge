<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SkillCategory;
use App\Models\Skill;
use Illuminate\Database\Seeder;

/**
 * Initial competency dictionary (canonical name + aliases), focused on the MVP
 * careers: Backend, Frontend, Full Stack, Data Analyst. Idempotent: re-running
 * updates category and merges aliases without duplicating rows.
 */
class SkillSeeder extends Seeder
{
    /**
     * @return array<string, array{0: SkillCategory, 1: list<string>}>
     */
    public static function dictionary(): array
    {
        $l = SkillCategory::Language;
        $f = SkillCategory::Framework;
        $t = SkillCategory::Tool;
        $c = SkillCategory::Concept;
        $p = SkillCategory::Practice;

        return [
            // Languages
            'PHP' => [$l, ['PHP Programming', 'Pemrograman PHP', 'PHP Native']],
            'JavaScript' => [$l, ['JS', 'Pemrograman JavaScript', 'ECMAScript', 'ES6']],
            'TypeScript' => [$l, ['TS']],
            'Python' => [$l, ['Pemrograman Python', 'Python Programming']],
            'Java' => [$l, ['Pemrograman Java']],
            'SQL' => [$l, ['Structured Query Language', 'Query SQL']],
            'HTML' => [$l, ['HTML5', 'HyperText Markup Language']],
            'CSS' => [$l, ['CSS3', 'Cascading Style Sheets']],

            // Frameworks & libraries
            'Laravel Framework' => [$f, ['Laravel', 'PHP Laravel']],
            'React' => [$f, ['ReactJS', 'React.js']],
            'Vue.js' => [$f, ['Vue', 'VueJS']],
            'Node.js' => [$f, ['NodeJS', 'Node']],
            'Express.js' => [$f, ['Express', 'ExpressJS']],
            'Tailwind CSS' => [$f, ['Tailwind', 'TailwindCSS']],
            'Bootstrap' => [$f, ['Bootstrap CSS']],
            'Pandas' => [$f, ['Python Pandas']],

            // Tools & platforms
            'Git' => [$t, ['Git Version Control', 'GitHub', 'GitLab', 'Version Control']],
            'Docker' => [$t, ['Docker Container', 'Containerization', 'Kontainerisasi']],
            'Linux' => [$t, ['Linux Server', 'Linux Command Line', 'Bash']],
            'MySQL' => [$t, ['MariaDB']],
            'PostgreSQL' => [$t, ['Postgres']],
            'Redis' => [$t, []],
            'Microsoft Excel' => [$t, ['Excel', 'Spreadsheet']],
            'Power BI' => [$t, ['PowerBI', 'Microsoft Power BI']],
            'Tableau' => [$t, []],

            // Concepts
            'REST API' => [$c, ['RESTful API', 'REST', 'RESTful Web Service', 'Web API']],
            'HTTP' => [$c, ['HTTP Protocol', 'Client-Server', 'Protokol HTTP']],
            'Object-Oriented Programming' => [$c, ['OOP', 'Pemrograman Berorientasi Objek', 'PBO']],
            'MVC' => [$c, ['Model-View-Controller', 'PHP MVC', 'Pola MVC']],
            'Database Design' => [$c, ['Perancangan Basis Data', 'ERD', 'Normalisasi Basis Data', 'Data Modeling']],
            'Authentication' => [$c, ['Autentikasi', 'Login', 'Session Authentication', 'Cookie dan Session']],
            'Web Security' => [$c, ['Keamanan Web', 'OWASP', 'Application Security']],
            'Responsive Web Design' => [$c, ['Responsive Design', 'Desain Responsif']],
            'Data Structures and Algorithms' => [$c, ['Struktur Data', 'Algoritma', 'DSA']],
            'Data Visualization' => [$c, ['Visualisasi Data']],
            'Statistics' => [$c, ['Statistika', 'Statistik']],
            'Data Cleaning' => [$c, ['Data Wrangling', 'Pembersihan Data', 'Data Preprocessing']],
            'Fetch API' => [$c, ['AJAX', 'Asynchronous JavaScript']],

            // Practices
            'Unit Testing' => [$p, ['Software Testing', 'Pengujian Perangkat Lunak', 'Automated Testing']],
            'CI/CD' => [$p, ['Continuous Integration', 'Continuous Deployment', 'GitHub Actions']],
            'Agile' => [$p, ['Scrum', 'Agile Development']],
            'Code Review' => [$p, []],
        ];
    }

    public function run(): void
    {
        foreach (self::dictionary() as $name => [$category, $aliases]) {
            $skill = Skill::query()->whereRaw('LOWER(canonical_name) = ?', [mb_strtolower($name)])->first()
                ?? new Skill(['canonical_name' => $name]);

            $skill->category = $category;
            $skill->aliases = array_values(array_unique([...($skill->aliases ?? []), ...$aliases]));
            $skill->save();
        }
    }
}
