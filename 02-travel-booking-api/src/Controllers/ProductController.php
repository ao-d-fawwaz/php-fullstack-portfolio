<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Http;
use App\Core\HttpException;
use App\Models\Product;

final class ProductController
{
    public function __construct(private Product $products)
    {
    }

    public function index(): void
    {
        Http::json(['status' => 'success', 'data' => $this->products->allActive()]);
    }

    public function show(array $params): void
    {
        $product = $this->products->findByCode($params['code']);
        if (!$product) {
            throw new HttpException(404, 'Product not found');
        }
        Http::json(['status' => 'success', 'data' => $product]);
    }
}
