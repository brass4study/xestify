<?php

declare(strict_types=1);

require_once BASE_PATH . '/src/validation/model/ValidationError.php';
require_once BASE_PATH . '/src/validation/model/ValidationResult.php';
require_once BASE_PATH . '/src/validation/contracts/FieldValidatorInterface.php';
require_once BASE_PATH . '/src/validation/FieldValidatorRegistry.php';
require_once BASE_PATH . '/src/validation/schema/SchemaFieldExtractor.php';
require_once BASE_PATH . '/src/validation/support/ValidationRules.php';
require_once BASE_PATH . '/src/validation/validators/StringFieldValidator.php';
require_once BASE_PATH . '/src/validation/validators/TextFieldValidator.php';
require_once BASE_PATH . '/src/validation/validators/NumberFieldValidator.php';
require_once BASE_PATH . '/src/validation/validators/BooleanFieldValidator.php';
require_once BASE_PATH . '/src/validation/validators/DateFieldValidator.php';
require_once BASE_PATH . '/src/validation/validators/TimestampFieldValidator.php';
require_once BASE_PATH . '/src/validation/validators/EmailFieldValidator.php';
require_once BASE_PATH . '/src/validation/validators/SelectFieldValidator.php';
require_once BASE_PATH . '/src/validation/DefaultFieldValidatorRegistryFactory.php';
require_once BASE_PATH . '/src/services/ValidationService.php';
