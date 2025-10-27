<?php

namespace Crater\Traits;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

trait ExchangeRateProvidersTrait
{
    public function getExchangeRate($filter, $baseCurrencyCode, $currencyCode)
    {
        switch ($filter['driver']) {
            case 'currency_freak':
                $url = "https://api.currencyfreaks.com/latest?apikey=".$filter['key'];

                $url = $url."&symbols={$currencyCode}"."&base={$baseCurrencyCode}";
                $response = Http::get($url)->json();

                if (array_key_exists('success', $response)) {
                    if ($response["success"] == false) {
                        return respondJson($response["error"]["message"], $response["error"]["message"]);
                    }
                }

                return response()->json([
                    'exchangeRate' => array_values($response["rates"]),
                ], 200);

                break;

            case 'currency_layer':
                $url = "http://api.currencylayer.com/live?access_key=".$filter['key']."&source={$baseCurrencyCode}&currencies={$currencyCode}";
                $response = Http::get($url)->json();

                if (array_key_exists('success', $response)) {
                    if ($response["success"] == false) {
                        return respondJson($response["error"]["info"], $response["error"]["info"]);
                    }
                }

                return response()->json([
                    'exchangeRate' => array_values($response['quotes']),
                ], 200);

                break;

            case 'open_exchange_rate':
                $url = "https://openexchangerates.org/api/latest.json?app_id=".$filter['key']."&base={$baseCurrencyCode}&symbols={$currencyCode}";
                $response = Http::get($url)->json();

                if (array_key_exists("error", $response)) {
                    return respondJson($response["message"], $response["description"]);
                }

                return response()->json([
                    'exchangeRate' => array_values($response["rates"]),
                ], 200);

                break;

            case 'currency_converter':
                $url = $this->getCurrencyConverterUrl($filter['driver_config']);
                $url = $url."/api/v7/convert?apiKey=".$filter['key'];

                $query = "{$baseCurrencyCode}_{$currencyCode}";
                $url = $url."&q={$query}"."&compact=y";
                $response = Http::get($url)->json();

                return response()->json([
                    'exchangeRate' => array_values($response[$query]),
                ], 200);

                break;

            case 'nbkr':
                // Национальный банк Кыргызстана - парсинг официального сайта
                $today = now()->format('d.m.Y');
                $url = "https://www.nbkr.kg/index1.jsp?item=1562&lang=RUS";
                
                try {
                    $html = Http::get($url)->body();
                    $rates = $this->parseNBKRRates($html);
                    
                    if (empty($rates)) {
                        return respondJson('Failed to parse exchange rates', 'Parser error');
                    }
                    
                    $baseCurrencyCode = strtoupper($baseCurrencyCode);
                    $currencyCode = strtoupper($currencyCode);

                    // Если базовая валюта - KGS
                    if ($baseCurrencyCode === 'KGS') {
                        $rate = $rates[$currencyCode] ?? null;
                        
                        if (!$rate) {
                            return respondJson("Currency {$currencyCode} not supported", 'Currency not found');
                        }
                        
                        return response()->json([
                            'exchangeRate' => [$rate],
                        ], 200);
                    }

                    // Если целевая валюта - KGS
                    if ($currencyCode === 'KGS') {
                        $baseRate = $rates[$baseCurrencyCode] ?? null;
                        
                        if (!$baseRate) {
                            return respondJson("Currency {$baseCurrencyCode} not supported", 'Currency not found');
                        }
                        
                        // Инвертируем курс
                        $rate = 1 / $baseRate;
                        
                        return response()->json([
                            'exchangeRate' => [$rate],
                        ], 200);
                    }

                    // Для конвертации между двумя валютами (не KGS)
                    $baseRate = $rates[$baseCurrencyCode] ?? null;
                    $targetRate = $rates[$currencyCode] ?? null;

                    if (!$baseRate || !$targetRate) {
                        return respondJson("One of the currencies not supported", 'Currency not found');
                    }

                    // Кросс-курс через KGS
                    $rate = $targetRate / $baseRate;

                    return response()->json([
                        'exchangeRate' => [$rate],
                    ], 200);
                    
                } catch (\Exception $e) {
                    \Log::error("NBKR parser error: " . $e->getMessage());
                    return respondJson('Failed to fetch rates from NBKR', 'Connection error');
                }

                break;
        }
    }

    public function getCurrencyConverterUrl($data)
    {
        switch ($data['type']) {
            case 'PREMIUM':
                return "https://api.currconv.com";

                break;

            case 'PREPAID':
                return "https://prepaid.currconv.com";

                break;

            case 'FREE':
                return "https://free.currconv.com";

                break;

            case 'DEDICATED':
                return $data['url'];

                break;
        }
    }

