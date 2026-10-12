<?php

namespace Tests\Feature\Security;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

// SECURITY-001 - penerimaan risiko Laravel 11 (Opsi B, keputusan pemilik 12 Okt 2026).
//
// Pemilik tetap di laravel/framework 11.56.1 (tanpa upgrade sebelum go-live) dengan syarat penjaga: risiko dari
// `composer audit` hanya diterima selama aplikasi TIDAK memakai fitur yang terdampak. Penjaga email ada di
// MailExposureGuardTest; test ini menjaga dua fitur lain:
//   - signed URL  -> advisory Temporary Signed URL Path Confusion
//   - markdown    -> advisory league/commonmark (paket transitif)
// Test GAGAL bila kode di app/ atau routes/ mulai memakainya. ID advisory pada pesan gagal DIBACA dari tabel di
// docs/ops/security-001-env.md (tidak ditulis keras di sini), supaya selalu sama dengan catatan resmi.
//
// Komentar/docblock diabaikan (tokenizer). Cakupan: app/ dan routes/ saja (bukan config/, database/, resources/).
// Penjaga ini hanya mengenali pola yang terdaftar di bawah; ia bukan pengganti review.
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan `php artisan test --filter=FrameworkAdvisoryGuardTest`.

class FrameworkAdvisoryGuardTest extends TestCase
{
    private const DOC = 'docs/ops/security-001-env.md';

    /** @return array<string, array<string, string>> kategori => [label => regex] */
    protected function patterns(): array
    {
        return [
            'signed' => [
                'signedRoute / temporarySignedRoute' => '/\b(?:temporarySignedRoute|signedRoute)\s*\(/',
                'hasValidSignature'                  => '/\bhasValid(?:Relative)?Signature\w*\s*\(/',
                "middleware 'signed'"                => '/([\'"])signed(?::[^\'"]*)?\1/',
                'ValidateSignature'                  => '/\bValidateSignature\b/',
            ],
            'markdown' => [
                'Str::markdown / Str::inlineMarkdown' => '/\bStr::(?:inline)?[mM]arkdown\s*\(/',
                '->markdown( / ->inlineMarkdown('     => '/->\s*(?:inline)?[mM]arkdown\s*\(/',
                'mail:: (komponen markdown mail)'     => '/[\'"]mail::/',
                'Mail\\Markdown'                      => '/Mail\\\\Markdown\b/',
                'League\\CommonMark'                  => '/League\\\\CommonMark/i',
                'CommonMarkConverter'                 => '/\bCommonMarkConverter\b/',
                'GithubFlavoredMarkdownConverter'     => '/\bGithubFlavoredMarkdownConverter\b/',
            ],
        ];
    }

    /** Kode PHP tanpa komentar/docblock; jumlah baris dipertahankan supaya nomor baris tetap benar. */
    protected function codeWithoutComments(string $source): string
    {
        $out = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    $out .= str_repeat("\n", substr_count($token[1], "\n"));
                    continue;
                }
                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    /** @return array<int, array{0: string, 1: string}> pasangan [kategori, label] yang cocok pada potongan kode */
    protected function matchesIn(string $code): array
    {
        $found = [];

        foreach ($this->patterns() as $category => $set) {
            foreach ($set as $label => $regex) {
                if (preg_match($regex, $code)) {
                    $found[] = [$category, $label];
                }
            }
        }

        return $found;
    }

    /** @return array<int, array{0: string, 1: string, 2: string}> [berkas:baris, kategori, label] */
    protected function scan(string $dir): array
    {
        $hits = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $lines = explode("\n", $this->codeWithoutComments(file_get_contents($file->getPathname())));

            foreach ($lines as $i => $line) {
                foreach ($this->matchesIn($line) as [$category, $label]) {
                    $where = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . ($i + 1);
                    $hits[] = [$where, $category, $label];
                }
            }
        }

