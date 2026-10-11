<?php

namespace Tests\Feature\Security;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

// SECURITY-001 / CVE-2026-48019 (GHSA-5vg9-5847-vvmq) - penjaga paparan.
//
// Keputusan pemilik (11 Okt 2026): CVE dicatat TIDAK TERDAMPAK karena aplikasi tidak mengirim email dan tidak memakai
// aturan validasi `email`. Test ini menjaga asumsi itu: GAGAL bila kode di app/ atau routes/ mulai memakai email
// (Mail::, Mailable, Notification, ->notify(, aturan validasi `email`), supaya CVE dievaluasi ulang SEBELUM fitur itu dipakai.
//
// Yang TIDAK dianggap temuan: trait `Notifiable` pada User (hanya `use Illuminate\Notifications\Notifiable`); trait itu
// tidak mengirim apa pun tanpa `->notify(` atau kelas Notification. Komentar dan docblock diabaikan (dibuang lewat tokenizer).
// Cakupan: app/ dan routes/ saja (bukan config/, database/, resources/).
//
// STATUS EKSEKUSI: ditulis di sandbox tanpa PHP; jalankan `php artisan test --filter=MailExposureGuardTest`.

class MailExposureGuardTest extends TestCase
{
    /** @return array<string, string> label => regex */
    protected function patterns(): array
    {
        return [
            'Mail::'                  => '/\bMail::/',
            'Mailable'                => '/\bMailable\b/',
            'Notification'            => '/\bNotification\b/',
            '->notify('               => '/->\s*notify(?:Now)?\s*\(/',
            'aturan validasi email'   => '/([\'"])(?:[^\'"]*\|)?email(?:[:|][^\'"]*)?\1/',
            'Rule::email'             => '/\bRule::email\s*\(/',
            'Rules\\Email'            => '/Rules\\\\Email\b/',
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

    /** @return string[] label pola yang cocok pada potongan kode */
    protected function labelsIn(string $code): array
    {
        $found = [];

        foreach ($this->patterns() as $label => $regex) {
            if (preg_match($regex, $code)) {
                $found[] = $label;
            }
        }

        return $found;
    }

    /** @return string[] "berkas:baris [label]" */
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
                foreach ($this->labelsIn($line) as $label) {
                    $hits[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ':' . ($i + 1) . " [{$label}]";
                }
            }
        }

        return $hits;
    }

    /** @test */
    public function app_dan_routes_tidak_memakai_email(): void
    {
        $hits = [...$this->scan(app_path()), ...$this->scan(base_path('routes'))];

        $this->assertSame(
            [],
            $hits,
            "Evaluasi ulang CVE-2026-48019 sebelum memakai email. Temuan:\n" . implode("\n", $hits)
        );
    }

    /**
     * Penjaga tidak boleh kosong-melompong: pastikan pola benar-benar mendeteksi, dan tidak salah menandai Notifiable.
     *
     * @test
     */
    public function pola_penjaga_mendeteksi_pemakaian_email_dan_tidak_menandai_notifiable(): void
    {
        $mustFlag = [
            'Mail::to($user)->send($m);',
            'class Welcome extends Mailable {}',
            'Notification::send($users, $n);',
            '$user->notify(new Foo());',
            '$user->notifyNow(new Foo());',
            "'email' => 'required|email',",
            "'rules' => ['required|email|max:255'],",
            "'x' => 'email:rfc,dns',",
            "Rule::email()->rfcCompliant();",
            'use Illuminate\Validation\Rules\Email;',
            'use Illuminate\Notifications\Notification;',
        ];

        foreach ($mustFlag as $sample) {
            $this->assertNotSame([], $this->labelsIn($sample), "tidak terdeteksi: {$sample}");
        }

        $mustNotFlag = [
            'use Illuminate\Notifications\Notifiable;',
            'use HasApiTokens, Notifiable;',
            "'email_verified_at' => 'datetime',",
            "'nip' => 'required|string|max:30',",
            "// Mail::to(\$x) hanya komentar",
        ];

        foreach ($mustNotFlag as $sample) {
            $this->assertSame([], $this->labelsIn($this->codeWithoutComments("<?php\n" . $sample)), "salah tandai: {$sample}");
        }
    }
}