    public function getSupportedCurrencies($request)
    {
        $message = 'Please Enter Valid Provider Key.';
        $error = 'invalid_key';

        $server_message = 'Server not responding';
        $error_message = 'server_error';

        switch ($request->driver) {
            case 'currency_freak':
                $url = "https://api.currencyfreaks.com/currency-symbols";
                $response = Http::get($url)->json();
                $checkKey = $this->getUrl($request);

                if ($response == null || $checkKey == null) {
                    return respondJson($error_message, $server_message);
                }

                if (array_key_exists('success', $checkKey) && array_key_exists('error', $checkKey)) {
                    if ($checkKey['error']['status'] == 404) {
                        return respondJson($error, $message);
                    }
                }

                return response()->json(['supportedCurrencies' => array_keys($response)]);

                break;

            case 'currency_layer':
                $url = "http://api.currencylayer.com/list?access_key=".$request->key;
                $response = Http::get($url)->json();

                if ($response == null) {
                    return respondJson($error_message, $server_message);
                }

                if (array_key_exists('currencies', $response)) {
                    return response()->json(['supportedCurrencies' => array_keys($response['currencies'])]);
                }

                return respondJson($error, $message);

                break;

            case 'open_exchange_rate':
                $url = "https://openexchangerates.org/api/currencies.json";
                $response = Http::get($url)->json();
                $checkKey = $this->getUrl($request);

                if ($response == null || $checkKey == null) {
                    return respondJson($error_message, $server_message);
                }

                if (array_key_exists('error', $checkKey)) {
                    if ($checkKey['status'] == 401) {
                        return respondJson($error, $message);
                    }
                }

                return response()->json(['supportedCurrencies' => array_keys($response)]);

                break;

            case 'currency_converter':
                $response = $this->getUrl($request);

                if ($response == null) {
                    return respondJson($error_message, $server_message);
                }

                if (array_key_exists('results', $response)) {
                    return response()->json(['supportedCurrencies' => array_keys($response['results'])]);
                }

                return respondJson($error, $message);

                break;

            case 'nbkr':
                // Национальный банк Кыргызстана - парсинг официального сайта
                try {
                    $url = "https://www.nbkr.kg/index1.jsp?item=1562&lang=RUS";
                    $html = Http::get($url)->body();
                    $rates = $this->parseNBKRRates($html);
                    
                    if (empty($rates)) {
                        return respondJson($error_message, $server_message);
                    }
                    
                    // Возвращаем список всех поддерживаемых валют + KGS
                    $currencies = array_keys($rates);
                    $currencies[] = 'KGS'; // Добавляем сам KGS
                    
                    return response()->json(['supportedCurrencies' => $currencies]);
                    
                } catch (\Exception $e) {
                    \Log::error("NBKR getSupportedCurrencies error: " . $e->getMessage());
                    return respondJson($error_message, $server_message);
                }

                break;
        }
    }

    public function getUrl($request)
    {
        switch ($request->driver) {
            case 'currency_freak':
                $url = "https://api.currencyfreaks.com/latest?apikey=".$request->key."&symbols=INR&base=USD";

                return Http::get($url)->json();

                break;

            case 'currency_layer':
                $url = "http://api.currencylayer.com/live?access_key=".$request->key."&source=INR&currencies=USD";

                return Http::get($url)->json();

                break;

            case 'open_exchange_rate':
                $url = "https://openexchangerates.org/api/latest.json?app_id=".$request->key."&base=INR&symbols=USD";

                return Http::get($url)->json();

                break;

            case 'currency_converter':
                $url = $this->getCurrencyConverterUrl($request)."/api/v7/currencies?apiKey=".$request->key;

                return Http::get($url)->json();

                break;

            case 'nbkr':
                // Национальный банк Кыргызстана - бесплатный, без ключа
                $url = "https://www.nbkr.kg/index1.jsp?item=1562&lang=RUS";

                return Http::get($url)->body();

                break;
        }
    }
    
    /**
     * Парсинг курсов валют с официального сайта НБКР
     * 
     * @param string $html
     * @return array
     */
    private function parseNBKRRates($html)
    {
        try {
            $crawler = new Crawler($html);
            $rates = [];
            
            // Находим таблицу с курсами (содержит заголовок "Код по ИСО 4217")
            $crawler->filter('table')->each(function (Crawler $table) use (&$rates) {
                $hasISOHeader = $table->filter('td')->reduce(function (Crawler $node) {
                    return stripos($node->text(), 'Код по ИСО') !== false;
                })->count() > 0;
                
                if (!$hasISOHeader) {
                    return;
                }
                
                // Парсим строки таблицы (пропускаем заголовок)
                $table->filter('tr')->each(function (Crawler $row) use (&$rates) {
                    $cells = $row->filter('td');
                    
                    if ($cells->count() >= 3) {
                        $codeCell = $cells->eq(0)->text();
                        $rateCell = $cells->eq(2)->text();
                        
                        // Извлекаем код валюты (первые 3 символа)
                        $code = trim($codeCell);
                        
                        // Пропускаем заголовок
                        if (strlen($code) === 3 && ctype_alpha($code)) {
                            // Извлекаем курс и преобразуем запятую в точку
                            $rate = trim($rateCell);
                            $rate = str_replace(',', '.', $rate);
                            $rate = floatval($rate);
                            
                            if ($rate > 0) {
                                $rates[strtoupper($code)] = $rate;
                            }
                        }
                    }
                });
            });
            
            return $rates;
        } catch (\Exception $e) {
            \Log::error("NBKR HTML parsing error: " . $e->getMessage());
            return [];
        }
    }
}
