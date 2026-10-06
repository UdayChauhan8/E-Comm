<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    private array $colorOptions = [
        'Black', 'White', 'Red', 'Navy', 'Grey',
        'Blue', 'Green', 'Orange', 'Pink', 'Beige',
    ];

    public function run(): void
    {
        Product::factory(50)->create()->each(function (Product $product) {

            foreach ($product->colors as $color) {
                $imageCount = rand(2, 3);

                for ($i = 0; $i < $imageCount; $i++) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'color' => $color,
                        'url' => 'https://picsum.photos/seed/'.$product->id.$color.$i.'/800/800',
                        'sort_order' => $i,
                    ]);
                }
            }
        });

        $this->command->info('50 shoe products seeded with images!');
    }
}
