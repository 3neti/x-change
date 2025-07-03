<?php

namespace LBHurtado\ModelInput\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;
use LBHurtado\ModelInput\Enums\InputType;
use \Exception;

trait HasInputs
{
    public function inputs(): MorphMany
    {
        return $this->morphMany($this->getInputModelClassName(), 'model', 'model_type', $this->getModelKeyColumnName())
            ->latest('id');
    }

    public function setInput(string|InputType $name, string $value): self
    {
        // Convert Inputs enum to its string value if provided
        $name = $name instanceof InputType ? $name->value : $name;

        if (! $this->isValidInput($name, $value)) {
            throw new Exception('Input name is not valid');
        }

        return $this->forceSetInput($name, $value);
    }

    public function forceSetInput(string|InputType $name, string $value): self
    {
        // Convert Channels enum to its string value if provided
        $name = $name instanceof InputType ? $name->value : $name;

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

    public function isValidInput(string|InputType $name, ?string $value = null): bool
    {
        // Convert Channel enum to its string value if provided
        $input = $name instanceof InputType ? $name : InputType::tryFrom($name);

        // Ensure the channel is valid (exists in the Input enum)
        if (! $input instanceof InputType) {
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
        // 1) If there’s a real attribute, mutator or loaded relation, let Eloquent handle it:
        if (
            array_key_exists($key, $this->attributes)
            || $this->hasGetMutator($key)
            || $this->relationLoaded($key)
        ) {
            return parent::__get($key);
        }

        // 2) Otherwise, see if it’s one of our “inputs”
        if ($input = $this->getInputFromEnum($key)) {
            return $this->getInputAttribute($input->value);
        }

        // 3) Fall back to normal magic
        return parent::__get($key);
    }

    public function __set($key, $value)
    {
        // 1) If it’s a real attribute or mutator, defer to Eloquent
        if (
            array_key_exists($key, $this->attributes)
            || $this->hasSetMutator($key)
        ) {
            parent::__set($key, $value);
            return;
        }

        // 2) Otherwise, if it’s one of our “inputs”
        if ($input = $this->getInputFromEnum($key)) {
            $this->forceSetInput($input->value, $value);
            return;
        }

        parent::__set($key, $value);
    }

//    public function __get($key)
//    {
//        // Check if the key matches any Input enum value
//        if ($input = $this->getInputFromEnum($key)) {
//            return $this->getInputAttribute($input->value);
//        }
//
//        // Delegate to the parent method for non-magic properties
//        return parent::__get($key);
//    }
//
//    public function __set($key, $value)
//    {
//        // Check if the key matches any Channel enum value
//        if ($input = $this->getInputFromEnum($key)) {
//
//            $this->setInputAttribute($input->value, $value);
//            return;
//        }
//
//        // Delegate to the parent method for non-magic properties
//        parent::__set($key, $value);
//    }

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

    private function getInputFromEnum(string $key): ?InputType
    {
        // Attempt to match the key to any value in the Channels enum
        foreach (InputType::cases() as $input) {
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

    public function input(string $name): ?string
    {
        return $this->inputs()->where('name',$name)->value('value');
    }
}
