<?php

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): self {
        $validator = new self($data);
        $validator->validate($rules);
        return $validator;
    }

    public function validate(array $rules): void {
        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $value = $this->data[$field] ?? null;
            $isExplicitNumeric = in_array('numeric', $ruleList, true) || in_array('integer', $ruleList, true);

            foreach ($ruleList as $rule) {
                $ruleName = $rule;
                $ruleParam = null;

                if (str_contains($rule, ':')) {
                    [$ruleName, $ruleParam] = explode(':', $rule, 2);
                }

                switch ($ruleName) {
                    case 'required':
                        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                            $this->addError($field, "The {$field} field is required.");
                        }
                        break;

                    case 'string':
                        if ($value !== null && !is_string($value)) {
                            $this->addError($field, "The {$field} field must be a string.");
                        }
                        break;

                    case 'numeric':
                        if ($value !== null && !is_numeric($value)) {
                            $this->addError($field, "The {$field} field must be a number.");
                        }
                        break;

                    case 'integer':
                        if ($value !== null && filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $this->addError($field, "The {$field} field must be an integer.");
                        }
                        break;

                    case 'email':
                        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->addError($field, "The {$field} field must be a valid email address.");
                        }
                        break;

                    case 'min':
                        if ($value !== null) {
                            if ($isExplicitNumeric && is_numeric($value)) {
                                if ((float)$value < (float)$ruleParam) {
                                    $this->addError($field, "The {$field} must be at least {$ruleParam}.");
                                }
                            } else {
                                if (mb_strlen((string)$value) < (int)$ruleParam) {
                                    $this->addError($field, "The {$field} must be at least {$ruleParam} characters.");
                                }
                            }
                        }
                        break;

                    case 'max':
                        if ($value !== null) {
                            if ($isExplicitNumeric && is_numeric($value)) {
                                if ((float)$value > (float)$ruleParam) {
                                    $this->addError($field, "The {$field} must not exceed {$ruleParam}.");
                                }
                            } else {
                                if (mb_strlen((string)$value) > (int)$ruleParam) {
                                    $this->addError($field, "The {$field} must not exceed {$ruleParam} characters.");
                                }
                            }
                        }
                        break;

                    case 'in':
                        if ($value !== null) {
                            $options = explode(',', (string)$ruleParam);
                            if (!in_array((string)$value, $options, true)) {
                                $this->addError($field, "The selected {$field} is invalid.");
                            }
                        }
                        break;

                    case 'date':
                        if ($value !== null) {
                            $d = DateTime::createFromFormat('Y-m-d', $value);
                            if (!$d || $d->format('Y-m-d') !== $value) {
                                $this->addError($field, "The {$field} must be a valid date in YYYY-MM-DD format.");
                            }
                        }
                        break;
                }
            }
        }
    }

    private function addError(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function fails(): bool {
        return !$this->passes();
    }

    public function errors(): array {
        return $this->errors;
    }
}
