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
    public function index()
    {
        return Inertia::render('Statistics/Index', [
            'statuses' => OrderStatus::all(),
            'categories' => Category::all(),
            'initialData' => [
                'orders' => [],
                'stats' => [
                    'order_count' => 0,
                    'status_stats' => [],
                    'items_count_non_services' => 0,
                    'items_count_services' => 0,
                    'total_sum_non_services' => 0,
                    'total_sum_services' => 0
                ],
                'products_stats' => []
            ]
        ]);
    }

    // Обработка фильтров (POST)
    public function filter(Request $request)
    {
        $request->validate([
            'mandatory_date' => 'required|array',
            'mandatory_date.field' => 'required|in:created_at,delivery_date,sent_at,updated_at',
            'mandatory_date.range' => 'required|array|size:2',
            'filters' => 'nullable|array'
        ]);

        // Применяем обязательный фильтр даты
        $query = Order::query()
            ->with([
                'status',
                'paymentMethod',
                'deliveryMethod',
                'group',
                'responsibleUser',
                'items' => function ($query) {
                    $query->select([
                        'id',
                        'order_id',
                        'product_id',
                        'product_variation_id',
                        'quantity',
                        'price'
                    ])->with([
                                'product:id,name,category_id',
                                'productVariation:id,product_id',
                                'productVariation.product:id,name,category_id',
                                'productVariation.attributes:product_variation_id,attribute_name,attribute_value'
                            ]);
                }
            ])
            ->withSum('items as calculated_total', DB::raw('quantity * price'))
            ->whereBetween(
                $request->input('mandatory_date.field'),
                $request->input('mandatory_date.range')
            );

        // Применяем дополнительные фильтры (если есть)
        if ($request->filled('filters')) {
            $this->applyFilters($query, $request->input('filters'));
        }

        $orders = $query->latest()->get();
        $categories = Category::all()->keyBy('id');
        $servicesCategoryId = Category::where('name', 'Services')->first()->id ?? 10;


        return response()->json([
            'orders' => $orders,
            'stats' => [
                'order_count' => $orders->count(),
                'status_stats' => $this->getStatusStats($orders),
                'items_count_non_services' => $this->getItemsCount($orders, $servicesCategoryId, false, $categories),
                'items_count_services' => $this->getItemsCount($orders, $servicesCategoryId, true, $categories),
                'total_sum_non_services' => $this->getItemsSum($orders, $servicesCategoryId, false, $categories),
                'total_sum_services' => $this->getItemsSum($orders, $servicesCategoryId, true, $categories),
            ],
            'products_stats' => $this->getProductsStats($orders, $categories),
        ]);
    }

    protected function getStatusStats($orders)
    {
        return OrderStatus::all()->mapWithKeys(function ($status) use ($orders) {
            return [
                $status->id => [
                    'count' => $orders->where('order_status_id', $status->id)->count(),
                    'name' => $status->name,
                    'color' => $status->color // сохраняем цвет для фронтенда
                ]
            ];
        })->sortByDesc('count')->values()->toArray();
    }


    protected function getItemsCount($orders, $servicesCategoryId, bool $isService, $categories): int
    {
        return $orders->sum(function ($order) use ($servicesCategoryId, $isService, $categories) {
            return $order->items->sum(function ($item) use ($servicesCategoryId, $isService, $categories) {
                $categoryId = $this->getItemCategoryId($item, $categories);
                return ($categoryId === $servicesCategoryId) === $isService ? $item->quantity : 0;
            });
        });
    }

    protected function getItemsSum($orders, $servicesCategoryId, bool $isService, $categories): float
    {
        return $orders->sum(function ($order) use ($servicesCategoryId, $isService, $categories) {
            return $order->items->sum(function ($item) use ($servicesCategoryId, $isService, $categories) {
                $categoryId = $this->getItemCategoryId($item, $categories);
                return ($categoryId === $servicesCategoryId) === $isService ? $item->quantity * $item->price : 0;
            });
        });
    }

    protected function getItemCategoryId($item, $categories)
    {
        if ($item->product) {
            return $item->product->category_id;
        }

        if ($item->product_variation_id && $item->productVariation->product) {
            return $item->productVariation->product->category_id;
        }

        return null;
    }

    protected function getProductsStats($orders, $categories)
    {
        return $orders->flatMap(function ($order) use ($categories) {
            return $order->items->map(function ($item) use ($categories) {
                $product = $item->product ?? $item->productVariation->product ?? null;
                if (!$product)
                    return null;

                $categoryId = $this->getItemCategoryId($item, $categories);
                $categoryName = $categories[$categoryId]->name ?? 'Без категорії';

                return [
                    'key' => $item->product_id . '-' . ($item->product_variation_id ?? 'no-variation'),
                    'product_name' => $product->name,
                    'attributes' => $item->product_variation_id
                        ? $item->productVariation->attributes
                            ->map(fn($attr) => "{$attr->attribute_name}: {$attr->attribute_value}")
                            ->join(', ')
                        : null,
                    'category_name' => $categoryName,
                    'quantity' => $item->quantity,
                    'total_sum' => $item->quantity * $item->price,
                ];
            });
        })
            ->filter()
            ->groupBy('key')
            ->map(function ($group) {
                $first = $group->first();
                return [
                    'product_name' => $first['product_name'],
                    'attributes' => $first['attributes'],
                    'category_name' => $first['category_name'],
                    'quantity' => $group->sum('quantity'),
                    'total_sum' => $group->sum('total_sum'),
                ];
            })
            ->values()
            ->toArray();
    }

    protected function isDateField($field): bool
    {
        return in_array($field, ['created_at', 'updated_at', 'sent_at', 'delivery_date', 'payment_date']);
    }

    protected function applyFilters($query, $group, $parentCondition = 'AND')
    {
        $method = strtolower($parentCondition) === 'or' ? 'orWhere' : 'where';

        $query->$method(function ($q) use ($group) {
            $groupCondition = strtolower($group['condition'] ?? 'AND');
            $groupMethod = $groupCondition === 'or' ? 'orWhere' : 'where';

            foreach ($group['rules'] as $rule) {
                if (isset($rule['rules'])) {
                    $this->applyFilters($q, $rule, $groupCondition);
                    continue;
                }

                $this->applyFilterRule($q, $rule, $groupMethod);
            }
        });
    }

    protected function applyFilterRule($query, array $rule, string $method): void
    {
        $field = $rule['field'] ?? null;
        $operator = $rule['operator'] ?? null;
        $value = $rule['value'] ?? null;

        if (!$field || !$operator || is_null($value)) {
            return;
        }

        // Специальная обработка взаимоисключающих условий для статусов
        if ($field === 'order_status_id') {
            $this->applyStatusFilter($query, $operator, $value, $method);
            return;
        }

        match ($field) {
            'product_id' => $this->applyProductFilter($query, $operator, $value, $method),
            'product_variation_id' => $this->applyVariationFilter($query, $operator, $value, $method),
            'category_id' => $this->applyCategoryFilter($query, $operator, $value, $method),
            'calculated_total' => $this->applyTotalFilter($query, $operator, $value, $method),
            default => $this->applyDefaultFilter($query, $field, $operator, $value, $method)
        };
    }

    protected function applyStatusFilter($query, string $operator, $value, string $method): void
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        if ($operator === 'входить в') {
            $query->{$method . 'In'}('order_status_id', $value);
        } elseif ($operator === 'не входить в') {
            $query->{$method . 'NotIn'}('order_status_id', $value);
        } else {
            $query->{$method}('order_status_id', $operator, $value);
        }
    }

    protected function applyProductFilter($query, string $operator, $value, string $method): void
    {
        $callback = function ($q) use ($operator, $value) {
            if ($operator === 'дорівнює') {
                $q->where('product_id', $value)
                    ->orWhereHas('productVariation', fn($q) => $q->where('product_id', $value));
            } elseif ($operator === 'не дорівнює') {
                $q->where('product_id', '!=', $value)
                    ->whereDoesntHave('productVariation', fn($q) => $q->where('product_id', $value));
            }
        };

        $query->{$method . 'Has'}('items', $callback);
    }

    protected function applyVariationFilter($query, string $operator, $value, string $method): void
    {
        $callback = fn($q) => $operator === 'дорівнює'
            ? $q->where('product_variation_id', $value)
            : $q->where('product_variation_id', '!=', $value);

        $query->{$method . 'Has'}('items', $callback);
    }

    protected function applyCategoryFilter($query, string $operator, $value, string $method): void
    {
        $callback = function ($q) use ($operator, $value) {
            $q->whereHas('product', fn($q) => $operator === 'дорівнює'
                ? $q->where('category_id', $value)
                : $q->where('category_id', '!=', $value))
                ->orWhereHas('productVariation.product', fn($q) => $operator === 'дорівнює'
                    ? $q->where('category_id', $value)
                    : $q->where('category_id', '!=', $value));
        };

        $query->{$method . 'Has'}('items', $callback);
    }

    protected function applyTotalFilter($query, string $operator, $value, string $method): void
    {
        if (in_array($operator, ['=', '!=', '<', '<=', '>', '>='])) {
            $raw = "(SELECT COALESCE(SUM(quantity * price), 0) FROM order_items WHERE order_id = orders.id)";
            $query->{$method . 'Raw'}("$raw $operator ?", [$value]);
        }
    }

    protected function applyDefaultFilter($query, string $field, string $operator, $value, string $method): void
    {
        if ($this->isDateField($field) && in_array($operator, ['дорівнює', 'не дорівнює', 'до', 'після', 'між'])) {
            $this->applyDateFilter($query, $field, $operator, $value, $method);
            return;
        }

        switch ($operator) {
            case 'містить':
                $query->{$method}($field, 'LIKE', "%$value%");
                break;
            case 'не містить':
                $query->{$method}($field, 'NOT LIKE', "%$value%");
                break;
            case 'дорівнює':
                $query->{$method}($field, '=', $value);
                break;
            case 'не дорівнює':
                $query->{$method}($field, '!=', $value);
                break;
            case '=':
            case '!=':
            case '<':
            case '<=':
            case '>':
            case '>=':
                $query->{$method}($field, $operator, $value);
                break;
            case 'входить в':
                if (is_array($value)) {
                    $query->{$method . 'In'}($field, $value);
                }
                break;
            case 'не входить в':
                if (is_array($value)) {
                    $query->{$method . 'NotIn'}($field, $value);
                }
                break;
        }
    }

    protected function applyDateFilter($query, string $field, string $operator, $value, string $method): void
    {
        switch ($operator) {
            case 'дорівнює':
                $query->{$method . 'Date'}($field, '=', $value);
                break;
            case 'не дорівнює':
                $query->{$method . 'Date'}($field, '!=', $value);
                break;
            case 'до':
                $query->{$method . 'Date'}($field, '<', $value);
                break;
            case 'після':
                $query->{$method . 'Date'}($field, '>', $value);
                break;
            case 'між':
                if (is_array($value) && count($value) === 2) {
                    $query->{$method}(function ($q) use ($field, $value) {
                        $q->whereDate($field, '>=', $value[0])
                            ->whereDate($field, '<=', $value[1]);
                    });
                }
                break;
        }
    }
}