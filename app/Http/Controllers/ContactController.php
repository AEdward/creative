<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return redirect()->route('contact')->with('status', 'Your message has been received.');
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'string', 'email', 'max:255'], 'subject' => ['nullable', 'string', 'max:150'], 'message' => ['required', 'string', 'min:10', 'max:5000']]);
        ContactMessage::create($data);

        return redirect()->route('contact')->with('status', 'Your message has been received.');
    }
}
