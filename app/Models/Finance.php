<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Finance extends Model
{
    use HasFactory;

    protected $fillable = [
        'data',
        'opis',
        'kwota',
        'kategoria',
        'status',
        'metoda_platnosci',
        'konto',
        'notatki',
        'source_file',
        'source_id',
    ];

    protected $casts = [
        'data' => 'date',
        'kwota' => 'decimal:2',
    ];

    /**
     * Scope do filtrowania po dacie
     */
    public function scopeByDate($query, $startDate, $endDate = null)
    {
        if ($endDate) {
            return $query->whereBetween('data', [$startDate, $endDate]);
        }
        return $query->where('data', '>=', $startDate);
    }

    /**
     * Scope do filtrowania po kategorii
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('kategoria', $category);
    }

    /**
     * Scope do filtrowania po statusie
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope do filtrowania po kwocie
     */
    public function scopeByAmount($query, $minAmount = null, $maxAmount = null)
    {
        if ($minAmount && $maxAmount) {
            return $query->whereBetween('kwota', [$minAmount, $maxAmount]);
        } elseif ($minAmount) {
            return $query->where('kwota', '>=', $minAmount);
        } elseif ($maxAmount) {
            return $query->where('kwota', '<=', $maxAmount);
        }
        return $query;
    }

    /**
     * Pobierz sumę wydatków w danym okresie
     */
    public static function getTotalExpenses($startDate, $endDate = null)
    {
        $query = static::query();
        return $query->byDate($startDate, $endDate)->sum('kwota');
    }

    /**
     * Pobierz wydatki pogrupowane po kategorii
     */
    public static function getExpensesByCategory($startDate, $endDate = null)
    {
        $query = static::query();
        return $query->byDate($startDate, $endDate)
            ->selectRaw('kategoria, SUM(kwota) as total')
            ->groupBy('kategoria')
            ->orderBy('total', 'desc')
            ->get();
    }
}
