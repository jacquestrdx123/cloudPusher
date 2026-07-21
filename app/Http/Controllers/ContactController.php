<?php

namespace App\Http\Controllers;

use App\Actions\StoreLead;
use App\Http\Requests\StoreLeadRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact');
    }

    public function store(StoreLeadRequest $request, StoreLead $storeLead): RedirectResponse
    {
        $storeLead->handle($request->validated());

        return redirect()
            ->route('contact')
            ->with('status', 'Thank you. We have received your request and will be in touch.');
    }
}
