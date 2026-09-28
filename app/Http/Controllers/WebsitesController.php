<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreWebsiteRequest;
use App\Http\Requests\UpdateWebsiteRequest;
use App\Models\Website;
use App\Services\WebsiteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WebsitesController extends Controller
{
    public function __construct(private readonly WebsiteService $websiteService) {}

    public function index(): View
    {
        return view('websites.index', [
            'websites' => auth()->user()->websites()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('websites.create');
    }

    public function store(StoreWebsiteRequest $request): RedirectResponse
    {
        $result = $this->websiteService->create(
            $request->user(),
            $request->validated(),
            $request->ip(),
        );

        return redirect()
            ->route('websites.show', $result['website'])
            ->with('plaintext_secret', $result['secret'])
            ->with('success', 'Website added. Copy your API secret now — it is shown only once.');
    }

    public function show(Website $website): View
    {
        Gate::authorize('view', $website);

        return view('websites.show', [
            'website' => $website,
            'connectionLogs' => $website->connectionLogs()->latest()->take(10)->get(),
        ]);
    }

    public function edit(Website $website): View
    {
        Gate::authorize('update', $website);

        return view('websites.edit', ['website' => $website]);
    }

    public function update(UpdateWebsiteRequest $request, Website $website): RedirectResponse
    {
        $this->websiteService->update($website, $request->validated());

        return redirect()
            ->route('websites.show', $website)
            ->with('success', 'Website updated.');
    }

    public function destroy(Website $website): RedirectResponse
    {
        Gate::authorize('delete', $website);

        $this->websiteService->delete($website);

        return redirect()
            ->route('websites.index')
            ->with('success', 'Website deleted. Its posts and logs were removed with it.');
    }
}
