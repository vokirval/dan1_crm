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
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        EmailTemplate::create($request->all());

        return redirect()->route('email-templates.index')->with('success', 'Template created successfully.');
    }
}
