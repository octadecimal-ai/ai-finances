<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Model<ExchangeRate>
 */
class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'currency_code',
        'rate',
        'table_number',
        'full_table_number',
    ];

    protected $casts = [
        'date' => 'date',
        'rate' => 'decimal:6',
    ];

    /**
     * Scope: kursy dla danej waluty
     * 
     * @param Builder<ExchangeRate> $query
     * @param string $currencyCode
     * @return Builder<ExchangeRate>
     */
    public function scopeForCurrency(Builder $query, string $currencyCode): Builder
    {
        return $query->where('currency_code', strtoupper($currencyCode));
    }

    /**
     * Scope: kursy dla danego zakresu dat
     * 
     * @param Builder<ExchangeRate> $query
     * @param string $startDate
     * @param string|null $endDate
     * @return Builder<ExchangeRate>
     */
    public function scopeForDateRange(Builder $query, string $startDate, ?string $endDate = null): Builder
    {
        $query->where('date', '>=', $startDate);
        
        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Scope: kursy dla danego roku
     * 
     * @param Builder<ExchangeRate> $query
     * @param int $year
     * @return Builder<ExchangeRate>
     */
    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->whereYear('date', $year);
    }

    /**
     * Pobierz najnowszy kurs dla danej waluty
     * 
     * @param string $currencyCode
     * @param string|null $date
     * @return ExchangeRate|null
     */
    public static function getLatestRate(string $currencyCode, ?string $date = null): ?self
    {
        $query = static::forCurrency($currencyCode);
        
        if ($date) {
            $query->where('date', '<=', $date);
        }
        
        return $query->orderBy('date', 'desc')->first();
    }

    /**
     * Pobierz kurs dla danej waluty i daty
     * 
     * @param string $currencyCode
     * @param string $date
     * @return ExchangeRate|null
     */
    public static function getRateForDate(string $currencyCode, string $date): ?self
    {
        return static::forCurrency($currencyCode)
            ->where('date', $date)
            ->first();
    }
}
