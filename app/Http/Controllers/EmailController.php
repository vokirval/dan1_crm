<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\EmailTemplate;
use App\Models\EmailHistory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    /**
     * Метод для замены макросов в шаблоне письма.
     */
    private function replaceMacros(string $templateBody, Order $order): string
    {
        $macros = $order->getMacros(); // Получаем макросы из модели Order
        return str_replace(array_keys($macros), array_values($macros), $templateBody);
    }

    /**
     * Отправка письма по шаблону.
     */
    public function sendEmail(Request $request, $orderId)
{
    $order = Order::findOrFail($orderId);

    if (!($order instanceof Order)) {
        throw new \InvalidArgumentException('Invalid order object passed');
    }

    $template = EmailTemplate::findOrFail($request->input('template_id'));

    $subject = $request->input('custom_subject') ?? $this->replaceMacros($template->subject, $order);
    $body = $request->input('custom_body') ?? $this->replaceMacros($template->body, $order);

    try {
        Mail::send([], [], function ($message) use ($order, $subject, $body) {
            $message->to($order->email)
                ->subject($subject)
                ->html($body); // Указываем HTML-тело письма
        });

        EmailHistory::create([
            'order_id' => $order->id,
            'template_id' => $template->id,
            'to_email' => $order->email,
            'subject' => $subject,
            'body' => $body,
            'status' => 'success',
            'sent_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Email sent successfully.']);
    } catch (\Exception $e) {
        EmailHistory::create([
            'order_id' => $order->id,
            'template_id' => $template->id,
            'to_email' => $order->email,
            'subject' => $subject,
            'body' => $body,
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);

        return response()->json(['success' => false, 'message' => 'Failed to send email.', 'error' => $e->getMessage()]);
    }
}


}
