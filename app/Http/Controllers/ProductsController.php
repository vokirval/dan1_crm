<?php
namespace App\Http\Controllers;


use Inertia\Inertia;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\ProductVariation;


class ProductsController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 500);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        // Поля, доступные для сортировки
        $sortableFields = ['name', 'sku', 'stock', 'price', 'discounted_price', 'status', 'created_at'];

        if (!in_array($sortBy, $sortableFields)) {
            $sortBy = 'created_at';
        }

        // Получаем список продуктов с пагинацией и сортировкой
        $products = Product::with('category')
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends([
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ]);

        $categories = Category::all();

        return Inertia::render('Products/Index', [
            'data' => $products,
            'categories' => $categories,
            'filters' => [
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
        ]);
    }

    public function getAll()
    {
        $products = Product::select('id', 'name')->get();
        return response()->json(['products' => $products]);
    }

    public function getVariations($productId)
    {
        // Проверяем, существует ли товар
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Получаем вариации и формируем название с атрибутами
        $variations = ProductVariation::where('product_id', $productId)
            ->with('attributes:product_variation_id,attribute_name,attribute_value')
            ->select('id', 'sku')
            ->get()
            ->map(function ($variation) {
                $attributesString = $variation->attributes->map(function ($attr) {
                    return "{$attr->attribute_name}: {$attr->attribute_value}";
                })->join(', ');

                $variation->name = $attributesString ?: 'Без атрибутів';
                return $variation;
            });

        return response()->json(['variations' => $variations]);
    }



    /**
     * Show the single product for editing.
     */
    public function single($id)
    {
        $product = Product::with('variations.attributes')->findOrFail($id);
        $categories = Category::all();

        return Inertia::render('Products/Single', [
            'product' => $product,
            'categories' => $categories,
        ]);

        
    }

    /**
     * Update the specified product in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'sku' => 'required|string|unique:products|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:simple,variable',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|integer|min:0',
            'width' => 'nullable|integer|min:0',
            'height' => 'nullable|integer|min:0',
            'stock' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        Product::create($validated);

        return back()->with('success', 'Продукт успішно створено.');
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:255',
            'sku' => 'required|string|unique:products,sku,' . $product->id . '|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:simple,variable',
            'price' => 'required|numeric|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|integer|min:0',
            'width' => 'nullable|integer|min:0',
            'height' => 'nullable|integer|min:0',
            'stock' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $product->update($validated);

        return redirect()->back()->with('success', 'Продукт успішно оновлено.');
    }



    /**
     * Store a new variation for the product.
     */
    public function storeVariation(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        // Валидация данных
        $validated = $request->validate([
            'sku' => 'required|string|unique:product_variations,sku|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'discounted_price' => 'nullable|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'attributes' => 'required|array|min:1',
            'attributes.*.attribute_name' => 'required|string|max:255',
            'attributes.*.attribute_value' => 'required|string|max:255',
        ]);

        // Создание вариации
        $variation = $product->variations()->create($validated);

        // Добавление атрибутов к вариации
        foreach ($validated['attributes'] as $attribute) {
            $variation->attributes()->create($attribute);
        }

        return redirect()->back()->with('success', 'Variation with attributes added successfully.');
    }


    /**
     * Delete the specified product.
     */
    public function destroy($id)
    {
        $product = Product::with('variations')->findOrFail($id);

        // Проверка на наличие вариаций
        if ($product->variations->count() > 0) {
            return redirect()->back()->withErrors([
                'error' => 'Cannot delete product with existing variations.'
            ]);
        }

        // Удаление продукта
        $product->delete();

        return redirect()->back()->with('success', 'Product deleted successfully.');
    }

    public function destroyVariation($id, $variationId)
    {


        $variation = ProductVariation::findOrFail($variationId);
        // Проверка на наличие
        if ($variation->stock > 0) {
            return redirect()->back()->withErrors([
                'error' => 'Cannot delete variation with stock greater than 0.',
            ]);
        }

        // Удаление вариации
        $variation->delete();

        return redirect()->back()->with('success', 'Variation deleted successfully.');
    }


}
