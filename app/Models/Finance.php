<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Finance extends Model
{
    use HasFactory;

    protected $fillable = [
        'miesiac',
        'ostatni_dzien',
        'dni_robocze',
        'urlop_swieta',
        'nadgodziny',
        'stawka_netto',
        'pensja_brutto',
        'pit',
        'vat',
        'zus',
        'pensja',
        'inne_plus',
        'pozyczki',
        'zwrot_pozyczki',
        'potr_is24',
        'zosia',
        'mieszkanie',
        'kredyt_samochodowy',
        'oc',
        'play',
        'internet',
        'woda',
        'inne',
        'us_santander',
        'vg_mc',
        'samochod',
        'paliwo',
        'remont',
        'sprzatanie',
        'hobby',
        'podroze',
        'wiola_zwrot_kasy',
        'allegro',
        'allegro_pay',
        'macbook_santander',
        'note_3_8_alior_mbank',
        'bph_silnik',
        'alior_maroko',
        'alior_basia',
        'komp_dron_smartnej',
        'smartney_7k',
        'ca_rower_mbank',
        'ca_wynajem_mbank',
        'lux_med',
        'multisport',
        'innogy',
        'mec_boguslawa',
        'terapia',
        'angielski',
        'xiaomi',
        'ca_eg',
        'millenium_eg',
        'egzekucja',
        'alior_piec_blacharz_remont',
        'alior_konsolidacja',
        'wiola_plus',
        'basia_plus_velo',
        'basia_iphone',
        'suma_oplat',
        'zostaje',
        'opis_innych',
        'diy',
        'wiek',
        'kredyty_i_pozyczki',
        'mieszkanie_kategoria',
        'p1',
        'source_file',
        'source_id',
    ];

    protected $casts = [
        'miesiac' => 'date',
        'ostatni_dzien' => 'date',
        'dni_robocze' => 'integer',
        'urlop_swieta' => 'decimal:2',
        'nadgodziny' => 'decimal:2',
        'stawka_netto' => 'decimal:2',
        'pensja_brutto' => 'decimal:2',
        'pit' => 'decimal:2',
        'vat' => 'decimal:2',
        'zus' => 'decimal:2',
        'pensja' => 'decimal:2',
        'inne_plus' => 'decimal:2',
        'pozyczki' => 'decimal:2',
        'zwrot_pozyczki' => 'decimal:2',
        'potr_is24' => 'decimal:2',
        'zosia' => 'decimal:2',
        'mieszkanie' => 'decimal:2',
        'kredyt_samochodowy' => 'decimal:2',
        'oc' => 'decimal:2',
        'play' => 'decimal:2',
        'internet' => 'decimal:2',
        'woda' => 'decimal:2',
        'inne' => 'decimal:2',
        'us_santander' => 'decimal:2',
        'vg_mc' => 'decimal:2',
        'samochod' => 'decimal:2',
        'paliwo' => 'decimal:2',
        'remont' => 'decimal:2',
        'sprzatanie' => 'decimal:2',
        'hobby' => 'decimal:2',
        'podroze' => 'decimal:2',
        'wiola_zwrot_kasy' => 'decimal:2',
        'allegro' => 'decimal:2',
        'allegro_pay' => 'decimal:2',
        'macbook_santander' => 'decimal:2',
        'note_3_8_alior_mbank' => 'decimal:2',
        'bph_silnik' => 'decimal:2',
        'alior_maroko' => 'decimal:2',
        'alior_basia' => 'decimal:2',
        'komp_dron_smartnej' => 'decimal:2',
        'smartney_7k' => 'decimal:2',
        'ca_rower_mbank' => 'decimal:2',
        'ca_wynajem_mbank' => 'decimal:2',
        'lux_med' => 'decimal:2',
        'multisport' => 'decimal:2',
        'innogy' => 'decimal:2',
        'mec_boguslawa' => 'decimal:2',
        'terapia' => 'decimal:2',
        'angielski' => 'decimal:2',
        'xiaomi' => 'decimal:2',
        'ca_eg' => 'decimal:2',
        'millenium_eg' => 'decimal:2',
        'egzekucja' => 'decimal:2',
        'alior_piec_blacharz_remont' => 'decimal:2',
        'alior_konsolidacja' => 'decimal:2',
        'wiola_plus' => 'decimal:2',
        'basia_plus_velo' => 'decimal:2',
        'basia_iphone' => 'decimal:2',
        'suma_oplat' => 'decimal:2',
        'zostaje' => 'decimal:2',
        'diy' => 'decimal:2',
        'wiek' => 'decimal:2',
        'kredyty_i_pozyczki' => 'decimal:2',
        'mieszkanie_kategoria' => 'decimal:2',
        'p1' => 'decimal:2',
    ];

    /**
     * Scope do filtrowania po miesiącu
     */
    public function scopeByMonth($query, $month)
    {
        return $query->where('miesiac', $month);
    }

    /**
     * Scope do filtrowania po roku
     */
    public function scopeByYear($query, $year)
    {
        return $query->whereYear('miesiac', $year);
    }

    /**
     * Pobierz sumę wydatków w danym miesiącu
     */
    public static function getTotalExpenses($month)
    {
        return static::where('miesiac', $month)->sum('suma_oplat');
    }

    /**
     * Pobierz średnie miesięczne wydatki
     */
    public static function getAverageMonthlyExpenses()
    {
        return static::avg('suma_oplat');
    }

    /**
     * Pobierz wydatki pogrupowane po miesiącu
     */
    public static function getMonthlyExpensesByCategory()
    {
        return static::selectRaw('YEAR(miesiac) as year, MONTH(miesiac) as month, SUM(suma_oplat) as total')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();
    }
}
