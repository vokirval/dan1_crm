<?php

namespace App\Http\Controllers;

use App\Models\AutoRule;
use App\Models\AutoRuleAction;
use App\Models\AutoRuleCondition;
use App\Models\Category;
use App\Models\DeliveryMethod;
use App\Models\Group;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AutoRuleController extends Controller
{
    public function index(OrderStatus $orderStatus)
    {
    
        return Inertia::render('AutoRules/Index', [
            'orderStatus' => $orderStatus,
            'rules' => $orderStatus->autoRules()->with(['conditions', 'actions'])->get(),
        ]);

      
    }

    public function create(OrderStatus $orderStatus)
    {
        $fields = $this->getAvailableFields();
        return Inertia::render('AutoRules/Create', [
            'orderStatus' => $orderStatus,
            'availableFields' => $fields,
            'availableOperators' => $this->getOperators(),
            'availableActions' => $this->getAvailableActions(),
        ]);
    }

    public function store(Request $request, OrderStatus $orderStatus)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'conditions' => 'array',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required', // Убрали |string, чтобы принимать массивы
            'actions' => 'array',
            'actions.*.type' => 'required|string',
            'actions.*.parameters' => 'nullable|array',
        ]);

        $rule = AutoRule::create([
            'order_status_id' => $orderStatus->id,
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        foreach ($validated['conditions'] as $condition) {
            AutoRuleCondition::create([
                'auto_rule_id' => $rule->id,
                'field' => $condition['field'],
                'operator' => $condition['operator'],
                'value' => is_array($condition['value']) ? json_encode($condition['value']) : $condition['value'],
            ]);
        }

        foreach ($validated['actions'] as $action) {
            AutoRuleAction::create([
                'auto_rule_id' => $rule->id,
                'type' => $action['type'],
                'parameters' => $action['parameters'],
            ]);
        }

        return redirect()->route('order-statuses.index', $orderStatus)->with('success', 'Автоправило успішно створено.');
        
    }

    protected function getAvailableFields()
    {
        return [
            ['label' => 'ID замовлення', 'value' => 'id', 'type' => 'number'],
            ['label' => 'Метод оплати', 'value' => 'payment_method_id', 'type' => 'select', 'options' => PaymentMethod::all()->map(fn($p) => ['id' => $p->id, 'name' => $p->name])],
            ['label' => 'Метод доставки', 'value' => 'delivery_method_id', 'type' => 'select', 'options' => DeliveryMethod::all()->map(fn($d) => ['id' => $d->id, 'name' => $d->name])],
            ['label' => 'Група', 'value' => 'group_id', 'type' => 'select', 'options' => Group::all()->map(fn($g) => ['id' => $g->id, 'name' => $g->name])],
            ['label' => 'Відповідальний', 'value' => 'responsible_user_id', 'type' => 'select', 'options' => User::all()->map(fn($u) => ['id' => $u->id, 'name' => $u->name])],
            ['label' => 'Ціна доставки', 'value' => 'delivery_price', 'type' => 'number'],
            ['label' => 'Повне ім\'я отримувача', 'value' => 'delivery_fullname', 'type' => 'string'],
            ['label' => 'Адреса доставки', 'value' => 'delivery_address', 'type' => 'string'],
            ['label' => 'Друга адреса', 'value' => 'delivery_second_address', 'type' => 'string'],
            ['label' => 'Поштовий індекс', 'value' => 'delivery_postcode', 'type' => 'string'],
            ['label' => 'Місто доставки', 'value' => 'delivery_city', 'type' => 'string'],
            ['label' => 'Область доставки', 'value' => 'delivery_state', 'type' => 'string'],
            ['label' => 'Код країни', 'value' => 'delivery_country_code', 'type' => 'string'],
            ['label' => 'Номер будинку', 'value' => 'delivery_address_number', 'type' => 'string'],
            ['label' => 'Email', 'value' => 'email', 'type' => 'string'],
            ['label' => 'Телефон', 'value' => 'phone', 'type' => 'string'],
            ['label' => 'IP-адреса', 'value' => 'ip', 'type' => 'string'],
            ['label' => 'Коментар', 'value' => 'comment', 'type' => 'string'],
            ['label' => 'Реферер сайту', 'value' => 'website_referrer', 'type' => 'string'],
            ['label' => 'UTM Source', 'value' => 'utm_source', 'type' => 'string'],
            ['label' => 'UTM Medium', 'value' => 'utm_medium', 'type' => 'string'],
            ['label' => 'UTM Term', 'value' => 'utm_term', 'type' => 'string'],
            ['label' => 'UTM Content', 'value' => 'utm_content', 'type' => 'string'],
            ['label' => 'UTM Campaign', 'value' => 'utm_campaign', 'type' => 'string'],
            ['label' => 'Sub ID 1', 'value' => 'sub_id1', 'type' => 'string'],
            ['label' => 'Sub ID 2', 'value' => 'sub_id2', 'type' => 'string'],
            ['label' => 'Sub ID 3', 'value' => 'sub_id3', 'type' => 'string'],
            ['label' => 'Sub ID 4', 'value' => 'sub_id4', 'type' => 'string'],
            ['label' => 'Sub ID 5', 'value' => 'sub_id5', 'type' => 'string'],
            ['label' => 'Sub ID 6', 'value' => 'sub_id6', 'type' => 'string'],
            ['label' => 'Sub ID 7', 'value' => 'sub_id7', 'type' => 'string'],
            ['label' => 'Sub ID 8', 'value' => 'sub_id8', 'type' => 'string'],
            ['label' => 'Sub ID 9', 'value' => 'sub_id9', 'type' => 'string'],
            ['label' => 'Sub ID 10', 'value' => 'sub_id10', 'type' => 'string'],
           // ['label' => 'Дата доставки', 'value' => 'delivery_date', 'type' => 'date'],
           // ['label' => 'Дата відправки', 'value' => 'sent_at', 'type' => 'date'],
            //['label' => 'Дата оплати', 'value' => 'payment_date', 'type' => 'date'],
           // ['label' => 'Дата оплати InPost', 'value' => 'inpost_payment_date', 'type' => 'date'],
            ['label' => 'Номер відстеження', 'value' => 'tracking_number', 'type' => 'string'],
            ['label' => 'Статус оплати', 'value' => 'is_paid', 'type' => 'boolean'],
            ['label' => 'Сума оплати', 'value' => 'paid_amount', 'type' => 'number'],
            ['label' => 'ID InPost', 'value' => 'inpost_id', 'type' => 'string'],
            ['label' => 'Статус InPost', 'value' => 'inpost_status', 'type' => 'string'],
            ['label' => 'Номер повернення', 'value' => 'return_tracking_number', 'type' => 'string'],
            ['label' => 'Сума замовлення', 'value' => 'order_total', 'type' => 'number'],
        ];
    }

    protected function getOperators()
    {
        return [
            'string' => ['містить', 'не містить', 'дорівнює', 'не дорівнює'],
            'number' => ['=', '!=', '<', '<=', '>', '>='],
            'select' => ['дорівнює', 'не дорівнює', 'входить в', 'не входить в'],
            'date' => ['=', '!=', '<', '<=', '>', '>='],
            'boolean' => ['дорівнює', 'не дорівнює'],
        ];
    }
    protected function getAvailableActions()
    {
        return [
            ['label' => 'Лог', 'value' => 'log'],
        ];
    }
    public function edit(OrderStatus $orderStatus, AutoRule $rule)
    {
        $fields = $this->getAvailableFields();
        return Inertia::render('AutoRules/Edit', [
            'orderStatus' => $orderStatus,
            'rule' => $rule->load(['conditions', 'actions']),
            'availableFields' => $fields,
            'availableOperators' => $this->getOperators(),
            'availableActions' => $this->getAvailableActions(),
        ]);
    }
    public function update(Request $request, OrderStatus $orderStatus, AutoRule $rule)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'conditions' => 'array',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string|in:містить,не містить,дорівнює,не дорівнює,є значення,немає значення,=,!=,<,<=,>,>=,входить в,не входить в',
            'conditions.*.value' => 'required', // Принимаем любой тип
            'actions' => 'array',
            'actions.*.type' => 'required|string',
            'actions.*.parameters' => 'nullable|array',
        ]);

        $rule->update([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Удаляем старые условия и действия
        $rule->conditions()->delete();
        $rule->actions()->delete();

        foreach ($validated['conditions'] as $condition) {
            AutoRuleCondition::create([
                'auto_rule_id' => $rule->id,
                'field' => $condition['field'],
                'operator' => $condition['operator'],
                'value' => is_array($condition['value']) ? json_encode($condition['value']) : $condition['value'],
            ]);
        }

        foreach ($validated['actions'] as $action) {
            AutoRuleAction::create([
                'auto_rule_id' => $rule->id,
                'type' => $action['type'],
                'parameters' => $action['parameters'],
            ]);
        }

        return back()->with('success', 'Автоправило успішно збережено.');
        
    }
    public function destroy(OrderStatus $orderStatus, AutoRule $rule)
    {
        $rule->delete();
        return back()->with('success', 'Автоправило успішно видалено.');
    }
}