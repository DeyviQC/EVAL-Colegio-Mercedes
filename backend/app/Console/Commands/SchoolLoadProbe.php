<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SchoolLoadProbe extends Command
{
    protected $signature = 'eval:load {--url=http://127.0.0.1:8081} {--requests=50} {--concurrency=5}';

    protected $description = 'Measure local authenticated classroom reads without changing academic data';

    public function handle(): int
    {
        $base = rtrim($this->option('url'), '/');
        $host = parse_url($base, PHP_URL_HOST);
        if (! in_array($host, ['127.0.0.1', 'localhost'], true) || parse_url($base, PHP_URL_SCHEME) !== 'http' || app()->environment('production')) {
            $this->error('This probe is restricted to local development.');

            return self::FAILURE;
        }
        $count = max(1, min(500, (int) $this->option('requests')));
        $parallel = max(1, min(10, (int) $this->option('concurrency')));
        $cookie = tempnam(sys_get_temp_dir(), 'eval-load-');
        $sections = DB::table('sections')->orderBy('id')->pluck('id')->all();
        if (! $sections) {
            throw new \RuntimeException('Seed the school scenario first.');
        }
        $call = function (string $path, ?array $data = null, ?string $token = null) use ($base, $cookie): array {
            $ch = curl_init($base.$path);
            $headers = ['Accept: application/json'];
            if ($token) {
                $headers[] = 'X-CSRF-TOKEN: '.$token;
            }curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_ENCODING => '', CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $headers]);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }$body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($status !== 200) {
                throw new \RuntimeException('Local authentication/response failed: '.$status);
            }

            return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        };
        try {
            $token = $call('/api/session')['csrf_token'];
            $call('/api/login', ['email' => env('EVAL_LOAD_EMAIL', 'director@eval.test'), 'password' => env('EVAL_LOAD_PASSWORD', 'Mercedes2026!')], $token);
            $token = $call('/api/session')['csrf_token'];
            $times = [];
            $failed = 0;
            $started = microtime(true);
            for ($offset = 0; $offset < $count; $offset += $parallel) {
                $multi = curl_multi_init();
                $handles = [];
                for ($i = $offset; $i < min($count, $offset + $parallel); $i++) {
                    $path = ($i % 2 === 0 ? '/api/academic' : '/api/education').'?section_id='.$sections[$i % count($sections)];
                    $ch = curl_init($base.$path);
                    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $cookie, CURLOPT_ENCODING => '', CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
                    curl_multi_add_handle($multi, $ch);
                    $handles[] = $ch;
                }do {
                    curl_multi_exec($multi, $running);
                    if ($running) {
                        curl_multi_select($multi, 1);
                    }
                } while ($running);
                foreach ($handles as $ch) {
                    $body = curl_multi_getcontent($ch);
                    $info = curl_getinfo($ch);
                    $times[] = $info['total_time'] * 1000;
                    if ($info['http_code'] !== 200 || ! is_array(json_decode($body, true))) {
                        $failed++;
                    }curl_multi_remove_handle($multi, $ch);
                    curl_close($ch);
                }curl_multi_close($multi);
            }
            $seconds = microtime(true) - $started;
            sort($times);
            $this->line(json_encode(['requests' => $count, 'concurrency' => $parallel, 'failures' => $failed, 'median_ms' => round($times[(int) floor(count($times) / 2)], 2), 'p95_ms' => round($times[min(count($times) - 1, (int) ceil(count($times) * .95) - 1)], 2), 'elapsed_seconds' => round($seconds, 2), 'requests_per_second' => round($count / max($seconds, .001), 2), 'scope' => 'Local development classroom reads; not an institutional production SLA'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $call('/api/logout', [], $token);

            return $failed ? self::FAILURE : self::SUCCESS;
        } finally {
            if (is_file($cookie)) {
                unlink($cookie);
            }
        }
    }
}
