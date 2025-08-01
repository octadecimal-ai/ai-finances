<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('finances', function (Blueprint $table) {
            $table->id();
            $table->date('miesiac')->comment('Miesiąc (data pierwszego dnia)');
            $table->date('ostatni_dzien')->comment('Ostatni dzień miesiąca');
            $table->integer('dni_robocze')->nullable()->comment('Liczba dni roboczych');
            $table->decimal('urlop_swieta', 10, 2)->nullable()->comment('Urlop + święta');
            $table->decimal('nadgodziny', 10, 2)->nullable()->comment('Nadgodziny');
            $table->decimal('stawka_netto', 10, 2)->nullable()->comment('Stawka netto');
            $table->decimal('pensja_brutto', 10, 2)->nullable()->comment('Pensja brutto');
            $table->decimal('pit', 10, 2)->nullable()->comment('PIT');
            $table->decimal('vat', 10, 2)->nullable()->comment('VAT');
            $table->decimal('zus', 10, 2)->nullable()->comment('ZUS');
            $table->decimal('pensja', 10, 2)->nullable()->comment('Pensja netto');
            $table->decimal('inne_plus', 10, 2)->nullable()->comment('Inne +');
            $table->decimal('pozyczki', 10, 2)->nullable()->comment('Pożyczki');
            $table->decimal('zwrot_pozyczki', 10, 2)->nullable()->comment('Zwrot pożyczki');
            $table->decimal('potr_is24', 10, 2)->nullable()->comment('Potr IS24');
            $table->decimal('zosia', 10, 2)->nullable()->comment('Zosia');
            $table->decimal('mieszkanie', 10, 2)->nullable()->comment('Mieszkanie (10.x)');
            $table->decimal('kredyt_samochodowy', 10, 2)->nullable()->comment('Kredyt samochodowy');
            $table->decimal('oc', 10, 2)->nullable()->comment('OC');
            $table->decimal('play', 10, 2)->nullable()->comment('PLAY (14.x)');
            $table->decimal('internet', 10, 2)->nullable()->comment('Internet (16.x)');
            $table->decimal('woda', 10, 2)->nullable()->comment('Woda');
            $table->decimal('inne', 10, 2)->nullable()->comment('Inne');
            $table->decimal('us_santander', 10, 2)->nullable()->comment('US Santander');
            $table->decimal('vg_mc', 10, 2)->nullable()->comment('VG/MC');
            $table->decimal('samochod', 10, 2)->nullable()->comment('Samochód');
            $table->decimal('paliwo', 10, 2)->nullable()->comment('Paliwo');
            $table->decimal('remont', 10, 2)->nullable()->comment('Remont');
            $table->decimal('sprzatanie', 10, 2)->nullable()->comment('Sprzątanie');
            $table->decimal('hobby', 10, 2)->nullable()->comment('Hobby');
            $table->decimal('podroze', 10, 2)->nullable()->comment('Podróże');
            $table->decimal('wiola_zwrot_kasy', 10, 2)->nullable()->comment('Wiola - zwrot kasy');
            $table->decimal('allegro', 10, 2)->nullable()->comment('Allegro');
            $table->decimal('allegro_pay', 10, 2)->nullable()->comment('Allegro Pay');
            $table->decimal('macbook_santander', 10, 2)->nullable()->comment('MacBook Santander');
            $table->decimal('note_3_8_alior_mbank', 10, 2)->nullable()->comment('Note 3/8 + Alior / mBank (07.x)');
            $table->decimal('bph_silnik', 10, 2)->nullable()->comment('BPH silnik');
            $table->decimal('alior_maroko', 10, 2)->nullable()->comment('Alior Maroko');
            $table->decimal('alior_basia', 10, 2)->nullable()->comment('Alior Basia');
            $table->decimal('komp_dron_smartnej', 10, 2)->nullable()->comment('Komp, dron Smartnej (30.x)');
            $table->decimal('smartney_7k', 10, 2)->nullable()->comment('Smartney 7k');
            $table->decimal('ca_rower_mbank', 10, 2)->nullable()->comment('CA (rower) / mBank');
            $table->decimal('ca_wynajem_mbank', 10, 2)->nullable()->comment('CA (wynajem) (10.x)');
            $table->decimal('lux_med', 10, 2)->nullable()->comment('Lux Med');
            $table->decimal('multisport', 10, 2)->nullable()->comment('Multisport');
            $table->decimal('innogy', 10, 2)->nullable()->comment('Innogy');
            $table->decimal('mec_boguslawa', 10, 2)->nullable()->comment('Mec Bogusława');
            $table->decimal('terapia', 10, 2)->nullable()->comment('Terapia');
            $table->decimal('angielski', 10, 2)->nullable()->comment('Angielski');
            $table->decimal('xiaomi', 10, 2)->nullable()->comment('Xiaomi');
            $table->decimal('ca_eg', 10, 2)->nullable()->comment('CA [EG]');
            $table->decimal('millenium_eg', 10, 2)->nullable()->comment('Millenium [EG]');
            $table->decimal('egzekucja', 10, 2)->nullable()->comment('Egzekucja');
            $table->decimal('alior_piec_blacharz_remont', 10, 2)->nullable()->comment('Alior piec blacharz remont (20.x) / Velo (24.x)');
            $table->decimal('alior_konsolidacja', 10, 2)->nullable()->comment('Alior konsolidacja (18.x). CA Mama (20.x)');
            $table->decimal('wiola_plus', 10, 2)->nullable()->comment('Wiola +');
            $table->decimal('basia_plus_velo', 10, 2)->nullable()->comment('BASIA+ (Velo)');
            $table->decimal('basia_iphone', 10, 2)->nullable()->comment('BASIA (iPhone)');
            $table->decimal('suma_oplat', 10, 2)->nullable()->comment('Suma opłat');
            $table->decimal('zostaje', 10, 2)->nullable()->comment('Zostaje');
            $table->text('opis_innych')->nullable()->comment('Opis innych');
            $table->decimal('diy', 10, 2)->nullable()->comment('DIY');
            $table->decimal('wiek', 10, 2)->nullable()->comment('WIEK');
            $table->decimal('kredyty_i_pozyczki', 10, 2)->nullable()->comment('Kredyty i pożyczki');
            $table->decimal('mieszkanie_kategoria', 10, 2)->nullable()->comment('Mieszkanie');
            $table->decimal('p1', 10, 2)->nullable()->comment('=P1');
            $table->string('source_file')->nullable()->comment('Plik źródłowy');
            $table->string('source_id')->nullable()->comment('ID z pliku źródłowego');
            $table->timestamps();
            
            // Indeksy dla lepszej wydajności
            $table->index('miesiac');
            $table->index('suma_oplat');
            $table->index('zostaje');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finances');
    }
};
