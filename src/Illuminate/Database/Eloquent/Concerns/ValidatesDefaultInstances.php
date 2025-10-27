<?php

namespace Illuminate\Database\Eloquent\Concerns;

use Illuminate\Database\Eloquent\Exceptions\MissingForeignKeyException;

trait ValidatesDefaultInstances
{
    /**
     * Indicates if the model instance was created as a default instance.
     *
     * @var bool
     */
    protected $isDefaultInstance = false;

    /**
     * The foreign keys that are required for default instances.
     *
     * Format: ['foreign_key' => 'relationship_name', ...]
     *
     * @var array<string, string>
     */
    protected $requiredForeignKeys = [];

    /**
     * Mark the model instance as a default instance.
     *
     * @return $this
     */
    public function markAsDefaultInstance(): static
    {
        $this->isDefaultInstance = true;

        return $this;
    }

    /**
     * Determine if the model instance is a default instance.
     *
     * @return bool
     */
    public function isDefaultModelInstance(): bool
    {
        return $this->isDefaultInstance;
    }

    /**
     * Get the isDefaultInstance attribute.
     *
     * @return bool
     */
    public function getIsDefaultInstanceAttribute(): bool
    {
        return $this->isDefaultInstance;
    }

    /**
     * Get the required foreign keys for this model.
     *
     * @return array<string, string>
     */
    public function getRequiredForeignKeys(): array
    {
        return $this->requiredForeignKeys;
    }

    /**
     * Set the required foreign keys for this model.
     *
     * @param  array<string, string>  $keys
     * @return $this
     */
    public function setRequiredForeignKeys(array $keys): static
    {
        $this->requiredForeignKeys = $keys;

        return $this;
    }

    /**
     * Validate that all required foreign keys are present for default instances.
     *
     * @return void
     *
     * @throws \Illuminate\Database\Eloquent\Exceptions\MissingForeignKeyException
     */
    public function validateRequiredForeignKeys(): void
    {
        if (! $this->isDefaultInstance) {
            return;
        }

        $missingKeys = [];

        foreach ($this->requiredForeignKeys as $key => $relationship) {
            $value = $this->getAttribute($key);

            if ($value === null) {
                $missingKeys[] = [
                    'key' => $key,
                    'relationship' => $relationship,
                ];
            }
        }

        if (! empty($missingKeys)) {
            throw new MissingForeignKeyException($this, $missingKeys);
        }
    }

    /**
     * Override save method to ensure validation is called for default instances.
     */
    public function save(array $options = [])
    {
        if ($this->isDefaultInstance) {
            $this->validateRequiredForeignKeys();
        }

        return parent::save($options);
    }
}
