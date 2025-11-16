<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCache
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Не кешираме ако има признак за персонален отговор
        $hasAuth = $request->headers->has('Authorization') || $request->hasSession();
        $isSafeMethod = in_array($request->getMethod(), ['GET', 'HEAD'], true);
        $ok = $response->getStatusCode() === 200;

        if (!$isSafeMethod || !$ok || $hasAuth) {
            return $response;
        }

        // Някои видове отговори не са подходящи за ETag (стрийм/файл)
        $isStream = $response instanceof StreamedResponse || $response instanceof BinaryFileResponse;

        // Базов публичен кеш за браузъри
        // - max-age: кеш в браузъра
        // - stale-while-revalidate/stale-if-error: по-добра UX при бавна мрежа
        $response->headers->set('Cache-Control', 'public, max-age=60, stale-while-revalidate=120, stale-if-error=600');

        // За CDN/shared caches (Cloudflare/Reverse proxy) – можеш да сложиш по-дълъг s-maxage
        // (Cloudflare уважава Cache-Control, Vercel – s-maxage/Surrogate-Control)
        $response->headers->set('Surrogate-Control', 'max-age=300');   // 5 мин за edge
        $response->headers->set('CDN-Cache-Control', 'max-age=300');   // някои CDN го четат
        $response->headers->set('Vary', $this->mergeVary($response->headers->get('Vary'), ['Accept', 'Accept-Encoding', 'Origin']));

        // Добавяме ETag, ако е безопасно (не за стрийм)
        if (!$isStream && !$response->headers->has('ETag')) {
            $etag = '"' . sha1($response->getContent()) . '"';
            $response->headers->set('ETag', $etag);

            // Ако клиентът прати If-None-Match и съвпада → 304
            if ($request->headers->get('If-None-Match') === $etag) {
                $response->setNotModified();
            }
        }

        return $response;
    }

    private function mergeVary(?string $existing, array $add): string
    {
        $current = array_filter(array_map('trim', explode(',', (string)$existing)));
        foreach ($add as $h) {
            if (!in_array($h, $current, true)) $current[] = $h;
        }
        return implode(', ', $current);
    }
}
