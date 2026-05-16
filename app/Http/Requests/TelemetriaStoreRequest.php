<?php
/*
 * Created At: 2026-05-12T12:39:34Z
 */

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TelemetriaStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => 'required|string|max:50',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'velocidade' => 'nullable|numeric|min:0',
            'bateria' => 'nullable|numeric|min:0|max:100',
            'panic_button' => 'nullable|boolean',
        ];
    }
}