        return $hits;
    }

    /**
     * ID advisory dari tabel pada docs/ops/security-001-env.md (kolom "Advisory"), per kategori:
     * signed = baris yang judulnya memuat "Signed URL"; markdown = baris paket league/commonmark.
     *
     * @return array<string, string[]>
     */
    protected function advisoryIdsFromDoc(): array
    {
        $doc = file_get_contents(base_path(self::DOC));
        $ids = ['signed' => [], 'markdown' => []];

        preg_match_all('/^\s*\|\s*\d+\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|\s*([^|]+?)\s*\|/m', $doc, $rows, PREG_SET_ORDER);

        foreach ($rows as [, $package, $advisory, $title]) {
            preg_match_all('/\b(?:CVE-\d{4}-\d+|GHSA-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4})\b/i', $advisory, $found);

            if (stripos($title, 'Signed URL') !== false) {
                $ids['signed'] = [...$ids['signed'], ...$found[0]];
            }
            if (strcasecmp($package, 'league/commonmark') === 0) {
                $ids['markdown'] = [...$ids['markdown'], ...$found[0]];
            }
        }

        return array_map(fn (array $list) => array_values(array_unique($list)), $ids);
    }

    /** @test */
    public function app_dan_routes_tidak_memakai_signed_url_maupun_markdown(): void
    {
        $hits = [...$this->scan(app_path()), ...$this->scan(base_path('routes'))];

        $message = '';
        if ($hits !== []) {
            $docIds = $this->advisoryIdsFromDoc();
            $lines = [];
            $categories = [];

            foreach ($hits as [$where, $category, $label]) {
                $lines[] = "{$where} [{$category}: {$label}]";
                $categories[$category] = true;
            }

            $related = [];
            foreach (array_keys($categories) as $category) {
                $related[] = $category . ' -> ' . (implode(', ', $docIds[$category]) ?: '(ID tidak ditemukan di ' . self::DOC . ')');
            }

            $message = "Evaluasi ulang penerimaan risiko Laravel 11 sebelum memakai fitur ini.\n"
                . 'Advisory terkait (dari ' . self::DOC . '): ' . implode('; ', $related) . "\nTemuan:\n" . implode("\n", $lines);
        }

        $this->assertSame([], $hits, $message);
    }

    /** @test */
    public function id_advisory_untuk_pesan_gagal_terbaca_dari_dokumen(): void
    {
        $ids = $this->advisoryIdsFromDoc();

        $this->assertNotEmpty($ids['signed'], 'Tidak ada ID advisory signed URL pada tabel ' . self::DOC);
        $this->assertNotEmpty($ids['markdown'], 'Tidak ada ID advisory league/commonmark pada tabel ' . self::DOC);
    }

    /**
     * Penjaga tidak boleh kosong-melompong: pola harus benar-benar mendeteksi, dan tidak salah menandai kode biasa.
     *
     * @test
     */
    public function pola_penjaga_mendeteksi_signed_url_dan_markdown_dan_tidak_salah_tandai(): void
    {
        $mustFlag = [
            "URL::signedRoute('unduh', ['id' => 1]);",
            'URL::temporarySignedRoute("unduh", now()->addMinutes(5), $p);',
            'if (! $request->hasValidSignature()) { abort(401); }',
            'if (! $request->hasValidRelativeSignature()) { abort(401); }',
            "Route::get('/x', \$h)->middleware('signed');",
            "Route::get('/x', \$h)->middleware(['auth', 'signed:relative']);",
            'use Illuminate\Routing\Middleware\ValidateSignature;',
            'return Str::markdown($teks);',
            'return Str::inlineMarkdown($teks);',
            '$html = str($teks)->markdown();',
            "return \$this->markdown('mail.invoice');",
            "@component('mail::message')",
            "\$url = 'mail::button';",
            'use Illuminate\Mail\Markdown;',
            'use League\CommonMark\CommonMarkConverter;',
            '$c = new GithubFlavoredMarkdownConverter();',
        ];

        foreach ($mustFlag as $sample) {
            $this->assertNotSame([], $this->matchesIn($sample), "tidak terdeteksi: {$sample}");
        }

        $mustNotFlag = [
            "'status' => 'designed',",
            "'signature' => \$signature,",
            '$designed = true;',
            'return $this->markdownLike;',
            "'markdown' => 'x',",
            'if ($user->hasValidPassword()) {}',
            "// URL::signedRoute('x') dan Str::markdown(\$t) hanya komentar",
        ];

        foreach ($mustNotFlag as $sample) {
            $this->assertSame([], $this->matchesIn($this->codeWithoutComments("<?php\n" . $sample)), "salah tandai: {$sample}");
        }
    }
}
