<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::all();
        return inertia('EmailTemplates/Index', ['templates' => $templates]);
    }

    public function create()
    {
        return inertia('EmailTemplates/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        EmailTemplate::create($validated);

        return redirect()->route('email-templates.index')->with('success', 'Шаблон успішно створено!');
    }

    public function edit($id)
    {
        $template = EmailTemplate::findOrFail($id);
        return inertia('EmailTemplates/Edit', ['template' => $template]);
    }

    public function update(Request $request, $id)
    {
        $template = EmailTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $template->update($validated);

        return redirect()->route('email-templates.index')->with('success', 'Шаблон іспішно оновлено!');
    }

    public function destroy($id)
    {
        $template = EmailTemplate::findOrFail($id);
        $template->delete();

        return back()->with('success', 'Шаблон успішно видалено!');

    }
}
