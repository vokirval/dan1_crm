<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Order;
use App\Models\Category;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticController extends Controller
{
    public function index(Request $request)
    {
        $filterJson = $request->get('filters');
        $filters = $filterJson ? json_decode($filterJson, true) : null;

        $query = Order::query()
            ->with([
                'status',
                'paymentMethod',
                'deliveryMethod',
                'group',
                'responsibleUser',
                'items.product:name,id,category_id',
                'items.productVariation:id,product_id',
                'items.productVariation.product:name,id,category_id', // Добавляем category_id для вариаций
                'items.productVariation.attributes:product_variation_id,attribute_name,attribute_value'
            ])
            ->withSum('items as calculated_total', DB::raw('quantity * price'))
            ->when($filters, function ($query) use ($filters) {
                $this->applyFilters($query, $filters);
            })
            ->latest();

        $orders = $query->get();
        $servicesCategoryId = Category::where('name', 'Services')->value('id') ?? 10; // Динамический ID для "Services"

        // 1. Количество заказов
        $orderCount = $orders->count();

        // 2. Количество товаров (не Services и Services)
        $itemsCountNonServices = $orders->sum(function ($order) use ($servicesCategoryId) {
            return $order->items->where(function ($item) use ($servicesCategoryId) {
                $categoryId = $item->product ? $item->product->category_id : ($item->productVariation->product->category_id ?? null);
                return $categoryId !== $servicesCategoryId;
            })->sum('quantity');
        });

        $itemsCountServices = $orders->sum(function ($order) use ($servicesCategoryId) {
            return $order->items->where(function ($item) use ($servicesCategoryId) {
                $categoryId = $item->product ? $item->product->category_id : ($item->productVariation->product->category_id ?? null);
                return $categoryId === $servicesCategoryId;
            })->sum('quantity');
        });

        // 3. Сумма заказов (не Services и Services)
        $totalSumNonServices = $orders->sum(function ($order) use ($servicesCategoryId) {
            return $order->items->where(function ($item) use ($servicesCategoryId) {
                $categoryId = $item->product ? $item->product->category_id : ($item->productVariation->product->category_id ?? null);
                return $categoryId !== $servicesCategoryId;
            })->sum(function ($item) {
                return $item->quantity * $item->price;
            });
        });

        $totalSumServices = $orders->sum(function ($order) use ($servicesCategoryId) {
            return $order->items->where(function ($item) use ($servicesCategoryId) {
                $categoryId = $item->product ? $item->product->category_id : ($item->productVariation->product->category_id ?? null);
                return $categoryId === $servicesCategoryId;
            })->sum(function ($item) {
                return $item->quantity * $item->price;
            });
        });

        // 4. Массив товаров с агрегацией
        $productsStats = $this->getProductsStats($orders);

        $statuses = OrderStatus::all();
        $categories = Category::all();

        return Inertia::render('Statistics/Index', [
            'orders' => $orders,
            'statuses' => $statuses,
            'categories' => $categories,
            'stats' => [
                'order_count' => $orderCount,
                'items_count_non_services' => $itemsCountNonServices,
                'items_count_services' => $itemsCountServices,
                'total_sum_non_services' => $totalSumNonServices,
                'total_sum_services' => $totalSumServices,
            ],
            'products_stats' => $productsStats,
        ]);
    }

    protected function getProductsStats($orders)
    {
        $productsMap = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product ?? $item->productVariation->product ?? null;
                if (!$product) continue;

                $key = $item->product_id . '-' . ($item->product_variation_id ?? 'no-variation');
                $attributes = $item->product_variation_id && $item->productVariation->attributes
                    ? $item->productVariation->attributes->map(fn($attr) => "{$attr->attribute_name}: {$attr->attribute_value}")->join(', ')
                    : null;

                if (!isset($productsMap[$key])) {
                    $productsMap[$key] = [
                        'product_name' => $product->name,
                        'attributes' => $attributes,
                        'category_name' => $product->category->name ?? 'Без категорії',
                        'quantity' => 0,
                        'total_sum' => 0,
                    ];
                }

                $productsMap[$key]['quantity'] += $item->quantity;
                $productsMap[$key]['total_sum'] += $item->quantity * $item->price;
            }
        }

        return array_values($productsMap); // Преобразуем в массив без ключей
    }

    protected function isDateField($field): bool
    {
        return in_array($field, ['created_at', 'updated_at', 'sent_at', 'delivery_date', 'payment_date']);
    }

    protected function applyFilters($query, $group, $parentCondition = 'AND')
    {
        // Ваш текущий код applyFilters остается без изменений
        $method = strtolower($parentCondition) === 'or' ? 'orWhere' : 'where';

        $query->$method(function ($q) use ($group) {
            $groupCondition = strtolower($group['condition'] ?? 'AND');
            $groupMethod = $groupCondition === 'or' ? 'orWhere' : 'where';

            foreach ($group['rules'] as $rule) {
                if (isset($rule['rules'])) {
                    $this->applyFilters($q, $rule, $groupCondition);
                    continue;
                }

                $field = $rule['field'] ?? null;
                $operator = $rule['operator'] ?? null;
                $value = $rule['value'] ?? null;

                if (!$field || !$operator || is_null($value)) {
                    continue;
                }

                if ($field === 'product_id') {
                    $q->$groupMethod(function ($sq) use ($operator, $value) {
                        if ($operator === 'дорівнює') {
                            $sq->whereHas('items', function ($itemQuery) use ($value) {
                                $itemQuery->where(function ($inner) use ($value) {
                                    $inner->where('product_id', $value)
                                          ->orWhereHas('productVariation', fn($q) => $q->where('product_id', $value));
                                });
                            });
                        } elseif ($operator === 'не дорівнює') {
                            $sq->whereDoesntHave('items', function ($itemQuery) use ($value) {
                                $itemQuery->where(function ($inner) use ($value) {
                                    $inner->where('product_id', $value)
                                          ->orWhereHas('productVariation', fn($q) => $q->where('product_id', $value));
                                });
                            })->orWhereHas('items', function ($itemQuery) use ($value) {
                                $itemQuery->where('product_id', '!=', $value)
                                          ->whereDoesntHave('productVariation', fn($q) => $q->where('product_id', $value));
                            });
                        }
                    });
                    continue;
                }

                if ($field === 'product_variation_id') {
                    $q->$groupMethod(function ($sq) use ($operator, $value) {
                        if ($operator === 'дорівнює') {
                            $sq->whereHas('items', fn($q) => $q->where('product_variation_id', $value));
                        } elseif ($operator === 'не дорівнює') {
                            $sq->whereDoesntHave('items', fn($q) => $q->where('product_variation_id', $value));
                        }
                    });
                    continue;
                }

                if ($field === 'category_id') {
                    $q->$groupMethod(function ($sq) use ($operator, $value) {
                        if ($operator === 'дорівнює') {
                            $sq->whereHas('items', function ($itemQuery) use ($value) {
                                $itemQuery->whereHas('product', function ($productQuery) use ($value) {
                                    $productQuery->where('category_id', $value);
                                })->orWhereHas('productVariation.product', function ($variationProductQuery) use ($value) {
                                    $variationProductQuery->where('category_id', $value);
                                });
                            });
                        } elseif ($operator === 'не дорівнює') {
                            $sq->whereDoesntHave('items', function ($itemQuery) use ($value) {
                                $itemQuery->whereHas('product', function ($productQuery) use ($value) {
                                    $productQuery->where('category_id', $value);
                                })->orWhereHas('productVariation.product', function ($variationProductQuery) use ($value) {
                                    $variationProductQuery->where('category_id', $value);
                                });
                            })->orWhereHas('items', function ($itemQuery) use ($value) {
                                $itemQuery->whereHas('product', fn($q) => $q->where('category_id', '!=', $value))
                                          ->whereDoesntHave('productVariation.product', fn($q) => $q->where('category_id', $value));
                            });
                        }
                    });
                    continue;
                }

                if ($field === 'calculated_total') {
                    $allowedOperators = ['=', '!=', '<', '<=', '>', '>='];
                    if (in_array($operator, $allowedOperators)) {
                        $raw = "(SELECT COALESCE(SUM(order_items.quantity * order_items.price), 0) FROM order_items WHERE order_items.order_id = orders.id)";
                        $q->$groupMethod(function ($subQ) use ($operator, $value, $raw) {
                            $subQ->whereRaw("$raw $operator ?", [$value]);
                        });
                    }
                    continue;
                }

                $q->$groupMethod(function ($subQ) use ($field, $operator, $value) {
                    switch ($operator) {
                        case 'містить':
                            $subQ->where($field, 'LIKE', "%$value%");
                            break;
                        case 'не містить':
                            $subQ->where($field, 'NOT LIKE', "%$value%");
                            break;
                        case 'дорівнює':
                            if ($this->isDateField($field)) {
                                $subQ->whereDate($field, '=', $value);
                            } else {
                                $subQ->where($field, '=', $value);
                            }
                            break;
                        case 'не дорівнює':
                            $subQ->where($field, '!=', $value);
                            break;
                        case '=':
                        case '!=':
                        case '<':
                        case '<=':
                        case '>':
                        case '>=':
                            $subQ->where($field, $operator, $value);
                            break;
                        case 'до':
                            $subQ->whereDate($field, '<', $value);
                            break;
                        case 'після':
                            $subQ->whereDate($field, '>', $value);
                            break;
                        case 'між':
                            if (is_array($value) && count($value) === 2) {
                                $subQ->whereDate($field, '>=', $value[0])
                                     ->whereDate($field, '<=', $value[1]);
                            }
                            break;
                        case 'входить в':
                            if (is_array($value)) {
                                $subQ->whereIn($field, $value);
                            }
                            break;
                        case 'не входить в':
                            if (is_array($value)) {
                                $subQ->whereNotIn($field, $value);
                            }
                            break;
                    }
                });
            }
        });
    }
}