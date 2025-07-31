<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Jedzenie',
                'description' => 'Wydatki na jedzenie i napoje',
                'color' => '#FF6B6B',
                'icon' => 'utensils',
                'is_default' => true,
            ],
            [
                'name' => 'Transport',
                'description' => 'Wydatki na transport i komunikację',
                'color' => '#4ECDC4',
                'icon' => 'car',
                'is_default' => true,
            ],
            [
                'name' => 'Zakupy',
                'description' => 'Wydatki na zakupy i ubrania',
                'color' => '#45B7D1',
                'icon' => 'shopping-bag',
                'is_default' => true,
            ],
            [
                'name' => 'Rachunki',
                'description' => 'Rachunki i opłaty',
                'color' => '#96CEB4',
                'icon' => 'file-invoice',
                'is_default' => true,
            ],
            [
                'name' => 'Zdrowie',
                'description' => 'Wydatki na zdrowie i medycynę',
                'color' => '#FFEAA7',
                'icon' => 'heartbeat',
                'is_default' => true,
            ],
            [
                'name' => 'Edukacja',
                'description' => 'Wydatki na edukację i szkolenia',
                'color' => '#DDA0DD',
                'icon' => 'graduation-cap',
                'is_default' => true,
            ],
            [
                'name' => 'Rozrywka',
                'description' => 'Wydatki na rozrywkę i hobby',
                'color' => '#FFB347',
                'icon' => 'gamepad',
                'is_default' => true,
            ],
            [
                'name' => 'Inne',
                'description' => 'Inne wydatki',
                'color' => '#C0C0C0',
                'icon' => 'ellipsis-h',
                'is_default' => true,
            ],
            // Subcategories for Jedzenie
            [
                'name' => 'Restauracje',
                'description' => 'Wydatki w restauracjach',
                'color' => '#FF8A80',
                'icon' => 'utensils',
                'parent_id' => 1,
                'is_default' => false,
            ],
            [
                'name' => 'Sklepy spożywcze',
                'description' => 'Zakupy w sklepach spożywczych',
                'color' => '#FFAB91',
                'icon' => 'shopping-cart',
                'parent_id' => 1,
                'is_default' => false,
            ],
            // Subcategories for Transport
            [
                'name' => 'Paliwo',
                'description' => 'Wydatki na paliwo',
                'color' => '#80CBC4',
                'icon' => 'gas-pump',
                'parent_id' => 2,
                'is_default' => false,
            ],
            [
                'name' => 'Transport publiczny',
                'description' => 'Bilety komunikacji miejskiej',
                'color' => '#9FA8DA',
                'icon' => 'bus',
                'parent_id' => 2,
                'is_default' => false,
            ],
            [
                'name' => 'Taksówki',
                'description' => 'Wydatki na taksówki',
                'color' => '#90A4AE',
                'icon' => 'taxi',
                'parent_id' => 2,
                'is_default' => false,
            ],
            // Subcategories for Zakupy
            [
                'name' => 'Ubrania',
                'description' => 'Wydatki na ubrania',
                'color' => '#81C784',
                'icon' => 'tshirt',
                'parent_id' => 3,
                'is_default' => false,
            ],
            [
                'name' => 'Elektronika',
                'description' => 'Wydatki na elektronikę',
                'color' => '#64B5F6',
                'icon' => 'laptop',
                'parent_id' => 3,
                'is_default' => false,
            ],
            // Subcategories for Rachunki
            [
                'name' => 'Prąd',
                'description' => 'Rachunki za prąd',
                'color' => '#FFD54F',
                'icon' => 'bolt',
                'parent_id' => 4,
                'is_default' => false,
            ],
            [
                'name' => 'Gaz',
                'description' => 'Rachunki za gaz',
                'color' => '#FF8A65',
                'icon' => 'fire',
                'parent_id' => 4,
                'is_default' => false,
            ],
            [
                'name' => 'Internet',
                'description' => 'Rachunki za internet',
                'color' => '#4FC3F7',
                'icon' => 'wifi',
                'parent_id' => 4,
                'is_default' => false,
            ],
            [
                'name' => 'Telefon',
                'description' => 'Rachunki za telefon',
                'color' => '#81C784',
                'icon' => 'phone',
                'parent_id' => 4,
                'is_default' => false,
            ],
            // Subcategories for Zdrowie
            [
                'name' => 'Leki',
                'description' => 'Wydatki na leki',
                'color' => '#FFB74D',
                'icon' => 'pills',
                'parent_id' => 5,
                'is_default' => false,
            ],
            [
                'name' => 'Lekarz',
                'description' => 'Wizyty u lekarza',
                'color' => '#F06292',
                'icon' => 'user-md',
                'parent_id' => 5,
                'is_default' => false,
            ],
            // Subcategories for Rozrywka
            [
                'name' => 'Kino',
                'description' => 'Wydatki na kino',
                'color' => '#BA68C8',
                'icon' => 'film',
                'parent_id' => 7,
                'is_default' => false,
            ],
            [
                'name' => 'Sport',
                'description' => 'Wydatki na sport',
                'color' => '#4DB6AC',
                'icon' => 'dumbbell',
                'parent_id' => 7,
                'is_default' => false,
            ],
            [
                'name' => 'Streaming',
                'description' => 'Subskrypcje streamingowe',
                'color' => '#FF7043',
                'icon' => 'play-circle',
                'parent_id' => 7,
                'is_default' => false,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::updateOrCreate(
                ['name' => $categoryData['name']],
                $categoryData
            );
        }
    }
} 