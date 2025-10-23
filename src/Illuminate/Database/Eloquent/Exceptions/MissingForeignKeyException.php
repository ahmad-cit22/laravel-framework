<?php

namespace Illuminate\Database\Eloquent\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class MissingForeignKeyException extends LogicException
{
    /**
     * The model instance that failed validation.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * The missing foreign keys.
     *
     * @var array
     */
    protected $missingKeys;

    /**
     * Create a new missing foreign key exception instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array  $missingKeys
     * @return void
     */
    public function __construct(Model $model, array $missingKeys)
    {
        $this->model = $model;
        $this->missingKeys = $missingKeys;

        $modelClass = get_class($model);
        $keysList = $this->formatMissingKeys($missingKeys);

        parent::__construct(
            "Cannot save default model instance [{$modelClass}]. " .
            "Missing required foreign key(s): {$keysList}. " .
            "Ensure setting the foreign key value(s) before saving, or create the model normally instead of using withDefault()."
        );
    }

    /**
     * Get the model instance that failed validation.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Get the missing foreign keys.
     *
     * @return array
     */
    public function getMissingKeys()
    {
        return array_column($this->missingKeys, 'key');
    }

    /**
     * Format the missing keys for the error message.
     *
     * @param  array  $missingKeys
     * @return string
     */
    protected function formatMissingKeys(array $missingKeys)
    {
        $formatted = [];

        foreach ($missingKeys as $keyInfo) {
            $formatted[] = "'{$keyInfo['key']}' (for {$keyInfo['relationship']} relationship)";
        }

        return implode(', ', $formatted);
    }
}
