<?php

namespace LBHurtado\Contact\Traits;

trait HasAdditionalAttributes
{
    const NAME_FIELD = 'name';
    const EMAIL_FIELD = 'email';
    const BIRTH_DATE = 'birth_date';
    const ADDRESS_FIELD = 'address';
    const GROSS_MONTHLY_INCOME_FIELD = 'gross_monthly_income';

    public function initializeHasAdditionalAttributes(): void
    {
        $this->mergeFillable([
            self::NAME_FIELD,
            self::EMAIL_FIELD,
            self::BIRTH_DATE,
            self::ADDRESS_FIELD,
            self::GROSS_MONTHLY_INCOME_FIELD,
        ]);
    }

    // Setters and Getters for each field

    public function setNameAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::NAME_FIELD, $value);
        }

        return $this;
    }

    public function getNameAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::NAME_FIELD) ?? '';
    }

    public function setEmailAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::EMAIL_FIELD, $value);
        }

        return $this;
    }

    public function getEmailAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::EMAIL_FIELD) ?? '';
    }

    public function setBirthDateAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::BIRTH_DATE, $value);
        }

        return $this;
    }

    public function getBirthDateAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::BIRTH_DATE) ?? '';
    }

    public function setAddressAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::ADDRESS_FIELD, $value);
        }

        return $this;
    }

    public function getAddressAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::ADDRESS_FIELD) ?? '';
    }

    public function setGrossMonthlyIncomeAttribute(?string $value): self
    {
        if ($value !== null) {
            $this->getAttribute('meta')->set(self::GROSS_MONTHLY_INCOME_FIELD, $value);
        }

        return $this;
    }

    public function getGrossMonthlyIncomeAttribute(): ?string
    {
        return $this->getAttribute('meta')->get(self::GROSS_MONTHLY_INCOME_FIELD) ?? '';
    }
}
