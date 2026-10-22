<?php

namespace App\Http\Middleware;

use App\Support\Region;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The screens were written with the taka sign (৳). An installation set to another currency
 * (Settings → Region & privacy) shows its own symbol instead, on every page and JSON answer.
 * Nothing changes for taka installations.
 */
class LocalCurrency
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $symbol = Region::currencySymbol();
        if ($symbol === '৳' || $response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }
        $type = (string) $response->headers->get('Content-Type');
        if ($type !== '' && !str_contains($type, 'text/html') && !str_contains($type, 'json')) {
            return $response;
        }
        $content = $response->getContent();
        if (is_string($content) && str_contains($content, '৳')) {
            $response->setContent(str_replace(['৳', '৳'], [$symbol, json_encode($symbol) !== false ? trim(json_encode($symbol), '"') : $symbol], $content));
        }

        return $response;
    }
}
