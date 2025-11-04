<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
   
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');
        $user = $this->user();

        return $conversation->participants()
                            ->where('participant_type', $user->getMorphClass())
                            ->where('participant_id', $user->id)
                            ->exists();
    }

   
    public function rules(): array
    {
        return [
            'body' => 'required|string|max:1000',
        ];
    }
}