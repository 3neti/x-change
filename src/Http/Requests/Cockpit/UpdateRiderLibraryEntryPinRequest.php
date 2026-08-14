<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Requests\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use LBHurtado\XChange\Models\RiderLibraryEntry;

class UpdateRiderLibraryEntryPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $owner = $this->user();
        $entry = $this->route('riderLibraryEntry');

        return $owner instanceof Model
            && $entry instanceof RiderLibraryEntry
            && $entry->owner_type === $owner->getMorphClass()
            && (string) $entry->owner_id === (string) $owner->getKey();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'pinned' => ['required', 'boolean'],
        ];
    }
}
