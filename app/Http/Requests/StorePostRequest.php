<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\BlogPost;
use App\Models\Website;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by store and update (the rules are identical by design; divergent
 * update-only rules would arrive as a subclass later).
 *
 * `scheduled_at` is entered in the website's local time and intentionally
 * left untouched here (so old() re-displays exactly what the user typed);
 * BlogPostService converts it to UTC after validation succeeds.
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Normalize comma-separated lists into arrays before validation.
     */
    public function prepareForValidation(): void
    {
        foreach (['tags', 'keywords'] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $entries = array_filter(array_map(
                fn (string $entry): string => trim($entry),
                explode(',', $value),
            ));

            $this->merge([$field => array_values($entries)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'website_id' => [
                'required',
                'integer',
                Rule::exists('websites', 'id')->where('user_id', $this->user()?->id),
            ],
            'title' => ['required', 'string', 'max:200'],
            'topic' => ['nullable', 'string', 'max:200'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string', 'max:500000'],
            'category' => ['nullable', 'string', 'max:100'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'keywords' => ['nullable', 'array', 'max:10'],
            'keywords.*' => ['string', 'max:50'],
            'scheduled_at' => ['nullable', 'date', $this->futureSchedule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website_id.exists' => 'Choose one of your own websites.',
        ];
    }

    /**
     * Schedules must be in the future when interpreted in the website's
     * timezone — except when they are exactly the schedule already stored
     * (otherwise editing a post whose earlier schedule has passed would
     * get stuck).
     *
     * @return callable(string, mixed, Closure): void
     */
    private function futureSchedule(): callable
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $website = $this->resolveWebsite();
            $timezone = ($website->timezone ?? null) ?: config('app.timezone');

            try {
                // Local wall time + website timezone = exact instant.
                $submitted = Carbon::parse($value, $timezone);
            } catch (\Exception) {
                return; // The "date" rule already failed.
            }

            $post = $this->route('post');

            if (
                $post instanceof BlogPost
                && $post->scheduled_at !== null
                && $post->scheduled_at->equalTo($submitted)
            ) {
                return;
            }

            if ($submitted->lte(now())) {
                $fail('The schedule must be a date and time in the future.');
            }
        };
    }

    /**
     * The website this post belongs to (validated input when present, the
     * bound post's website otherwise) — used to interpret local time.
     */
    private function resolveWebsite(): ?Website
    {
        $websiteId = $this->input('website_id');

        if ($websiteId !== null && $websiteId !== '') {
            $website = $this->user()?->websites()->whereKey($websiteId)->first();

            if ($website instanceof Website) {
                return $website;
            }
        }

        $post = $this->route('post');

        return $post instanceof BlogPost ? $post->website : null;
    }
}
