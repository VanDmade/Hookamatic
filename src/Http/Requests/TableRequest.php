<?php

namespace VanDmade\Hookamatic\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TableRequest extends FormRequest
{

    public function messages(): array
    {
        return [
            'search.string' => __('hookamatic::table.search.string'),
            'page.integer' => __('hookamatic::table.page.integer'),
            'page.min' => __('hookamatic::table.page.min'),
            'per_page.integer' => __('hookamatic::table.per_page.integer'),
            'per_page.min' => __('hookamatic::table.per_page.min'),
            'per_page.max' => __('hookamatic::table.per_page.max'),
            'sort_by.string' => __('hookamatic::table.sort_by.string'),
            'sort_order.in' => __('hookamatic::table.sort_order.in'),
            'options.array' => __('hookamatic::table.options.array'),
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->input('search', null),
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 10),
            'sort_by' => $this->input('sort_by', null),
            'sort_order' => $this->input('sort_order', 'asc'),
            'options' => $this->input('options', []),
            'json' => filter_var($this->input('json', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string',
            'sort_order' => 'nullable|in:asc,desc',
            'options' => 'nullable|array',
            'json' => 'nullable|boolean',
        ];
    }

}
