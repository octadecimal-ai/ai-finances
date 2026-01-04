<?php

namespace App\Services\Import;

use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;
use Exception;

class InvoiceParserService
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Parsuje plik faktury (PDF lub CSV) i zwraca dane faktury
     */
    public function parseInvoice(string $filePath, string $sourceType = 'cursor'): array
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("Plik nie istnieje: {$filePath}");
            }

            if (!is_readable($filePath)) {
                throw new Exception("Plik nie jest czytelny: {$filePath}");
            }

            // Sprawdź czy to CSV (OVH)
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($extension === 'csv' && $sourceType === 'ovh') {
                return $this->parseInvoiceFromCsv($filePath, $sourceType);
            }

            // Domyślnie parsuj jako PDF
            $pdf = $this->parser->parseFile($filePath);
            $text = $pdf->getText();
            
            // Wyciągnij metadane
            $details = $pdf->getDetails();
            
            // Parsuj dane faktury w zależności od typu źródła
            $invoiceData = [
                'invoice_number' => $this->extractInvoiceNumber($text, $details, $sourceType),
                'invoice_date' => $this->extractDate($text, 'invoice_date', $sourceType),
                'issue_date' => $this->extractDate($text, 'issue_date', $sourceType),
                'due_date' => $this->extractDate($text, 'due_date', $sourceType),
                'seller_name' => $this->extractSellerName($text, $sourceType),
                'seller_tax_id' => $this->extractTaxId($text, 'seller', $sourceType),
                'seller_address' => $this->extractAddress($text, 'seller', $sourceType),
                'seller_email' => $this->extractEmail($text, 'seller', $sourceType),
                'seller_phone' => $this->extractPhone($text, 'seller', $sourceType),
                'seller_account_number' => $this->extractAccountNumber($text, 'seller', $sourceType),
                'buyer_name' => $this->extractBuyerName($text, $sourceType),
                'buyer_tax_id' => $this->extractTaxId($text, 'buyer', $sourceType),
                'buyer_address' => $this->extractAddress($text, 'buyer', $sourceType),
                'buyer_email' => $this->extractEmail($text, 'buyer', $sourceType),
                'buyer_phone' => $this->extractPhone($text, 'buyer', $sourceType),
                'subtotal' => $this->extractAmount($text, 'subtotal', $sourceType),
                'tax_amount' => $this->extractAmount($text, 'tax', $sourceType),
                'total_amount' => $this->extractAmount($text, 'total', $sourceType),
                'currency' => $this->extractCurrency($text, $sourceType),
                'payment_method' => $this->extractPaymentMethod($text, $sourceType),
                'items' => $this->extractItems($text, $sourceType),
                'metadata' => [
                    'raw_text' => substr($text, 0, 10000), // Pierwsze 10k znaków
                    'pdf_details' => $details,
                    'pages_count' => count($pdf->getPages()),
                ],
            ];

            Log::info('Invoice parsed successfully', [
                'file' => basename($filePath),
                'invoice_number' => $invoiceData['invoice_number'],
                'total_amount' => $invoiceData['total_amount'],
            ]);

            return $invoiceData;

        } catch (Exception $e) {
            Log::error('Invoice parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Wyciąga numer faktury
     */
    private function extractInvoiceNumber(string $text, array $details, string $sourceType = 'cursor'): ?string
    {
        $patterns = [];
        
        if ($sourceType === 'anthropic') {
            // Anthropic format: "Invoice numberWNUILWTJ-0001" (bez spacji)
            $patterns = [
                '/Invoice\s+number\s*([A-Z0-9\-]+)/i',
                '/Invoice\s+number([A-Z0-9\-]+)/i',  // Bez spacji
            ];
        } elseif ($sourceType === 'openai') {
            // OpenAI format: "Invoice number2D514660-0016" (bez spacji)
            $patterns = [
                '/Invoice\s+number\s*([A-Z0-9\-]+)/i',
                '/Invoice\s+number([A-Z0-9\-]+)/i',  // Bez spacji
            ];
        } elseif ($sourceType === 'google') {
            // Google format: "Numer faktury: GCPLD0004518686"
            $patterns = [
                '/Numer\s+faktury\s*:\s*([A-Z0-9]+)/i',
                '/Faktura\s+nr\s*:?\s*([A-Z0-9]+)/i',
            ];
        } else {
            // Cursor i inne formaty
            $patterns = [
                '/Numer\s+faktury\s+([A-Z0-9\-]+)/i',  // Cursor format: "Numer faktury 28D344B5-0001"
                '/Faktura\s+(?:nr|Nr|NR|#)?\s*:?\s*([A-Z0-9\/\-]+)/i',
                '/Invoice\s+(?:no|No|NO|#)?\s*:?\s*([A-Z0-9\/\-]+)/i',
                '/Nr\s+faktury\s*:?\s*([A-Z0-9\/\-]+)/i',
                '/FV\s+([A-Z0-9\/\-]+)/i',
                '/FA\s+([A-Z0-9\/\-]+)/i',
            ];
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Wyciąga datę (invoice_date, issue_date, due_date)
     */
    private function extractDate(string $text, string $type, string $sourceType = 'cursor'): ?string
    {
        $patterns = [];
        
        if ($sourceType === 'anthropic' || $sourceType === 'openai') {
            // Anthropic/OpenAI format: "Date of issue July 1, 2025" lub "Date due July 1, 2025"
            if ($type === 'issue_date') {
                $patterns = [
                    '/Date\s+of\s+issue\s+([A-Za-z]+\s+\d{1,2},\s+\d{4})/i',  // "Date of issue July 1, 2025" (ze spacją)
                    '/Date\s+of\s+issue([A-Za-z]+\s+\d{1,2},\s+\d{4})/i',  // "Date of issueJuly 1, 2025" (bez spacji)
                    '/Date\s+of\s+issue\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                ];
            } elseif ($type === 'due_date') {
                $patterns = [
                    '/Date\s+due\s+([A-Za-z]+\s+\d{1,2},\s+\d{4})/i',  // "Date due July 1, 2025" (ze spacją)
                    '/Date\s+due([A-Za-z]+\s+\d{1,2},\s+\d{4})/i',  // "Date dueJuly 1, 2025" (bez spacji)
                    '/Date\s+due\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                ];
            }
        } elseif ($sourceType === 'google') {
            // Google format: "31 gru 2025" (polskie skróty miesięcy)
            // Format w PDF: daty są w osobnych liniach po numerze faktury
            // Struktura: "GCPLD0004518686\n31 gru 2025\n1 sty 2026\n..."
            $lines = explode("\n", $text);
            if ($type === 'issue_date') {
                // Szukaj numeru faktury, następna linia to data sprzedaży, kolejna to data wystawienia
                foreach ($lines as $i => $line) {
                    if (preg_match('/GCPLD\d+/', $line) && isset($lines[$i+2])) {
                        $dateLine = trim($lines[$i+2]);
                        if (preg_match('/(\d{1,2}\s+[a-z]+\s+\d{4})/iu', $dateLine, $matches)) {
                            $date = $this->normalizeDate($matches[1], $sourceType);
                            if ($date) {
                                return $date;
                            }
                        }
                    }
                }
                $patterns = [
                    '/Data\s+wystawienia\s+faktury[^\d]*(\d{1,2}\s+[a-z]+\s+\d{4})/iu',
                ];
            } elseif ($type === 'due_date') {
                // Data sprzedaży jest w linii po numerze faktury
                foreach ($lines as $i => $line) {
                    if (preg_match('/GCPLD\d+/', $line) && isset($lines[$i+1])) {
                        $dateLine = trim($lines[$i+1]);
                        if (preg_match('/(\d{1,2}\s+[a-z]+\s+\d{4})/iu', $dateLine, $matches)) {
                            $date = $this->normalizeDate($matches[1], $sourceType);
                            if ($date) {
                                return $date;
                            }
                        }
                    }
                }
                $patterns = [
                    '/Data\s+sprzedaży[^\d]*(\d{1,2}\s+[a-z]+\s+\d{4})/iu',
                ];
            }
        } else {
            // Cursor i inne formaty
            if ($type === 'invoice_date') {
                $patterns = [
                    '/Data\s+wystawienia\s*:?\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                    '/Invoice\s+date\s*:?\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                    '/Data\s+faktury\s*:?\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                ];
            } elseif ($type === 'issue_date') {
                $patterns = [
                    '/Data\s+wystawienia(\d{1,2}\s+[^\s]+\s+\d{4})/iu',  // Cursor format: "Data wystawienia8 września 2025" (bez spacji, z polskimi znakami)
                    '/Data\s+wystawienia\s+(\d{1,2}\s+[^\s]+\s+\d{4})/iu',  // Cursor format: "Data wystawienia 15 marca 2025" (ze spacją)
                    '/Data\s+wystawienia\s*:?\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                    '/Issue\s+date\s*:?\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                ];
            } elseif ($type === 'due_date') {
                $patterns = [
                    '/Termin\s+płatności(\d{1,2}\s+[^\s]+\s+\d{4})/iu',  // Cursor format: "Termin płatności8 września 2025" (bez spacji, z polskimi znakami)
                    '/Termin\s+płatności\s+(\d{1,2}\s+[^\s]+\s+\d{4})/iu',  // Cursor format: "Termin płatności 15 marca 2025" (ze spacją)
                    '/Termin\s+płatności\s*:?\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                    '/Due\s+date\s*:?\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                    '/Data\s+płatności\s*:?\s*(\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{2,4})/i',
                ];
            }
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $date = $this->normalizeDate($matches[1], $sourceType);
                if ($date) {
                    return $date;
                }
            }
        }

        return null;
    }

    /**
     * Normalizuje datę do formatu Y-m-d
     */
    private function normalizeDate(string $dateString, string $sourceType = 'cursor'): ?string
    {
        $dateString = trim($dateString);
        
        if ($sourceType === 'anthropic' || $sourceType === 'openai') {
            // Anthropic/OpenAI format: "July 1, 2025"
            $englishMonths = [
                'january' => '01', 'february' => '02', 'march' => '03', 'april' => '04',
                'may' => '05', 'june' => '06', 'july' => '07', 'august' => '08',
                'september' => '09', 'october' => '10', 'november' => '11', 'december' => '12',
            ];
            
            // Sprawdź format "July 1, 2025"
            foreach ($englishMonths as $month => $monthNum) {
                if (preg_match('/^' . $month . '\s+(\d{1,2}),\s+(\d{4})$/i', $dateString, $matches)) {
                    $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $year = $matches[2];
                    return "$year-$monthNum-$day";
                }
            }
        } elseif ($sourceType === 'google') {
            // Google format: "31 gru 2025" (polskie skróty miesięcy)
            $polishMonthShort = [
                'sty' => '01', 'lut' => '02', 'mar' => '03', 'kwi' => '04',
                'maj' => '05', 'cze' => '06', 'lip' => '07', 'sie' => '08',
                'wrz' => '09', 'paź' => '10', 'lis' => '11', 'gru' => '12',
            ];
            
            // Sprawdź format "31 gru 2025"
            foreach ($polishMonthShort as $month => $monthNum) {
                if (preg_match('/^(\d{1,2})\s+' . $month . '\s+(\d{4})$/iu', $dateString, $matches)) {
                    $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $year = $matches[2];
                    return "$year-$monthNum-$day";
                }
            }
        } else {
            // Cursor format - polskie nazwy miesięcy
            $polishMonths = [
                'stycznia' => '01', 'lutego' => '02', 'marca' => '03', 'kwietnia' => '04',
                'maja' => '05', 'czerwca' => '06', 'lipca' => '07', 'sierpnia' => '08',
                'września' => '09', 'września' => '09', 'października' => '10', 'listopada' => '11', 'grudnia' => '12',
            ];
            
            // Sprawdź format "15 marca 2025" lub "8 września 2025"
            foreach ($polishMonths as $month => $monthNum) {
                if (preg_match('/^(\d{1,2})\s+' . preg_quote($month, '/') . '\s+(\d{4})$/iu', $dateString, $matches)) {
                    $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $year = $matches[2];
                    return "$year-$monthNum-$day";
                }
            }
        }
        
        // Różne formaty dat
        $formats = [
            'd.m.Y',
            'd-m-Y',
            'd/m/Y',
            'Y-m-d',
            'Y.m.d',
            'Y/m/d',
            'd.m.y',
            'd-m-y',
            'd/m/y',
        ];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * Wyciąga nazwę sprzedawcy
     */
    private function extractSellerName(string $text, string $sourceType = 'cursor'): ?string
    {
        if ($sourceType === 'anthropic') {
            // Anthropic format - sprzedawca to "Anthropic, PBC"
            if (preg_match('/\bAnthropic[^,\n]*(?:,?\s*PBC)?/i', $text, $matches)) {
                return trim($matches[0]);
            }
        } elseif ($sourceType === 'openai') {
            // OpenAI format - sprzedawca to "OpenAI, LLC"
            if (preg_match('/\bOpenAI[^,\n]*(?:,?\s*LLC)?/i', $text, $matches)) {
                return trim($matches[0]);
            }
        } elseif ($sourceType === 'google') {
            // Google format - sprzedawca to "Google Cloud Poland Sp. z o.o."
            if (preg_match('/Google\s+Cloud\s+Poland[^\n]+/i', $text, $matches)) {
                return trim($matches[0]);
            }
        } else {
            // Cursor format - sprzedawca to "Cursor" (firma)
            if (preg_match('/Faktura\s+([^\n]+)/i', $text, $matches)) {
                $lines = explode("\n", $text);
                foreach ($lines as $i => $line) {
                    $line = trim($line);
                    if (preg_match('/^Cursor$/i', $line)) {
                        return 'Cursor';
                    }
                }
            }
            
            // Fallback - szukaj "Cursor" jako nazwa firmy
            if (preg_match('/\bCursor\b/i', $text)) {
                return 'Cursor';
            }
        }
        
        $patterns = [
            '/Sprzedawca\s*:?\s*([^\n]+)/i',
            '/Seller\s*:?\s*([^\n]+)/i',
            '/Wystawca\s*:?\s*([^\n]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Wyciąga nazwę nabywcy
     */
    private function extractBuyerName(string $text, string $sourceType = 'cursor'): ?string
    {
        if ($sourceType === 'anthropic' || $sourceType === 'openai') {
            // Anthropic/OpenAI format - nabywca jest po "Bill to"
            if (preg_match('/Bill\s+to\s+([^\n]+)/i', $text, $matches)) {
                return trim($matches[1]);
            }
        } elseif ($sourceType === 'google') {
            // Google format - nabywca jest po "Wystawiono na:"
            if (preg_match('/Wystawiono\s+na:\s*([^\n]+)/i', $text, $matches)) {
                $name = trim($matches[1]);
                // Może być w następnej linii
                $lines = explode("\n", $text);
                $found = false;
                foreach ($lines as $i => $line) {
                    if (preg_match('/Wystawiono\s+na:/i', $line) && isset($lines[$i+1])) {
                        return trim($lines[$i+1]);
                    }
                }
                return $name;
            }
        } else {
            // Cursor format - nabywca jest po "Odbiorca faktury"
            if (preg_match('/Odbiorca\s+faktury\s+([^\n]+)/i', $text, $matches)) {
                return trim($matches[1]);
            }
        }
        
        $patterns = [
            '/Nabywca\s*:?\s*([^\n]+)/i',
            '/Buyer\s*:?\s*([^\n]+)/i',
            '/Odbiorca\s*:?\s*([^\n]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Wyciąga NIP (Tax ID)
     */
    private function extractTaxId(string $text, string $type, string $sourceType = 'cursor'): ?string
    {
        // Cursor format - NIP może być w formacie "PL VATPL1181427776"
        if ($type === 'buyer') {
            if (preg_match('/PL\s+VAT\s*PL\s*(\d+)/i', $text, $matches)) {
                return 'PL' . $matches[1];
            }
            if (preg_match('/PL\s+VATPL\s*(\d+)/i', $text, $matches)) {
                return 'PL' . $matches[1];
            }
        }
        
        // US EIN dla sprzedawcy
        if ($type === 'seller') {
            if (preg_match('/US\s+EIN\s+([\d\-]+)/i', $text, $matches)) {
                return $matches[1];
            }
        }
        
        $patterns = [
            '/NIP\s*:?\s*(\d{3}[-\s]?\d{3}[-\s]?\d{2}[-\s]?\d{2})/i',
            '/Tax\s+ID\s*:?\s*(\d{3}[-\s]?\d{3}[-\s]?\d{2}[-\s]?\d{2})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return preg_replace('/[-\s]/', '', $matches[1]);
            }
        }

        return null;
    }

    /**
     * Wyciąga adres
     */
    private function extractAddress(string $text, string $type, string $sourceType = 'cursor'): ?string
    {
        // Cursor format - adres jest po nazwie firmy
        if ($type === 'seller') {
            // Szukaj adresu sprzedawcy (po "Cursor" i przed "Odbiorca faktury")
            $beforeReceiver = preg_split('/Odbiorca\s+faktury/i', $text)[0] ?? '';
            $lines = explode("\n", $beforeReceiver);
            $addressLines = [];
            $collecting = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/\bCursor\b/i', $line)) {
                    $collecting = true;
                    continue;
                }
                if ($collecting && !empty($line)) {
                    // Zatrzymaj na emailu lub telefonie
                    if (preg_match('/@|^\+/', $line)) {
                        break;
                    }
                    // Zbierz linie adresu
                    if (!preg_match('/^(Strona|Faktura|Numer|Data|Termin)/i', $line)) {
                        $addressLines[] = $line;
                    }
                }
            }
            if (!empty($addressLines)) {
                return implode(', ', $addressLines);
            }
        } elseif ($type === 'buyer') {
            // Szukaj adresu nabywcy (po "Odbiorca faktury" i przed email/NIP)
            $afterReceiver = preg_split('/Odbiorca\s+faktury/i', $text)[1] ?? '';
            $lines = explode("\n", $afterReceiver);
            $addressLines = [];
            $collecting = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/Odbiorca\s+faktury/i', $line) || empty($addressLines)) {
                    $collecting = true;
                    continue;
                }
                if ($collecting && !empty($line)) {
                    // Zatrzymaj na emailu lub NIP
                    if (preg_match('/@|PL\s+VAT|NIP/i', $line)) {
                        break;
                    }
                    // Zbierz linie adresu
                    if (!preg_match('/^(Strona|Faktura|Numer|Data|Termin|Opis|Ilość|Kwota)/i', $line)) {
                        $addressLines[] = $line;
                    }
                }
            }
            if (!empty($addressLines)) {
                return implode(', ', $addressLines);
            }
        }
        
        // Fallback - standardowe parsowanie
        $patterns = [
            '/(' . ($type === 'seller' ? 'Sprzedawca' : 'Nabywca') . '.*?)(?=NIP|Tel|Email|$)/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $address = trim($matches[1]);
                // Usuń nazwę firmy z początku
                $address = preg_replace('/^(Sprzedawca|Nabywca|Seller|Buyer)\s*:?\s*/i', '', $address);
                return $address ?: null;
            }
        }

        return null;
    }

    /**
     * Wyciąga email
     */
    private function extractEmail(string $text, string $type, string $sourceType = 'cursor'): ?string
    {
        // Cursor format - email jest w linii z @
        $emails = [];
        if (preg_match_all('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $text, $matches)) {
            $emails = $matches[1];
        }
        
        if ($type === 'seller' && !empty($emails)) {
            // Sprzedawca (Cursor) - zwykle hi@cursor.com
            foreach ($emails as $email) {
                if (strpos($email, 'cursor.com') !== false) {
                    return $email;
                }
            }
            return $emails[0]; // Pierwszy email
        } elseif ($type === 'buyer' && !empty($emails)) {
            // Nabywca - zwykle email użytkownika
            foreach ($emails as $email) {
                if (strpos($email, 'cursor.com') === false) {
                    return $email;
                }
            }
            return $emails[count($emails) - 1]; // Ostatni email
        }
        
        // Fallback
        $pattern = '/Email\s*:?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i';
        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * Wyciąga telefon
     */
    private function extractPhone(string $text, string $type, string $sourceType = 'cursor'): ?string
    {
        // Cursor format - telefon jest w formacie "+1 831-425-9504"
        if ($type === 'seller') {
            // Szukaj telefonu sprzedawcy (po adresie, przed "Odbiorca faktury")
            $beforeReceiver = preg_split('/Odbiorca\s+faktury/i', $text)[0] ?? '';
            if (preg_match('/\+[\d\s\-\(\)]+/', $beforeReceiver, $matches)) {
                return trim($matches[0]);
            }
        }
        
        $patterns = [
            '/Tel\.?\s*:?\s*([+\d\s\-\(\)]+)/i',
            '/Phone\s*:?\s*([+\d\s\-\(\)]+)/i',
            '/Telefon\s*:?\s*([+\d\s\-\(\)]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Wyciąga numer konta
     */
    private function extractAccountNumber(string $text, string $type, string $sourceType = 'cursor'): ?string
    {
        $patterns = [
            '/Nr\s+konta\s*:?\s*(\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4})/i',
            '/Account\s+number\s*:?\s*(\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return preg_replace('/\s+/', '', $matches[1]);
            }
        }

        return null;
    }

    /**
     * Wyciąga kwotę (subtotal, tax, total)
     */
    private function extractAmount(string $text, string $type, string $sourceType = 'cursor'): float
    {
        $patterns = [];
        
        if ($sourceType === 'anthropic' || $sourceType === 'openai') {
            // Anthropic/OpenAI format
            if ($type === 'subtotal') {
                $patterns = [
                    '/Subtotal\s+\$([\d,\.]+)/i',  // "Subtotal	$5.00"
                    '/Total\s+excluding\s+tax\s+\$([\d,\.]+)/i',  // "Total excluding tax	$5.00"
                ];
            } elseif ($type === 'tax') {
                // OpenAI format - VAT może być w osobnej linii po "VAT - Poland"
                $lines = explode("\n", $text);
                foreach ($lines as $i => $line) {
                    if (preg_match('/VAT\s+-\s+Poland/i', $line) && isset($lines[$i+1])) {
                        $nextLine = trim($lines[$i+1]);
                        if (preg_match('/\$([\d,\.]+)/i', $nextLine, $matches)) {
                            $amount = $this->normalizeAmount($matches[1], $sourceType);
                            if ($amount !== null) {
                                return $amount;
                            }
                        }
                    }
                }
                $patterns = [
                    '/VAT\s+[^$]+\$([\d,\.]+)/i',  // "VAT - Poland (23% on $20.00) $4.60"
                    '/VAT\s+-\s+[^$]+\$([\d,\.]+)/i',  // "VAT - Poland (23% on $20.00) $4.60"
                    '/Tax\s+\([^)]+\)\s+\$([\d,\.]+)/i',  // "Tax (23% on $5.00)	$1.15"
                    '/Tax\s+\$([\d,\.]+)/i',
                ];
            } elseif ($type === 'total') {
                $patterns = [
                    '/Amount\s+due\s+\$([\d,\.]+)\s*USD/i',  // "Amount due	$6.15 USD"
                    '/\$([\d,\.]+)\s*USD\s+due/i',  // "$6.15 USD due July 1, 2025"
                    '/Total\s+\$([\d,\.]+)/i',  // "Total	$6.15"
                ];
            }
        } elseif ($sourceType === 'google') {
            // Google format - kwoty w PLN
            // Z analizy PDF wynika że:
            // Linia 42: "122,62 zł" (subtotal)
            // Linia 43: "28,20 zł" (tax)
            // Linia 44: "150,82 zł" (total)
            // Linia 45: "Suma częściowa w PLN"
            // Linia 46: "VAT (23%)"
            // Linia 47: "Łączna kwota w PLN"
            // Czyli kwoty są 3 linie PRZED nagłówkami
            $lines = explode("\n", $text);
            if ($type === 'subtotal') {
                $amount = null;
                foreach ($lines as $i => $line) {
                    if (preg_match('/Suma\s+częściowa\s+w\s+PLN/iu', $line) && $i >= 3 && isset($lines[$i-3])) {
                        $amountLine = trim($lines[$i-3]);
                        // Non-breaking space (U+00A0) może być w PDF, więc używamy [\s\xC2\xA0]*
                        if (preg_match('/([\d,\.]+)[\s\xC2\xA0]*zł/iu', $amountLine, $matches)) {
                            // Zapisz ostatnie dopasowanie (późniejsze wystąpienie w dokumencie jest właściwe)
                            $amount = $this->normalizeAmount($matches[1], $sourceType);
                        }
                    }
                }
                if ($amount !== null) {
                    return $amount;
                }
                $patterns = [
                    '/Suma\s+częściowa\s+w\s+PLN[^\d]*([\d,\.]+)[\s\xC2\xA0]*zł/iu',
                ];
            } elseif ($type === 'tax') {
                $amount = null;
                foreach ($lines as $i => $line) {
                    if (preg_match('/VAT\s*\([^)]+\)/iu', $line) && $i >= 3 && isset($lines[$i-3])) {
                        $amountLine = trim($lines[$i-3]);
                        if (preg_match('/([\d,\.]+)[\s\xC2\xA0]*zł/iu', $amountLine, $matches)) {
                            // Zapisz ostatnie dopasowanie
                            $amount = $this->normalizeAmount($matches[1], $sourceType);
                        }
                    }
                }
                if ($amount !== null) {
                    return $amount;
                }
                $patterns = [
                    '/VAT\s*\([^)]+\)[^\d]*([\d,\.]+)[\s\xC2\xA0]*zł/iu',
                ];
            } elseif ($type === 'total') {
                $amount = null;
                foreach ($lines as $i => $line) {
                    if (preg_match('/Łączna\s+kwota\s+w\s+PLN/iu', $line) && $i >= 3 && isset($lines[$i-3])) {
                        $amountLine = trim($lines[$i-3]);
                        if (preg_match('/([\d,\.]+)[\s\xC2\xA0]*zł/iu', $amountLine, $matches)) {
                            // Zapisz ostatnie dopasowanie
                            $amount = $this->normalizeAmount($matches[1], $sourceType);
                        }
                    }
                }
                if ($amount !== null) {
                    return $amount;
                }
                $patterns = [
                    '/Łączna\s+kwota\s+w\s+PLN[^\d]*([\d,\.]+)[\s\xC2\xA0]*zł/iu',
                ];
            }
        } else {
            // Cursor format
            if ($type === 'subtotal') {
                $patterns = [
                    '/Suma\s+częściowa\s+([\d\s,\.]+)\s*USD/i',  // Cursor format
                    '/Wartość\s+netto\s*:?\s*([\d\s,\.]+)/i',
                    '/Net\s+amount\s*:?\s*([\d\s,\.]+)/i',
                    '/Razem\s+netto\s*:?\s*([\d\s,\.]+)/i',
                ];
            } elseif ($type === 'tax') {
                $patterns = [
                    '/VAT\s*:?\s*([\d\s,\.]+)/i',
                    '/Podatek\s+VAT\s*:?\s*([\d\s,\.]+)/i',
                    '/Kwota\s+VAT\s*:?\s*([\d\s,\.]+)/i',
                ];
            } elseif ($type === 'total') {
                $patterns = [
                    '/Należna\s+kwota[\s\t\xC2\xA0]+([\d,\.]+)[\s\t\xC2\xA0]*USD/i',  // Cursor format: "Należna kwota\t20,00 USD" (z tabulatorem i spacją niełamliwą)
                    '/Kwota\s+([\d,\.]+)\s*USD\s+do\s+zapłaty/i',  // Cursor format: "Kwota 20,00 USD do zapłaty do 15 marca 2025"
                    '/Suma\s+([\d,\.]+)\s*USD/i',  // Cursor format: "Suma 20,00 USD"
                    '/Do\s+zapłaty\s*:?\s*([\d\s,\.]+)/i',
                    '/Total\s*:?\s*([\d\s,\.]+)/i',
                    '/Razem\s+brutto\s*:?\s*([\d\s,\.]+)/i',
                    '/Suma\s*:?\s*([\d\s,\.]+)/i',
                ];
            }
        }
        
        // Dla subtotal - jeśli total jest znane, użyj go jako subtotal (dla faktur bez VAT, np. Cursor US)
        // NIE stosuj dla Anthropic ani OpenAI (mają osobny subtotal i VAT)
        if ($type === 'subtotal' && !in_array($sourceType, ['anthropic', 'openai'])) {
            $total = $this->extractAmount($text, 'total', $sourceType);
            if ($total > 0) {
                return $total; // Dla faktur Cursor (US) nie ma VAT, więc subtotal = total
            }
        }


        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $amount = $this->normalizeAmount($matches[1], $sourceType);
                if ($amount !== null) {
                    return $amount;
                }
            }
        }

        return 0.0;
    }

    /**
     * Normalizuje kwotę
     */
    private function normalizeAmount(string $amountString): ?float
    {
        $amountString = trim($amountString);
        $amountString = preg_replace('/\s+/', '', $amountString);
        
        // Jeśli jest tylko przecinek (format "20,00"), zamień na kropkę
        if (preg_match('/^\d+,\d+$/', $amountString)) {
            $amountString = str_replace(',', '.', $amountString);
        }
        // Jeśli jest kropka i przecinek (format "1.234,56"), zamień na format amerykański
        elseif (preg_match('/^(\d{1,3}(?:\.\d{3})*),(\d{2})$/', $amountString, $matches)) {
            $amountString = str_replace('.', '', $amountString);
            $amountString = str_replace(',', '.', $amountString);
        }
        // Jeśli jest tylko przecinek jako separator dziesiętny (format "20,00")
        elseif (strpos($amountString, ',') !== false && strpos($amountString, '.') === false) {
            $amountString = str_replace(',', '.', $amountString);
        }

        $amount = (float) $amountString;
        return $amount > 0 ? $amount : null;
    }

    /**
     * Wyciąga walutę
     */
    private function extractCurrency(string $text, string $sourceType = 'cursor'): string
    {
        // Sprawdź różne waluty
        if (preg_match('/USD|\$/i', $text)) {
            return 'USD';
        }
        if (preg_match('/PLN|zł|zloty/i', $text)) {
            return 'PLN';
        }
        if (preg_match('/EUR|€|euro/i', $text)) {
            return 'EUR';
        }
        if (preg_match('/GBP|£|pound/i', $text)) {
            return 'GBP';
        }
        
        // Domyślnie w zależności od typu źródła
        if ($sourceType === 'google') {
            return 'PLN';
        }
        return 'USD';
    }

    /**
     * Wyciąga metodę płatności
     */
    private function extractPaymentMethod(string $text, string $sourceType = 'cursor'): ?string
    {
        $patterns = [
            '/Płatność\s*:?\s*([^\n]+)/i',
            '/Payment\s+method\s*:?\s*([^\n]+)/i',
            '/Forma\s+płatności\s*:?\s*([^\n]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Wyciąga pozycje faktury
     */
    private function extractItems(string $text, string $sourceType = 'cursor'): array
    {
        $items = [];
        
        if ($sourceType === 'anthropic' || $sourceType === 'openai') {
            // Anthropic/OpenAI format - pozycje są w formacie:
            // "Description	Qty Unit price Tax Amount
            // One-time credit purchase	1 $5.00 23% $5.00"
            // lub
            // "ChatGPT Plus Subscription
            // Feb 21 – Mar 21, 2024
            // 1 $20.00 23% $20.00"
            
            // Szukaj sekcji z pozycjami
            if (preg_match('/Description\s+Qty\s+Unit\s+price\s+Tax\s+Amount(.+?)(?:Subtotal|Total)/is', $text, $sectionMatch)) {
                $itemsSection = $sectionMatch[1];
                $lines = explode("\n", $itemsSection);
                $position = 1;
                
                $i = 0;
                while ($i < count($lines)) {
                    $line = trim($lines[$i]);
                    
                    // Pomiń puste linie
                    if (empty($line)) {
                        $i++;
                        continue;
                    }
                    
                    // Format: "ChatGPT Plus Subscription\nFeb 21 – Mar 21, 2024\n1 $20.00 23% $20.00"
                    // Sprawdź czy następna linia to data zakresu
                    $nextLine = isset($lines[$i+1]) ? trim($lines[$i+1]) : '';
                    $lineAfterNext = isset($lines[$i+2]) ? trim($lines[$i+2]) : '';
                    
                    // Jeśli następna linia to data zakresu, użyj linii po niej
                    // Format: "Feb 21 – Mar 21, 2024" lub "21 February 2024 – 21 March 2024"
                    // Znak "–" to UTF-8 en-dash (U+2013, \xe2\x80\x93)
                    if (preg_match('/^[A-Za-z]+\s+\d{1,2}\s+[\x{2013}\x{2014}-]\s+[A-Za-z]+\s+\d{1,2},?\s+\d{4}/u', $nextLine) && !empty($lineAfterNext)) {
                        // Nazwa produktu jest w $line, data w $nextLine, dane w $lineAfterNext
                        // Format: "1 $20.00 23% $20.00"
                        if (preg_match('/(\d+)\s+\$([\d,\.]+)\s+(\d+)%\s+\$([\d,\.]+)/i', $lineAfterNext, $matches)) {
                            $name = $line;
                            $quantity = (float) str_replace(',', '.', $matches[1]);
                            $unitPrice = $this->normalizeAmount($matches[2]) ?? 0;
                            $taxRate = (float) str_replace(',', '.', $matches[3]);
                            $grossAmount = $this->normalizeAmount($matches[4]) ?? 0;
                            
                            // Oblicz net i VAT
                            $netAmount = $grossAmount / (1 + ($taxRate / 100));
                            $taxAmount = $grossAmount - $netAmount;
                            
                            $items[] = [
                                'position' => $position++,
                                'name' => $name,
                                'description' => $nextLine, // Data zakresu jako opis
                                'quantity' => $quantity,
                                'unit' => 'szt',
                                'unit_price' => $unitPrice,
                                'net_amount' => $netAmount,
                                'tax_rate' => $taxRate,
                                'tax_amount' => $taxAmount,
                                'gross_amount' => $grossAmount,
                            ];
                            $i += 3; // Pomiń nazwę, datę i dane
                            continue;
                        }
                    }
                    
                    // Format bez daty zakresu: "One-time credit purchase	1 $5.00 23% $5.00"
                    if (preg_match('/^(.+?)\s+(\d+)\s+\$([\d,\.]+)\s+(\d+)%\s+\$([\d,\.]+)$/i', $line, $matches)) {
                        $name = trim($matches[1]);
                        $quantity = (float) str_replace(',', '.', $matches[2]);
                        $unitPrice = $this->normalizeAmount($matches[3]) ?? 0;
                        $taxRate = (float) str_replace(',', '.', $matches[4]);
                        $grossAmount = $this->normalizeAmount($matches[5]) ?? 0;
                        
                        // Oblicz net i VAT
                        $netAmount = $grossAmount / (1 + ($taxRate / 100));
                        $taxAmount = $grossAmount - $netAmount;
                        
                        $items[] = [
                            'position' => $position++,
                            'name' => $name,
                            'description' => null,
                            'quantity' => $quantity,
                            'unit' => 'szt',
                            'unit_price' => $unitPrice,
                            'net_amount' => $netAmount,
                            'tax_rate' => $taxRate,
                            'tax_amount' => $taxAmount,
                            'gross_amount' => $grossAmount,
                        ];
                    }
                    
                    $i++;
                }
            }
            
            return $items;
        } elseif ($sourceType === 'google') {
            // Google format - pozycje są w formacie:
            // "Subskrypcja	Opis Okres	Ilość Kwota (zł)
            // Google Workspace Business Plus	Użycie 1 gru - 19 gru	1	72,81"
            
            // Szukaj sekcji z pozycjami
            if (preg_match('/Subskrypcja\s+Opis\s+Okres\s+Ilość\s+Kwota[^\n]+\n(.+?)(?:Potrzebujesz|https|$)/is', $text, $sectionMatch)) {
                $itemsSection = $sectionMatch[1];
                $lines = explode("\n", $itemsSection);
                $position = 1;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Pomiń puste linie i nagłówki
                    if (empty($line) || preg_match('/^(Subskrypcja|Opis|Okres|Ilość|Kwota)/i', $line)) {
                        continue;
                    }
                    
                    // Format: "Google Workspace Business Plus	Użycie 1 gru - 19 gru	1	72,81"
                    // Nazwa, Opis, Ilość, Kwota
                    if (preg_match('/^([^\t]+)\t([^\t]+)\t(\d+)\t([\d,\.]+)$/i', $line, $matches)) {
                        $name = trim($matches[1]);
                        $description = trim($matches[2]);
                        $quantity = (float) str_replace(',', '.', $matches[3]);
                        $grossAmount = $this->normalizeAmount($matches[4]) ?? 0;
                        
                        // Dla Google VAT jest 23%
                        $taxRate = 23.0;
                        $netAmount = $grossAmount / (1 + ($taxRate / 100));
                        $taxAmount = $grossAmount - $netAmount;
                        $unitPrice = $quantity > 0 ? ($netAmount / $quantity) : 0;
                        
                        $items[] = [
                            'position' => $position++,
                            'name' => $name,
                            'description' => $description,
                            'quantity' => $quantity,
                            'unit' => 'szt',
                            'unit_price' => $unitPrice,
                            'net_amount' => $netAmount,
                            'tax_rate' => $taxRate,
                            'tax_amount' => $taxAmount,
                            'gross_amount' => $grossAmount,
                        ];
                    }
                }
            }
            
            return $items;
        }
        
        // Cursor format - pozycje są w formacie:
        // "Opis	Ilość Cena jednostkowa	Kwota"
        // "Cursor Pro
        // 15 mar 2025 – 15 kwi 2025
        // 1 20,00 USD 20,00 USD"
        
        // Szukaj sekcji z pozycjami
        if (preg_match('/Opis\s+Ilość\s+Cena\s+jednostkowa\s+Kwota(.+?)(?:Suma\s+częściowa|Suma|Należna)/is', $text, $sectionMatch)) {
            $itemsSection = $sectionMatch[1];
            
            // Najpierw spróbuj znaleźć pozycje w różnych formatach
            // Format 1: "nazwa produktu 1 20,00 USD 20,00 USD" (wszystko w jednej linii)
            // Format 2: "nazwa produktu\n1\t21,81 USD" (ilość i kwota w osobnej linii)
            // Format 3: "nazwa produktu\n1-7,27 USD" (ujemna kwota)
            
            // Format 1: wszystko w jednej linii
            if (preg_match_all('/(.+?)\s+(\d+)[\s\t\xC2\xA0]+([\d,\.]+)[\s\t\xC2\xA0]*USD[\s\t\xC2\xA0]+([\d,\.]+)[\s\t\xC2\xA0]*USD/i', $itemsSection, $allMatches, PREG_SET_ORDER)) {
                $position = 1;
                foreach ($allMatches as $match) {
                    $name = trim($match[1]);
                    $quantity = (float) str_replace(',', '.', $match[2]);
                    $unitPrice = $this->normalizeAmount($match[3]) ?? 0;
                    $netAmount = $this->normalizeAmount($match[4]) ?? 0;
                    
                    // Dla faktur Cursor (US) VAT jest 0%
                    $taxRate = 0.0;
                    $taxAmount = 0.0;
                    $grossAmount = $netAmount;
                    
                    $items[] = [
                        'position' => $position++,
                        'name' => $name,
                        'description' => null,
                        'quantity' => $quantity,
                        'unit' => 'szt',
                        'unit_price' => $unitPrice,
                        'net_amount' => $netAmount,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                        'gross_amount' => $grossAmount,
                    ];
                }
            }
            
            // Format 2 i 3: nazwa w jednej linii, data w następnej, ilość i kwota w kolejnej
            // Szukaj wzorca: nazwa produktu\n data\n ilość kwota USD
            // (tylko jeśli Format 1 nie znalazł pozycji)
            if (empty($items)) {
                $lines = explode("\n", $itemsSection);
                $position = 1;
                $i = 0;
                while ($i < count($lines)) {
                    $line = trim($lines[$i]);
                    
                    // Pomiń puste linie i nagłówki
                    if (empty($line) || preg_match('/^(Opis|Ilość|Cena|Kwota|Suma|Należna)/i', $line)) {
                        $i++;
                        continue;
                    }
                    
                // Sprawdź czy to linia z danymi pozycji (ilość i kwota)
                // Format: "1	21,81 USD" lub "1-7,27 USD"
                if (preg_match('/^(\d+)[\s\t\xC2\xA0]+([-]?[\d,\.]+)[\s\t\xC2\xA0]*USD/i', $line, $dataMatch)) {
                    // Szukaj nazwy produktu w poprzednich liniach
                    $name = null;
                    $description = null;
                    
                    // Sprawdź poprzednią linię - może być data
                    if ($i > 0) {
                        $prevLine = trim($lines[$i - 1]);
                        // Data może być w formacie "4 gru 2025 – 15 gru 2025" (ze skrótami miesięcy)
                        // Używamy flagi 'u' dla Unicode (en dash)
                        if (preg_match('/^\d{1,2}\s+\w+\s+\d{4}\s*[–\x{2013}\x{2014}-]\s*\d{1,2}\s+\w+\s+\d{4}/u', $prevLine)) {
                            $description = $prevLine;
                            // Nazwa produktu jest w linii przed datą (2 linie wcześniej)
                            if ($i > 1) {
                                $nameCandidate = trim($lines[$i - 2]);
                                // Sprawdź czy to nie jest nagłówek ani linia która ZACZYNA się od daty
                                if (!preg_match('/^(Opis|Ilość|Cena|Kwota|Suma|Należna)/i', $nameCandidate) && 
                                    !preg_match('/^\d{1,2}\s+\w+\s+\d{4}/', $nameCandidate)) {
                                    $name = $nameCandidate;
                                }
                            }
                        } else {
                            // Nazwa produktu jest w poprzedniej linii (bez daty)
                            if (!preg_match('/^(Opis|Ilość|Cena|Kwota|Suma|Należna)/i', $prevLine) && 
                                !preg_match('/^\d{1,2}\s+\w+\s+\d{4}/', $prevLine)) {
                                $name = $prevLine;
                            }
                        }
                    }
                    
                    if ($name && strlen($name) > 3) {
                        $quantity = (float) str_replace(',', '.', $dataMatch[1]);
                        $amountStr = $dataMatch[2];
                        $netAmount = $this->normalizeAmount($amountStr) ?? 0;
                        
                        // Jeśli kwota jest ujemna, użyj wartości bezwzględnej
                        if (strpos($amountStr, '-') !== false) {
                            $netAmount = abs($netAmount);
                        }
                        
                        // Cena jednostkowa = kwota / ilość
                        $unitPrice = $quantity > 0 ? ($netAmount / $quantity) : $netAmount;
                        
                        // Dla faktur Cursor (US) VAT jest 0%
                        $taxRate = 0.0;
                        $taxAmount = 0.0;
                        $grossAmount = $netAmount;
                        
                        $items[] = [
                            'position' => $position++,
                            'name' => $name,
                            'description' => $description,
                            'quantity' => $quantity,
                            'unit' => 'szt',
                            'unit_price' => $unitPrice,
                            'net_amount' => $netAmount,
                            'tax_rate' => $taxRate,
                            'tax_amount' => $taxAmount,
                            'gross_amount' => $grossAmount,
                        ];
                    }
                }
                    
                    $i++;
                }
            }
            
            // Jeśli nie znaleziono pozycji w formacie wieloliniowym, użyj fallback
            if (empty($items)) {
                // Fallback - parsowanie linia po linii
                $lines = explode("\n", $itemsSection);
                
                $currentItem = null;
                $currentDescription = null;
                $position = 1;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Pomiń puste linie
                    if (empty($line)) {
                        continue;
                    }
                    
                    // Sprawdź czy to linia z danymi pozycji (ilość, cena, kwota)
                    // Format: "1 20,00 USD 20,00 USD" (z tabulatorami i spacjami niełamliwymi)
                    if (preg_match('/^(\d+)[\s\t\xC2\xA0]+([\d,\.]+)[\s\t\xC2\xA0]*USD[\s\t\xC2\xA0]+([\d,\.]+)[\s\t\xC2\xA0]*USD/i', $line, $matches)) {
                        if ($currentItem) {
                            $quantity = (float) str_replace(',', '.', $matches[1]);
                            $unitPrice = $this->normalizeAmount($matches[2]) ?? 0;
                            $netAmount = $this->normalizeAmount($matches[3]) ?? 0;
                            
                            // Dla faktur Cursor (US) VAT jest 0%
                            $taxRate = 0.0;
                            $taxAmount = 0.0;
                            $grossAmount = $netAmount;
                            
                            $items[] = [
                                'position' => $position++,
                                'name' => $currentItem,
                                'description' => $currentDescription,
                                'quantity' => $quantity,
                                'unit' => 'szt',
                                'unit_price' => $unitPrice,
                                'net_amount' => $netAmount,
                                'tax_rate' => $taxRate,
                                'tax_amount' => $taxAmount,
                                'gross_amount' => $grossAmount,
                            ];
                            $currentItem = null;
                            $currentDescription = null;
                        }
                    } 
                    // Sprawdź czy to data (opis okresu)
                    elseif (preg_match('/^\d{1,2}\s+\w+\s+\d{4}\s*[–-]\s*\d{1,2}\s+\w+\s+\d{4}/', $line)) {
                        // To jest opis okresu (np. "15 mar 2025 – 15 kwi 2025")
                        $currentDescription = $line;
                    }
                    // Sprawdź czy to nazwa produktu/usługi
                    elseif (!preg_match('/^\d+\s+[\d,\.]+\s*USD/i', $line) && 
                            !preg_match('/^\d{1,2}\s+\w+\s+\d{4}/', $line) && // nie data
                            strlen($line) > 2) {
                        // To może być nazwa produktu (może być długa)
                        if (!$currentItem) {
                            $currentItem = $line;
                        }
                    }
                }
            }
        }
        
        // Fallback - standardowe parsowanie
        if (empty($items)) {
            $lines = explode("\n", $text);
            $inItemsSection = false;
            $position = 1;

            foreach ($lines as $line) {
                $line = trim($line);
                
                // Wykryj sekcję z pozycjami
                if (preg_match('/Lp|Nazwa|Ilość|Cena|Wartość|Netto|Brutto/i', $line)) {
                    $inItemsSection = true;
                    continue;
                }

                if ($inItemsSection) {
                    // Proste wykrywanie pozycji
                    if (preg_match('/(\d+)\s+(.+?)\s+(\d+[.,]?\d*)\s+([\d\s,\.]+)\s+([\d\s,\.]+)/', $line, $matches)) {
                        $netAmount = $this->normalizeAmount($matches[5]) ?? 0;
                        $taxRate = 23.0; // Domyślnie 23% - można wyciągnąć z faktury
                        $taxAmount = $netAmount * ($taxRate / 100);
                        $grossAmount = $netAmount + $taxAmount;
                        
                        $items[] = [
                            'position' => $position++,
                            'name' => trim($matches[2]),
                            'description' => null,
                            'quantity' => (float) str_replace(',', '.', $matches[3]),
                            'unit' => 'szt',
                            'unit_price' => $this->normalizeAmount($matches[4]) ?? 0,
                            'net_amount' => $netAmount,
                            'tax_rate' => $taxRate,
                            'tax_amount' => $taxAmount,
                            'gross_amount' => $grossAmount,
                        ];
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Parsuje plik CSV faktury OVH i zwraca dane faktury
     */
    public function parseInvoiceFromCsv(string $filePath, string $sourceType = 'ovh'): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("Plik CSV nie istnieje: {$filePath}");
        }

        $csvData = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            throw new Exception("Nie można otworzyć pliku CSV: {$filePath}");
        }

        // Pomiń nagłówek
        $header = fgetcsv($handle, 0, ';');
        
        if ($header === false) {
            fclose($handle);
            throw new Exception("Nie można odczytać nagłówka CSV");
        }

        // Mapowanie kolumn OVH
        $columnMap = [];
        foreach ($header as $index => $column) {
            $columnMap[trim(strtolower($column))] = $index;
        }

        // Sprawdź wymagane kolumny
        $requiredColumns = ['id_invoice', 'date', 'price_without_tax', 'price_with_tax'];
        foreach ($requiredColumns as $required) {
            if (!isset($columnMap[$required])) {
                fclose($handle);
                throw new Exception("Brakuje wymaganej kolumny: {$required}");
            }
        }

        // Parsuj wszystkie wiersze
        $invoices = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < count($header)) {
                continue; // Pomiń niepełne wiersze
            }

            $invoiceNumber = trim($row[$columnMap['id_invoice']] ?? '');
            if (empty($invoiceNumber)) {
                continue; // Pomiń wiersze bez numeru faktury
            }

            $dateStr = trim($row[$columnMap['date']] ?? '');
            $priceWithoutTax = (float) str_replace(',', '.', $row[$columnMap['price_without_tax']] ?? '0');
            $priceWithTax = (float) str_replace(',', '.', $row[$columnMap['price_with_tax']] ?? '0');
            $debtState = trim($row[$columnMap['debt_state']] ?? 'N/A');
            $url = trim($row[$columnMap['url']] ?? '');

            // Parsuj datę ISO 8601 (np. "2025-02-16T00:04:56Z")
            $issueDate = null;
            if (!empty($dateStr)) {
                try {
                    $date = new \DateTime($dateStr);
                    $issueDate = $date->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    Log::warning("Nie można sparsować daty OVH: {$dateStr}", ['error' => $e->getMessage()]);
                }
            }

            // Oblicz VAT
            $taxAmount = $priceWithTax - $priceWithoutTax;

            // Określ status płatności
            $paymentStatus = 'pending';
            if (strtoupper($debtState) === 'PAID' || $priceWithTax == 0) {
                $paymentStatus = 'paid';
            } elseif (strtoupper($debtState) === 'OVERDUE') {
                $paymentStatus = 'overdue';
            }

            $invoices[] = [
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $issueDate,
                'issue_date' => $issueDate,
                'due_date' => $issueDate, // OVH nie ma osobnej daty płatności
                'seller_name' => 'OVH',
                'seller_tax_id' => null,
                'seller_address' => null,
                'seller_email' => null,
                'seller_phone' => null,
                'seller_account_number' => null,
                'buyer_name' => null, // OVH CSV nie zawiera danych nabywcy
                'buyer_tax_id' => null,
                'buyer_address' => null,
                'buyer_email' => null,
                'buyer_phone' => null,
                'subtotal' => $priceWithoutTax,
                'tax_amount' => $taxAmount,
                'total_amount' => $priceWithTax,
                'currency' => 'EUR', // OVH używa EUR
                'payment_method' => null,
                'payment_status' => $paymentStatus,
                'metadata' => [
                    'debt_state' => $debtState,
                    'url' => $url,
                    'source_file' => basename($filePath),
                ],
                'items' => [], // OVH CSV nie zawiera pozycji faktury
            ];
        }

        fclose($handle);

        // Zwróć pierwszą fakturę (dla pojedynczego pliku CSV z wieloma fakturami, 
        // będziemy je przetwarzać osobno w komendzie)
        if (empty($invoices)) {
            throw new Exception("Nie znaleziono faktur w pliku CSV");
        }

        // Jeśli jest tylko jedna faktura, zwróć ją
        if (count($invoices) === 1) {
            return $invoices[0];
        }

        // Jeśli jest wiele faktur, zwróć pierwszą (komenda przetworzy wszystkie)
        return $invoices[0];
    }

    /**
     * Parsuje plik CSV OVH i zwraca wszystkie faktury
     */
    public function parseAllInvoicesFromCsv(string $filePath, string $sourceType = 'ovh'): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("Plik CSV nie istnieje: {$filePath}");
        }

        $csvData = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            throw new Exception("Nie można otworzyć pliku CSV: {$filePath}");
        }

        // Pomiń nagłówek
        $header = fgetcsv($handle, 0, ';');
        
        if ($header === false) {
            fclose($handle);
            throw new Exception("Nie można odczytać nagłówka CSV");
        }

        // Mapowanie kolumn OVH
        $columnMap = [];
        foreach ($header as $index => $column) {
            $columnMap[trim(strtolower($column))] = $index;
        }

        // Sprawdź wymagane kolumny
        $requiredColumns = ['id_invoice', 'date', 'price_without_tax', 'price_with_tax'];
        foreach ($requiredColumns as $required) {
            if (!isset($columnMap[$required])) {
                fclose($handle);
                throw new Exception("Brakuje wymaganej kolumny: {$required}");
            }
        }

        // Parsuj wszystkie wiersze
        $invoices = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < count($header)) {
                continue; // Pomiń niepełne wiersze
            }

            $invoiceNumber = trim($row[$columnMap['id_invoice']] ?? '');
            if (empty($invoiceNumber)) {
                continue; // Pomiń wiersze bez numeru faktury
            }

            $dateStr = trim($row[$columnMap['date']] ?? '');
            $priceWithoutTax = (float) str_replace(',', '.', $row[$columnMap['price_without_tax']] ?? '0');
            $priceWithTax = (float) str_replace(',', '.', $row[$columnMap['price_with_tax']] ?? '0');
            $debtState = trim($row[$columnMap['debt_state']] ?? 'N/A');
            $url = trim($row[$columnMap['url']] ?? '');

            // Parsuj datę ISO 8601 (np. "2025-02-16T00:04:56Z")
            $issueDate = null;
            if (!empty($dateStr)) {
                try {
                    $date = new \DateTime($dateStr);
                    $issueDate = $date->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    Log::warning("Nie można sparsować daty OVH: {$dateStr}", ['error' => $e->getMessage()]);
                }
            }

            // Oblicz VAT
            $taxAmount = $priceWithTax - $priceWithoutTax;

            // Określ status płatności
            $paymentStatus = 'pending';
            if (strtoupper($debtState) === 'PAID' || $priceWithTax == 0) {
                $paymentStatus = 'paid';
            } elseif (strtoupper($debtState) === 'OVERDUE') {
                $paymentStatus = 'overdue';
            }

            $invoices[] = [
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $issueDate,
                'issue_date' => $issueDate,
                'due_date' => $issueDate,
                'seller_name' => 'OVH',
                'seller_tax_id' => null,
                'seller_address' => null,
                'seller_email' => null,
                'seller_phone' => null,
                'seller_account_number' => null,
                'buyer_name' => null,
                'buyer_tax_id' => null,
                'buyer_address' => null,
                'buyer_email' => null,
                'buyer_phone' => null,
                'subtotal' => $priceWithoutTax,
                'tax_amount' => $taxAmount,
                'total_amount' => $priceWithTax,
                'currency' => 'EUR',
                'payment_method' => null,
                'payment_status' => $paymentStatus,
                'metadata' => [
                    'debt_state' => $debtState,
                    'url' => $url,
                    'source_file' => basename($filePath),
                ],
                'items' => [],
            ];
        }

        fclose($handle);

        return $invoices;
    }
}

