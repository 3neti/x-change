<?php

namespace LBHurtado\ModelInput\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;
use LBHurtado\ModelInput\Enums\Input;
use \Exception;

trait HasInputs
{
    public function inputs(): MorphMany
    {
        return $this->morphMany($this->getInputModelClassName(), 'model', 'model_type', $this->getModelKeyColumnName())
            ->latest('id');
    }

    public function setInput(string|Input $name, string $value): self
    {
        // Convert Inputs enum to its string value if provided
        $name = $name instanceof Input ? $name->value : $name;

        if (! $this->isValidInput($name, $value)) {
            throw new Exception('Input name is not valid');
        }

        return $this->forceSetInput($name, $value);
    }

    public function forceSetInput(string|Input $name, string $value): self
    {
        // Convert Channels enum to its string value if provided
        $name = $name instanceof Input ? $name->value : $name;

        // Normalize phone numbers to E.164 format without "+"
        if ($name === 'mobile') {
            $value = ltrim(phone($value, 'PH')->formatE164(), '+');
        }

        $this->inputs()->create([
            'name' => $name,
            'value' => $value,
        ]);

        return $this;
    }

    public function isValidInput(string|Input $name, ?string $value = null): bool
    {
        // Convert Channel enum to its string value if provided
        $input = $name instanceof Input ? $name : Input::tryFrom($name);

        // Ensure the channel is valid (exists in the Input enum)
        if (! $input instanceof Input) {
            return false;
        }

        // Perform validation using channel-specific rules
        $validator = Validator::make(['value' => $value], ['value' => $input->rules()]);

        return ! $validator->fails();
    }

    protected function getInputTableName(): string
    {
        $modelClass = $this->getInputModelClassName();

        return (new $modelClass)->getTable();
    }

    protected function getModelKeyColumnName(): string
    {
        return config('model-input.model_primary_key_attribute') ?? 'model_id';
    }

    protected function getInputModelClassName(): string
    {
        return config('model-input.input_model') ?? \LBHurtado\ModelInput\Models\Input::class;
    }

    public function __get($key)
    {
        // Check if the key matches any Input enum value
        if ($input = $this->getInputFromEnum($key)) {
            return $this->getInputAttribute($input->value);
        }

        // Delegate to the parent method for non-magic properties
        return parent::__get($key);
    }

    public function __set($key, $value)
    {
        // Check if the key matches any Channel enum value
        if ($input = $this->getInputFromEnum($key)) {

            $this->setInputAttribute($input->value, $value);
            return;
        }

        // Delegate to the parent method for non-magic properties
        parent::__set($key, $value);
    }

    protected function getInputAttribute(string $name): ?string
    {
        // Check if channels are already loaded to avoid querying the database
        $input = $this->relationLoaded('inputs')
            ? $this->inputs->firstWhere('name', $name) // Searches in the loaded relationship (collection)
            : $this->inputs()->where('name', $name)->first(); // Falls back to a database query if not loaded

        return $input?->value;
    }

    protected function setInputAttribute(string $name, string $value): void
    {
        $this->forceSetInput($name, $value);
    }

    private function getInputFromEnum(string $key): ?Input
    {
        // Attempt to match the key to any value in the Channels enum
        foreach (Input::cases() as $input) {
            if ($input->value === $key) {
                return $input;
            }
        }

        return null; // No match
    }

    public static function findByInput(string $inputName, string $inputValue): ?self
    {
        return static::whereHas('inputs', function (Builder $q) use ($inputName, $inputValue) {
            $q->where('name', $inputName);

            if ($inputName === 'mobile') {
                try {
                    $e164 = ltrim(phone($inputValue, 'PH')->formatE164(), '+');
                    $national = preg_replace('/\D+/', '', $inputValue);
                    $dialable = phone($inputValue, 'PH')->formatForMobileDialingInCountry('PH');
                    $dialable = preg_replace('/\D+/', '', $dialable);

                    $q->where(function ($sub) use ($e164, $national, $dialable) {
                        $sub->where('value', $e164)
                            ->orWhere('value', 'LIKE', "%{$national}%")
                            ->orWhere('value', 'LIKE', "%{$dialable}%");
                    });
                } catch (\Throwable $e) {
                    // nothing will match
                    $q->whereRaw('0 = 1');
                }
            } else {
                $q->where('value', $inputValue);
            }
        })->first();
    }

    public static function __callStatic($method, $parameters)
    {
        // Check if the method starts with "findBy"
        if (str_starts_with($method, 'findBy')) {
            // Dynamically determine the channel name
            $inputName = strtolower(str_replace('findBy', '', $method));

            // Ensure there are at least two parameters: the channel value
            if (!isset($parameters[0])) {
                throw new \InvalidArgumentException('Channel value is required');
            }
            $inputValue = $parameters[0];

            // Use the existing findByChannel method to find the model
            return static::findByInput($inputName, $inputValue);
        }

        // If the method does not match "findBy", pass it to the parent (if necessary)
        return parent::__callStatic($method, $parameters);
    }
}
