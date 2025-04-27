<?php

namespace App\Services;

use App\Models\AutoRule;
use App\Models\Logs;
use App\Models\Order;

class AutoRuleService
{

    public function processRulesForOrder(Order $order)
    {
       
        $rules = AutoRule::where('order_status_id', $order->order_status_id)
            ->where('is_active', true)
            ->with(['conditions', 'actions'])
            ->get();

            

        foreach ($rules as $rule) {
            if ($this->checkConditions($rule, $order)) {
                $this->executeActions($rule, $order);
            }
        }
    }

    protected function checkConditions(AutoRule $rule, Order $order): bool
    {
        foreach ($rule->conditions as $condition) {
            // Получаем значение поля заказа
            $fieldValue = match ($condition->field) {
                'order_total' => $order->items->sum(fn($item) => $item->quantity * $item->price),
                default => $order->{$condition->field} ?? null,
            };
            $conditionValue = $condition->value;

            switch ($condition->operator) {
                case 'містить':
                    if (!is_string($fieldValue) || !str_contains(strtolower($fieldValue), strtolower($conditionValue))) {
                        return false;
                    }
                    break;

                case 'не містить':
                    if (!is_string($fieldValue) || str_contains(strtolower($fieldValue), strtolower($conditionValue))) {
                        return false;
                    }
                    break;

                case 'дорівнює':
                    if ($fieldValue !== $conditionValue) {
                        return false;
                    }
                    break;

                case 'не дорівнює':
                    if ($fieldValue === $conditionValue) {
                        return false;
                    }
                    break;

                case 'є значення':
                    if (is_null($fieldValue) || $fieldValue === '') {
                        return false;
                    }
                    break;

                case 'немає значення':
                    if (!is_null($fieldValue) && $fieldValue !== '') {
                        return false;
                    }
                    break;

                case '=':
                    if ($fieldValue != $conditionValue) { // Нестрогое сравнение для чисел
                        return false;
                    }
                    break;

                case '!=':
                    if ($fieldValue == $conditionValue) {
                        return false;
                    }
                    break;

                case '<':
                    if (!is_numeric($fieldValue) || $fieldValue >= $conditionValue) {
                        return false;
                    }
                    break;

                case '<=':
                    if (!is_numeric($fieldValue) || $fieldValue > $conditionValue) {
                        return false;
                    }
                    break;

                case '>':
                    if (!is_numeric($fieldValue) || $fieldValue <= $conditionValue) {
                        return false;
                    }
                    break;

                case '>=':
                    if (!is_numeric($fieldValue) || $fieldValue < $conditionValue) {
                        return false;
                    }
                    break;

                case 'входить в':
                    $conditionValue = is_array($conditionValue) ? $conditionValue : json_decode($conditionValue, true);
                    if (!is_array($conditionValue) || !in_array($fieldValue, $conditionValue)) {
                        return false;
                    }
                    break;

                case 'не входить в':
                    $conditionValue = is_array($conditionValue) ? $conditionValue : json_decode($conditionValue, true);
                    if (!is_array($conditionValue) || in_array($fieldValue, $conditionValue)) {
                        return false;
                    }
                    break;

                default:
                    return false; // Неизвестный оператор
            }
        }

        return true; // Все условия выполнены
    }

    protected function executeActions(AutoRule $rule, Order $order)
{
    foreach ($rule->actions as $action) {
        switch ($action->type) {
            case 'log':
                Logs::create([
                    'auto_rule_id' => $rule->id,
                    'order_id' => $order->id,
                    'message' => $action->parameters['message'] ?? "Автоправило {$rule->name} успішно виконано для замовлення {$order->id}",
                ]);
                break;
            case 'send_email':
                $recipient = $action->parameters['recipient'] ?? $order->email;
                $subject = $action->parameters['subject'] ?? 'Повідомлення щодо замовлення';
                $body = $action->parameters['body'] ?? "Ваше замовлення #{$order->id} оброблено.";

                if ($recipient && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    try {
                        \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($recipient, $subject) {
                            $message->to($recipient)
                                    ->subject($subject)
                                    ->from(config('mail.from.address'), config('mail.from.name'));
                        });
                        // Логируем успешную отправку
                        Logs::create([
                            'auto_rule_id' => $rule->id,
                            'order_id' => $order->id,
                            'message' => "Email відправлено на {$recipient} з темою: {$subject}",
                        ]);
                    } catch (\Exception $e) {
                        // Логируем ошибку
                        Logs::create([
                            'auto_rule_id' => $rule->id,
                            'order_id' => $order->id,
                            'message' => "Помилка відправки email на {$recipient}: {$e->getMessage()}",
                        ]);
                    }
                } else {
                    // Логируем ошибку, если email невалидный
                    Logs::create([
                        'auto_rule_id' => $rule->id,
                        'order_id' => $order->id,
                        'message' => "Невалідний email для відправки: {$recipient}",
                    ]);
                }
                break;
        }
    }
}
}