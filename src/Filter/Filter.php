<?php

namespace Filter;

/**
 * Filter manager
 *
 * Runs data through registered filters
 */
class Filter
{
    /**
     * @var array Registered filters
     */
    protected $filters = array();

    /**
     * Filter values
     * @param array $vvalues Array of named values
     * @param array $rules Array of field names to list of rules concatenated by '|' or as an array
     * @return array Filtered values
     */
    public function filter(array $values, array $rules)
    {
        $rules = $this->parseRules($rules);
        $filtered = array();

        foreach ($values as $field => $value) {
            if (array_key_exists($field, $rules)) {
                // Call each filter
                foreach ($rules[$field] as $filter => $args) {
                    $value = $this->callFilter($filter, $value, $args);
                }
            }
            $filtered[$field] = $value;
        }

        return $filtered;
    }

    /**
     * Filter a single value
     * @param mixed $value
     * @param array|string $rules
     * @return mixed Filtered value
     */
    public function filterOne($value, $rules) {
        return $this->filter(['_' => $rules], ['_' => $value])['_'];
    }

    /**
     * Register a callback filter
     * @param string $name
     * @param callable $filter
     * @throws Exception If filter already registered
     * @throws Exception If filter is not Callable
     */
    public function registerFilter($name, $filter)
    {
        if (array_key_exists($name, $this->filters)) {
            throw new \Exception("Filter named '$name' already registered");
        }

        if (is_string($filter)) {
            $filter = new $filter;
        }

        if (!is_callable($filter)) {
            throw new \Exception('Filter should be callable');
        }

        $this->filters[$name] = $filter;
    }

    /**
     * Unregister a callback filter
     * @param string $name
     */
    public function unregisterFilter($name) {
        if (array_key_exists($name, $this->filters)) {
            unset($this->filters[$name]);
        }
    }

    /**
     * Get a list of registered filters
     * @return array List of registered filters
     */
    public function getFilters()
    {
        return array_keys($this->filters);
    }

    /**
     * Call a filter
     * @param string $name Filter name
     * @param mixed $value Value to filter
     * @param array  $args Arguments to the filter
     * @return mixed Filtered value
     */
    protected function callFilter($name, $value, $args=array())
    {
        if (!array_key_exists($name, $this->filters)) {
            throw new \Exception("No filter named '$name' registered");
        }

        return call_user_func($this->filters[$name], $value, $args);
    }

    /**
     * Return filter rules for each field
     *
     * Example input: ['field' => 'strtoupper|lcfirst|ltrim:.,",",_']
     * Output: ['field' => ['strtoupper' => [], ... ltrim' => ['.', ',', '_']]]
     *
     * Each filter name is separated by a pipe.
     * Filters can have arguments, specified after a colon, which are split by
     * comma using the same rules as str_getcsv.
     *
     * @param string $rules
     * @return array Parsed rules
     */
    protected function parseRules($rules) {
        foreach ($rules as $key => &$field_rules)  {
            $filters = is_string($field_rules) ? explode('|', $field_rules) : $field_rules;
            $field_rules = $this->parseFilters($filters);
        }

        return $rules;
    }

    /**
     * Parse a specific filter
     * @see Filter::parseRules() for syntax information
     * @param array $filters
     * @return array Filter rules
     */
    protected function parseFilters($filters) {
        foreach ($filters as $index => $filter) {
            if (strpos($filter, ':') === false) {
                $filters[$filter] = [];
            } else {
                list($name, $args) = explode(':', $filter, 2);
                $args = str_getcsv($args);
                $filters[$name] = $args;
            }

            unset($filters[$index]);
        }

        return $filters;
    }

    /**
     * Register some default filters
     */
    public function registerDefaultFilters()
    {
        $this->registerFilter('trim', function($value, array $args) {
            if (count($args) > 0) {
                return trim($value, implode($args));
            }
            return trim($value);
        });

        $this->registerFilter('clean_trim', function($value, array $args) {
            return trim(preg_replace("/\s+/", " ", $value));
        });

        $this->registerFilter('ltrim', function($value, array $args) {
            if (count($args) > 0) {
                return ltrim($value, implode($args));
            }
            return ltrim($value);
        });

        $this->registerFilter('rtrim', function($value, array $args) {
            if (count($args) > 0) {
                return rtrim($value, implode($args));
            }
            return rtrim($value);
        });

        $this->registerFilter('upper', function($value, array $args) {
            return strtoupper($value);
        });

        $this->registerFilter('lower', function($value, array $args) {
            return strtolower($value);
        });

        $this->registerFilter('capfirst', function($value, array $args) {
            return ucfirst($value);
        });

        $this->registerFilter('lowerfirst', function($value, array $args) {
            return lcfirst($value);
        });

        $this->registerFilter('null', function($value, array $args) {
            if (empty($value)) {
                return null;
            }
            return $value;
        });

        $this->registerFilter('slug', function($value, array $args) {
            if (count($args) > 0) {
              return Str::slug($value, implode($args));
            }
            return Str::slug($value);
        });

        $this->registerFilter('bool', function($value, array $args) {
            return $value ? true : false;
        });

        $this->registerFilter('to_json', function($value, array $args) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        });

        $this->registerFilter('to_array', function($value, array $args) {
            return json_decode($value, true);
        });

        $this->registerFilter('to_object', function($value, array $args) {
            return json_decode($value);
        });
    }
}
