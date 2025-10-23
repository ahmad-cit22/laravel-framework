<?php

namespace Illuminate\Database\Eloquent\Relations\Concerns;

use Illuminate\Database\Eloquent\Model;

trait SupportsDefaultModels
{
    /**
     * Indicates if a default model instance should be used.
     *
     * Alternatively, may be a Closure or array.
     *
     * @var \Closure|array|bool
     */
    protected $withDefault;

    /**
     * Make a new related instance for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @return \Illuminate\Database\Eloquent\Model
     */
    abstract protected function newRelatedInstanceFor(Model $parent);

    /**
     * Return a new model instance in case the relationship does not exist.
     *
     * @param  \Closure|array|bool  $callback
     * @return $this
     */
    public function withDefault($callback = true)
    {
        $this->withDefault = $callback;

        return $this;
    }

    /**
     * Get the default value for this relation.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    protected function getDefaultFor(Model $parent)
    {
        if (! $this->withDefault) {
            return;
        }

        $instance = $this->newRelatedInstanceFor($parent);

        // Set required foreign keys based on relationship type (if this instance uses ValidatesDefaultInstances trait)
        $this->setRequiredForeignKeysForDefaultInstance($instance);

        if (is_callable($this->withDefault)) {
            return call_user_func($this->withDefault, $instance, $parent) ?: $instance;
        }

        if (is_array($this->withDefault)) {
            $instance->forceFill($this->withDefault);
        }

        return $instance;
    }

    /**
     * Set the required foreign keys for the default instance based on relationship type.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $instance
     * @return void
     */
    protected function setRequiredForeignKeysForDefaultInstance(Model $instance)
    {
        // only continue if this instance uses the ValidatesDefaultInstances trait
        $traitsUsed = class_uses_recursive($instance);
        if (! in_array('Illuminate\Database\Eloquent\Concerns\ValidatesDefaultInstances', $traitsUsed)) {
            return;
        }

        $instance->markAsDefaultInstance();

        $potentialKeys = [];

        if ($this instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
            $potentialKeys[$this->getForeignKeyName()] = 'belongsTo';
        } elseif ($this instanceof \Illuminate\Database\Eloquent\Relations\MorphTo) {
            $potentialKeys[$this->getForeignKeyName()] = 'morphTo';
            $potentialKeys[$this->getMorphType()] = 'morphTo';
        } elseif ($this instanceof \Illuminate\Database\Eloquent\Relations\HasOne ||
            $this instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
            $potentialKeys[$this->getForeignKeyName()] = 'hasOneOrMany';
        } elseif ($this instanceof \Illuminate\Database\Eloquent\Relations\MorphOne ||
            $this instanceof \Illuminate\Database\Eloquent\Relations\MorphMany) {
            $potentialKeys[$this->getForeignKeyName()] = 'morphOneOrMany';
            $potentialKeys[$this->getMorphType()] = 'morphOneOrMany';
        }

        // Filter out the nullable columns
        $requiredKeys = $this->filterNonNullableKeys($instance, $potentialKeys);

        $instance->setRequiredForeignKeys($requiredKeys);
    }

    /**
     * Filter out nullable foreign keys, keeping only non-nullable (required) ones.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $instance
     * @param  array  $potentialKeys
     * @return array
     */
    protected function filterNonNullableKeys(Model $instance, array $potentialKeys)
    {
        $requiredKeys = [];
        $schema = $instance->getConnection()->getSchemaBuilder();
        $tableName = $instance->getTable();

        foreach ($potentialKeys as $columnName => $relationshipType) {
            try {
                $columnInfo = $schema->getColumnListing($tableName);

                if (in_array($columnName, $columnInfo)) {
                    $columns = $schema->getColumns($tableName);
                    $columnDetails = collect($columns)->firstWhere('name', $columnName);

                    if ($columnDetails && ! $columnDetails['nullable']) {
                        $requiredKeys[$columnName] = $relationshipType;
                    }
                }
            } catch (\Exception $e) {
                // If can't determine nullability, err on the side of caution
                // and don't require the key (avoiding false positives)
                continue;
            }
        }

        return $requiredKeys;
    }
}
