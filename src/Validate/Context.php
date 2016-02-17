<?php

namespace Lullabot\AMP\Validate;

use Lullabot\AMP\Spec\ValidationResultStatus;
use Lullabot\AMP\Spec\ValidationErrorSeverity;
use Lullabot\AMP\Spec\ValidationErrorCode;
use Lullabot\AMP\Spec\ValidationError;

/**
 * Class Context
 * @package Lullabot\AMP\Validate
 *
 * This class is a PHP port of the Context class in validator.js
 * (see https://github.com/ampproject/amphtml/blob/master/validator/validator.js )
 *
 * The static method severityFor() is a normal top-level function in validator.js but has been incorporated
 * into this class for convenience.
 *
 * The main difference between the PHP Port and the js version is that the Context class here will be working with a DOM
 * style parser (PHP dom extension) while it was working with an event based/sax style in validator.js
 *
 */
class Context
{
    /** @var \DOMElement */
    protected $tag = null;
    /** @var \SplObjectStorage */
    protected $tagspecs_validated;
    protected $mandatory_alternatives_satisfied = []; // Set of strings
    protected $max_errors = -1;

    public function __construct($max_errors = -1)
    {
        $this->tagspecs_validated = new \SplObjectStorage();
        $this->max_errors = $max_errors;
    }

    public function substituteTag(\DOMElement $new_tag)
    {
        $this->tag = $new_tag;
    }

    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @param $code
     * @param array $params
     * @param string $spec_url
     * @param SValidationResult $validationResult
     * @return bool
     */
    public function addError($code, array $params, $spec_url, SValidationResult $validationResult)
    {
        if (empty($spec_url)) {
            $spec_url = '';
        }

        $line = $this->tag->getLineNo();
        return $this->addErrorWithLine($line, $code, $params, $spec_url, $validationResult);
    }

    /**
     * @param $line
     * @param $validation_error_code
     * @param array $params
     * @param $spec_url
     * @param SValidationResult $validation_result
     * @return bool
     */
    public function addErrorWithLine($line, $validation_error_code, array $params, $spec_url, SValidationResult $validation_result)
    {
        $progress = $this->getProgress($validation_result);
        if ($progress['complete']) {
            assert($validation_result->status === ValidationResultStatus::FAIL, 'Early PASS exit without full verification');
            return false;
        }

        $severity = self::severityFor($validation_error_code);
        if ($severity !== ValidationErrorSeverity::WARNING) {
            $validation_result->status = ValidationResultStatus::FAIL;
        }

        if ($progress['wants_more_errors']) {
            $error = new ValidationError();
            $error->severity = $severity;
            $error->code = $validation_error_code;
            $error->params = $params;
            $error->line = $line;
            // dont know the column number unfortunately
            $error->spec_url = $spec_url;
            assert(isset($validation_result->errors));
            $validation_result->errors[] = $error;
        }

        return true;
    }

    /**
     * @param ParsedTagSpec $parsed_tag_spec
     * @return bool
     */
    public function recordTagspecValidated(ParsedTagSpec $parsed_tag_spec)
    {
        $duplicate = $this->tagspecs_validated->contains($parsed_tag_spec);
        if (!$duplicate) {
            $this->tagspecs_validated->attach($parsed_tag_spec);
        }
        return !$duplicate;
    }

    public function getMandatoryAlternativesSatisfied()
    {
        return $this->mandatory_alternatives_satisfied;
    }

    /**
     * @param string $satisfied
     */
    public function recordMandatoryAlternativesSatisfied($satisfied)
    {
        $this->mandatory_alternatives_satisfied[$satisfied] = 1;
    }

    /**
     * @param SValidationResult $validation_result
     * @return array
     */
    public function getProgress(SValidationResult $validation_result)
    {
        if ($this->max_errors === -1) {
            return ['complete' => false, 'wants_more_errors' => true];
        }

        if ($this->max_errors === 0) {
            return ['complete' => $validation_result->status === ValidationResultStatus::FAIL, 'wants_more_errors' => false];
        }

        $wants_more_errors = count($validation_result->errors) < $this->max_errors;
        return ['complete' => !$wants_more_errors, 'wants_more_errors' => $wants_more_errors];
    }

    /**
     * @return \SplObjectStorage
     */
    public function getTagspecsValidated()
    {
        return $this->tagspecs_validated;
    }

    public static function severityFor($validation_error_code)
    {
        // @todo make more error codes less severe as we're going to be able to fix them
        if ($validation_error_code === ValidationErrorCode::DEPRECATED_TAG) {
            return ValidationErrorSeverity::WARNING;
        } else if ($validation_error_code == ValidationErrorCode::DEPRECATED_ATTR) {
            return ValidationErrorSeverity::WARNING;
        }

        return ValidationErrorSeverity::ERROR;
    }
}

