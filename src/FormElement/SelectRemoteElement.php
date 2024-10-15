<?php

namespace IMEdge\Web\Select2\FormElement;

use IMEdge\Web\Select2\BaseSelect2Lookup;
use ipl\Html\FormElement\SelectElement;
use RuntimeException;

class SelectRemoteElement extends SelectElement
{
    protected ?BaseSelect2Lookup $lookup = null;

    public function __construct($name, $attributes = null)
    {
        if (is_array($attributes) && isset($attributes['lookup'])) {
            $this->lookup = $attributes['lookup'];
            unset($attributes['lookup']);
        } else {
            throw new RuntimeException('SelectRemoteElement requires a Lookup');
        }
        parent::__construct($name, $attributes);
    }

    public function setValue($value)
    {
        if ($this->lookup && (is_string($value) || is_int($value))) {
            if ($pair = $this->lookup->getOptionalPair($value)) {
                $this->optionContent[$pair['id']] = parent::makeOption($pair['id'], $pair['text']);
            }
        }

        return parent::setValue($value);
    }
}
