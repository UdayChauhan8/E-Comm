<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    private array $brands = [
        'Nike', 'Adidas', 'Puma', 'Reebok', 'New Balance',
        'Converse', 'Vans', 'Asics', 'Saucony', 'Brooks',
    ];

    private array $models = [
        'Air Max', 'Ultra Boost', 'RS-X', 'Classic Leather', 'Fresh Foam',
        'Chuck Taylor', 'Old Skool', 'Gel-Nimbus', 'Ride', 'Ghost',
        'Air Force 1', 'Stan Smith', 'Suede', 'Nano', '990v5',
        'React', 'ZX 2K', 'Velocity', 'Floatride', 'Adrenaline',
    ];

    private array $colorOptions = [
        'Black', 'White', 'Red', 'Navy', 'Grey',
        'Blue', 'Green', 'Orange', 'Pink', 'Beige',
        'Brown', 'Yellow', 'Purple', 'Teal',
    ];

    private array $styleCategories = [
        'Running', 'Casual', 'Basketball', 'Training',
        'Lifestyle', 'Walking', 'Trail', 'Tennis',
    ];

    private array $sizes = ['6', '6.5', '7', '7.5', '8', '8.5', '9', '9.5', '10', '10.5', '11', '12'];

    private array $genders = ['Men', 'Women', 'Unisex'];

    public function definition(): array
    {
        $brand    = $this->faker->randomElement($this->brands);
        $model    = $this->faker->randomElement($this->models);
        $colorway = $this->faker->randomElement($this->colorOptions);
        $title    = "{$brand} {$model} {$colorway}";

        $selectedColors = $this->faker->randomElements(
            $this->colorOptions,
            $this->faker->numberBetween(2, 4)
        );

        $selectedSizes = $this->faker->randomElements(
            $this->sizes,
            $this->faker->numberBetween(5, 8)
        );
        sort($selectedSizes); 

        return [
            'title'          => $title,
            'slug'           => Str::slug($title) . '-' . $this->faker->unique()->numberBetween(100, 999),
            'description'    => $this->faker->paragraphs(2, true),
            'price'          => $this->faker->randomFloat(2, 49.99, 249.99),
            'colors'         => $selectedColors,
            'sizes'          => $selectedSizes,
            'gender'         => $this->faker->randomElement($this->genders),
            'style_category' => $this->faker->randomElement($this->styleCategories),
            'thumbnail'      => 'https://picsum.photos/seed/' . $this->faker->unique()->word() . '/400/400',
            'is_active'      => true,
        ];
    }
}