<?php

namespace Tests\Unit\Services\Google;

use Tests\TestCase;
use App\Services\Google\ExcelService;
use App\Services\Google\GoogleDriveService;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelServiceTest extends TestCase
{
    private ExcelService $excelService;
    private GoogleDriveService $googleDriveService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->googleDriveService = Mockery::mock(GoogleDriveService::class);
        $this->excelService = new ExcelService($this->googleDriveService);
        
        // Upewnij się, że katalog temp istnieje
        Storage::makeDirectory('temp');
    }

    private function createTestSpreadsheet(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Nagłówki
        $sheet->setCellValue('A1', 'Data');
        $sheet->setCellValue('B1', 'Opis');
        $sheet->setCellValue('C1', 'Kwota');
        $sheet->setCellValue('D1', 'Kategoria');

        // Przykładowe dane
        $sheet->setCellValue('A2', '2025-08-01');
        $sheet->setCellValue('B2', 'Zakupy spożywcze Biedronka');
        $sheet->setCellValue('C2', '-156.78');
        $sheet->setCellValue('D2', 'Jedzenie');

        $sheet->setCellValue('A3', '2025-08-01');
        $sheet->setCellValue('B3', 'Netflix');
        $sheet->setCellValue('C3', '-45.00');
        $sheet->setCellValue('D3', 'Rozrywka');

        $tempPath = storage_path('app/temp/test.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return $tempPath;
    }

    public function testImportTransactionsFromSheet()
    {
        // Przygotowanie
        $user = User::factory()->create();
        Category::create(['name' => 'Jedzenie', 'user_id' => $user->id]);
        Category::create(['name' => 'Rozrywka', 'user_id' => $user->id]);
        
        $tempPath = $this->createTestSpreadsheet();
        $fileId = 'test123';

        $this->googleDriveService
            ->shouldReceive('downloadFile')
            ->once()
            ->with($fileId, Mockery::type('string'))
            ->andReturn(true);

        // Symulujemy pobranie pliku przez skopiowanie go
        $this->googleDriveService
            ->shouldReceive('downloadFile')
            ->andReturnUsing(function($fileId, $destPath) use ($tempPath) {
                copy($tempPath, $destPath);
                return true;
            });

        // Wykonanie
        $result = $this->excelService->importTransactionsFromSheet($fileId, $user);

        // Weryfikacja
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['imported_count']);
        $this->assertEmpty($result['errors']);

        // Sprawdź czy transakcje zostały poprawnie zapisane
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'description' => 'Zakupy spożywcze Biedronka',
            'amount' => -156.78,
            'transaction_date' => '2025-08-01',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'description' => 'Netflix',
            'amount' => -45.00,
            'transaction_date' => '2025-08-01',
        ]);

        // Sprawdź czy kategorie zostały poprawnie przypisane
        $jedzenie = Category::where('name', 'Jedzenie')->first();
        $rozrywka = Category::where('name', 'Rozrywka')->first();

        $this->assertDatabaseHas('transactions', [
            'description' => 'Zakupy spożywcze Biedronka',
            'category_id' => $jedzenie->id,
        ]);

        $this->assertDatabaseHas('transactions', [
            'description' => 'Netflix',
            'category_id' => $rozrywka->id,
        ]);

        // Sprzątanie
        unlink($tempPath);
    }

    public function testImportTransactionsWithInvalidData()
    {
        // Przygotowanie
        $user = User::factory()->create();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Nagłówki
        $sheet->setCellValue('A1', 'Data');
        $sheet->setCellValue('B1', 'Opis');
        $sheet->setCellValue('C1', 'Kwota');

        // Nieprawidłowe dane
        $sheet->setCellValue('A2', 'nieprawidłowa data');
        $sheet->setCellValue('B2', 'Test');
        $sheet->setCellValue('C2', 'nieprawidłowa kwota');

        $tempPath = storage_path('app/temp/test_invalid.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        $fileId = 'test123';

        $this->googleDriveService
            ->shouldReceive('downloadFile')
            ->andReturnUsing(function($fileId, $destPath) use ($tempPath) {
                copy($tempPath, $destPath);
                return true;
            });

        // Wykonanie
        $result = $this->excelService->importTransactionsFromSheet($fileId, $user);

        // Weryfikacja
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['imported_count']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Nieprawidłowy format daty', $result['errors'][0]);

        // Sprawdź czy żadne transakcje nie zostały zapisane
        $this->assertEquals(0, Transaction::count());

        // Sprzątanie
        unlink($tempPath);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
