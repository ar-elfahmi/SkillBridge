<?php

declare(strict_types=1);

namespace Tests\Feature\Competency;

use App\Enums\VerificationStatus;
use App\Models\RpsDocument;
use App\Models\RpsPage;
use App\Modules\Competency\Services\EvidenceVerifier;
use App\Modules\Rps\Enums\ParseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceVerifierTest extends TestCase
{
    use RefreshDatabase;

    private EvidenceVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new EvidenceVerifier;
    }

    public function test_verifies_exact_match_on_specified_page(): void
    {
        $pages = [
            1 => 'RPS Pemrograman Web. CPMK 1: Menguasai konsep dasar web.',
            2 => 'Sub-CPMK 3: Mahasiswa mampu menerapkan OOP pada pola MVC menggunakan framework PHP.',
        ];

        $result = $this->verifier->verifyPages(
            evidence: 'menerapkan OOP pada pola MVC',
            pageNumber: 2,
            pages: $pages,
        );

        $this->assertTrue($result->isVerified());
        $this->assertSame(VerificationStatus::Verified, $result->status);
        $this->assertSame(2, $result->page);
    }

    public function test_verifies_case_insensitively(): void
    {
        $pages = [
            1 => 'Mampu Menggunakan DOCKER CONTAINER Dan REST API Untuk Deployment.',
        ];

        $result = $this->verifier->verifyPages(
            evidence: 'docker container dan rest api',
            pageNumber: 1,
            pages: $pages,
        );

        $this->assertTrue($result->isVerified());
        $this->assertSame(1, $result->page);
    }

    public function test_normalizes_whitespace_in_evidence_and_source(): void
    {
        $pages = [
            1 => "Mahasiswa   dapat \t mengimplementasikan  \n  autentikasi Sanctum.",
        ];

        $result = $this->verifier->verifyPages(
            evidence: "  mengimplementasikan \t\t autentikasi   Sanctum  ",
            pageNumber: 1,
            pages: $pages,
        );

        $this->assertTrue($result->isVerified());
        $this->assertSame(1, $result->page);
    }

    public function test_normalizes_smart_quotes_and_various_dashes(): void
    {
        $pages = [
            1 => 'Materi: Menggunakan “Laravel Framework” dalam arsitektur Client–Server.',
        ];

        // Evidence uses straight quotes and standard hyphen
        $result = $this->verifier->verifyPages(
            evidence: 'Menggunakan "Laravel Framework" dalam arsitektur Client-Server',
            pageNumber: 1,
            pages: $pages,
        );

        $this->assertTrue($result->isVerified());
        $this->assertSame(1, $result->page);
    }

    public function test_corrects_page_number_if_found_on_different_page(): void
    {
        $pages = [
            1 => 'Identitas mata kuliah dan dosen pengampu.',
            2 => 'Rencana mingguan perkuliahan.',
            3 => 'Tugas 1: Pembuatan REST API dengan Express.js.',
        ];

        // LLM mistakenly claims page 1, but quote is on page 3
        $result = $this->verifier->verifyPages(
            evidence: 'Pembuatan REST API dengan Express.js',
            pageNumber: 1,
            pages: $pages,
        );

        $this->assertTrue($result->isVerified());
        $this->assertSame(3, $result->page);
    }

    public function test_resolves_page_when_page_number_is_null(): void
    {
        $pages = [
            1 => 'Halaman awal dokumen.',
            2 => 'Sub-CPMK 5: Mahasiswa mampu merancang skema relasional PostgreSQL.',
        ];

        $result = $this->verifier->verifyPages(
            evidence: 'merancang skema relasional PostgreSQL',
            pageNumber: null,
            pages: $pages,
        );

        $this->assertTrue($result->isVerified());
        $this->assertSame(2, $result->page);
    }

    public function test_flags_hallucinated_evidence_as_unverified_and_nulls_page(): void
    {
        $pages = [
            1 => 'Dasar-dasar pemrograman web menggunakan HTML, CSS, dan JavaScript.',
            2 => 'Pengantar basis data relasional MySQL.',
        ];

        // AI hallucinates a technology or statement not present in document
        $result = $this->verifier->verifyPages(
            evidence: 'Mahasiswa sangat mahir dalam Kubernetes dan arsitektur microservices',
            pageNumber: 1,
            pages: $pages,
        );

        $this->assertFalse($result->isVerified());
        $this->assertSame(VerificationStatus::Unverified, $result->status);
        $this->assertNull($result->page);
    }

    public function test_flags_paraphrased_or_fabricated_evidence_as_unverified(): void
    {
        $pages = [
            1 => 'Mampu menerapkan konsep dasar object-oriented programming.',
        ];

        // Paraphrased quote (not verbatim) must be rejected
        $result = $this->verifier->verifyPages(
            evidence: 'Memahami prinsip OOP secara mendalam',
            pageNumber: 1,
            pages: $pages,
        );

        $this->assertFalse($result->isVerified());
        $this->assertSame(VerificationStatus::Unverified, $result->status);
        $this->assertNull($result->page);
    }

    public function test_returns_unverified_for_empty_evidence(): void
    {
        $pages = [1 => 'Teks halaman'];

        $result = $this->verifier->verifyPages('', 1, $pages);
        $this->assertFalse($result->isVerified());
        $this->assertNull($result->page);

        $resultSpaces = $this->verifier->verifyPages("   \t  ", 1, $pages);
        $this->assertFalse($resultSpaces->isVerified());
        $this->assertNull($resultSpaces->page);
    }

    public function test_verify_in_text_helper(): void
    {
        $source = 'Kualifikasi: Menguasai framework Laravel 11 dan database PostgreSQL.';

        $this->assertTrue($this->verifier->verifyInText('framework Laravel 11', $source));
        $this->assertTrue($this->verifier->verifyInText('laravel 11 dan database postgresql', $source));
        $this->assertFalse($this->verifier->verifyInText('framework Django', $source));
        $this->assertFalse($this->verifier->verifyInText('', $source));
    }

    public function test_verify_document_with_eloquent_pages(): void
    {
        $doc = RpsDocument::factory()->create(['parse_status' => ParseStatus::Parsed]);

        RpsPage::factory()->create([
            'rps_document_id' => $doc->id,
            'page_number' => 1,
            'text' => 'Identitas RPS Pemrograman Berorientasi Objek',
        ]);
        RpsPage::factory()->create([
            'rps_document_id' => $doc->id,
            'page_number' => 2,
            'text' => 'CPMK 2: Mampu menerapkan design pattern Singleton dan Factory',
        ]);

        $verifiedResult = $this->verifier->verifyDocument(
            evidence: 'design pattern Singleton dan Factory',
            pageNumber: 2,
            document: $doc,
        );

        $this->assertTrue($verifiedResult->isVerified());
        $this->assertSame(2, $verifiedResult->page);

        $hallucinatedResult = $this->verifier->verifyDocument(
            evidence: 'Microservices architecture dengan Kafka',
            pageNumber: 2,
            document: $doc,
        );

        $this->assertFalse($hallucinatedResult->isVerified());
        $this->assertNull($hallucinatedResult->page);
    }
}
