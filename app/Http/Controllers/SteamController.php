<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Helpers\Helper;

class SteamController extends Controller
{
    /**
     * A currency code => currency name array of available Steam currencies
     *
     * @var array
     */
    private $currencies = [
        "ae" => "UAE Dirham",
        "az" => "CIS - U.S. Dollar",
        "br" => "Brazilian Real",
        "ca" => "Canadian Dollar",
        "ch" => "Swiss Franc",
        "cl" => "Chilean Peso",
        "cn" => "Chinese Yuan",
        "co" => "Colombian Peso",
        "eu" => "Euro",
        "hk" => "Hong Kong Dollar",
        "id" => "Indonesian Rupiah",
        "in" => "Indian Rupee",
        "jp" => "Japanese Yen",
        "kr" => "South Korean Won",
        "mx" => "Mexican Peso",
        "my" => "Malaysian Ringgit",
        "no" => "Norwegian Krone",
        "nz" => "New Zealand Dollar",
        "pe" => "Peruvian Nuevo Sol",
        "ph" => "Philippine Peso",
        "ru" => "Russian Ruble",
        "sa" => "Saudi Riyal",
        "sg" => "Singapore Dollar",
        "th" => "Thai Baht",
        "tr" => "Turkish Lira",
        "tw" => "Taiwan Dollar",
        "uk" => "British Pound",
        "us" => "U.S. Dollar",
        "za" => "South African Rand"
    ];

    /**
     * Array of standard HTTP headers to return back to the client
     *
     * @var array
     */
    private $headers = [
        'Access-Control-Allow-Origin' => '*'
    ];

    /**
     * Returns a JSON object with "currency code" => "currency name" for available Steam currencies.
     *
     * @param  Request $request
     * @return Response
     */
    public function listCurrencies(Request $request)
    {
        $currencies = $this->currencies;
        if ($request->wantsJson()) {
            return Helper::json($currencies);
        }

        $list = "# Currency code - Currency name" . PHP_EOL;
        foreach($currencies as $code => $name) {
            $list .= $code . " - " . $name . PHP_EOL;
        }
        return Helper::text($list);
    }

    /**
     * Returns a response with information about a game, specified by search.
     *
     * @param  Request $request
     * @return Response
     */
    public function gameInfoBySearch(Request $request)
    {
        $json = $request->wantsJson();
        $search = trim($request->input('search', ''));
        $cc = strtolower($request->input('cc', 'us'));
        $strict = $request->input('strict', false);
        $emptySpace = $request->input('empty', false);
        $error = null;

        if (empty($search)) {
            $error = 'Missing parameter: search';
        }

        if (empty($this->currencies[$cc])) {
            $error = 'Invalid currency code specified';
        }

        // Pass error back before making a request to API, if it's set
        if (!empty($error)) {
            if ($json) {
                return Helper::json(['error' => $error], 404);
            }

            return Helper::text($error);
        }

        $search = urlencode($search);
        $cc = strtoupper($cc); // Apparently the storefront API returns LESS data with lowercase currency code... not sure why
        $requestUrl = 'http://store.steampowered.com/api/storesearch/?term=' . $search . '&cc=' . $cc;
        $data = Helper::get($requestUrl);
        if (!isset($data['total']) || $data['total'] === 0) {

            $data = Helper::get($requestUrl); // Re-request the information, as the storefront API is sometimes 'hit or miss'.
            if (!isset($data['total']) || $data['total'] === 0) {
                $error = 'No results found';
                if ($json) {
                    return Helper::json(['error' => $error], 404);
                }

                // $emptySpace is specifically for Nightbot and others, where it will just send something 'empty' instead of an error.
                return Helper::text(($emptySpace ? PHP_EOL : $error));
            }
        }

        $game = null;
        foreach ($data['items'] as $g) {
            if (strtolower($search) === strtolower($g['name'])) {
                $game = $g;
                break;
            }
        }

        if (empty($game)) {
            $game = $data['items'][0]; // fall back to first item
        }

        if ($strict) {
            if (strtolower($search) !== strtolower($game['name'])) {
                $error = '[Strict] No matching games found';
                if ($json) {
                    return Helper::json(['error' => $error], 404);
                }

                return Helper::text($error);
            }
        }

        $url = 'http://store.steampowered.com/app/' . $game['id'] . '/';
        if ($json) {
            $game['url'] = $url;
            return Helper::json($game);
        }

        $values = [$game['name'], $url];

        if (isset($game['price'])) {
            $price = $game['price'];
            $values[] = ($price['final'] / 100) . " " . $price['currency'];
        }

        return Helper::text(implode($values, " - "));
    }
}
