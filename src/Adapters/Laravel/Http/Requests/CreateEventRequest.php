<?php

declare(strict_types=1);

namespace Denosys\Analytics\Adapters\Laravel\Http\Requests;

use Denosys\Analytics\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @internal
 */
final class CreateEventRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array'],
            'events.*.name' => ['required', 'string', 'alpha_dash:ascii'],
            'events.*.type' => ['required', Rule::enum(EventType::class)],
        ];
    }
}
