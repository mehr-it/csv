<?php


	namespace MehrIt\Csv;


	use Closure;
	use DateTime;
	use DateTimeZone;
	use Exception;
	use InvalidArgumentException;
	use Safe\Exceptions\JsonException;
	use Throwable;

	trait ConvertsValues
	{

		protected $converters = [];

		protected $defaultDecimalSeparator = '.';


		/**
		 * Sets the converter with given name
		 * @param string $name The name
		 * @param callable $callback The callback performing the conversion
		 * @return $this
		 */
		public function addConverter(string $name, callable $callback) {
			$this->converters[$name] = $callback;

			return $this;
		}

		/**
		 * Sets the default decimal separator
		 * @param string $decimalSeparator The default decimal separator
		 * @return $this
		 */
		public function setDefaultDecimalSeparator(string $decimalSeparator) {
			$this->defaultDecimalSeparator = $decimalSeparator;

			return $this;
		}

		/**
		 * Converts the given value using given converter(s)
		 * @param mixed $value The value
		 * @param string|Closure|string[]|Closure[] $convert The converter
		 * @return mixed The converted value
		 */
		protected function convertValue($value, $convert) {

			if (is_array($value)) {
				foreach($value as &$curr) {
					$curr = $this->convertValue($curr, $convert);
				}

				return $value;
			}

			if (is_string($convert)) {

				// parse converter pipe
				$converters = explode('|', $convert);

				if (count($converters) === 1) {
					// single converter

					// extracts arguments
					$args          = explode(':', $convert);
					$converterName = array_shift($args);

					// either use custom converter or build in method
					if ($cb = ($this->converters[$converterName] ?? null))
						return call_user_func($cb, $value, ...$args);
					elseif (method_exists($this, ($method = 'convert' . ucfirst($converterName))))
						return $this->{$method}($value, ...$args);
				}
				else {

					// multiple converters
					foreach ($converters as $currConverter) {
						$value = $this->convertValue($value, $currConverter);
					}

					return $value;
				}
			}
			elseif (is_array($convert)) {
				foreach ($convert as $curr) {
					$value = $this->convertValue($value, $curr);
				}

				return $value;
			}
			elseif ($convert instanceof Closure) {
				return $convert($value);
			}

			throw new InvalidArgumentException('Convert must either be a string or callable');
		}


		/**
		 * Returns null if value is null, empty string or string only containing whitespaces
		 * @param mixed $value The value
		 * @return mixed The converted value
		 */
		protected function convertDefaultNull($value) {
			return $this->convertDefault($value);
		}

		/**
		 * Returns the given default value if value is null, empty string or string only containing whitespaces
		 * @param mixed $value The value
		 * @param mixed|null $default The default value
		 * @return mixed The converted value
		 */
		protected function convertDefault($value, $default = null) {
			if (!is_object($value) && trim($value) === '' && $value !== false)
				return $default;

			return $value;
		}

		/**
		 * Removes all whitespaces before and after text
		 * @param string|null $value The value
		 * @return string The converted value
		 */
		protected function convertTrim(?string $value) {

			return trim($value);
		}

		/**
		 * Converts the text to uppercase
		 * @param string|null $value The value
		 * @return string|null The converted value
		 */
		protected function convertUpper(?string $value): ?string {
			if ($value === null)
				return null;
			
			return mb_convert_case($value, MB_CASE_UPPER, $this->outputEncoding);
		}

		/**
		 * Converts the text to lowercase
		 * @param string|null $value The value
		 * @return string|null The converted value
		 */
		protected function convertLower(?string $value): ?string {
			if ($value === null)
				return null;
			
			return mb_convert_case($value, MB_CASE_LOWER, $this->outputEncoding);
		}

		/**
		 * Converts the value as boolean. "false", "False", "FALSE", "0" and "" are considered as `false`. All other values are converted to `true`
		 * @param string $value The value
		 * @return bool The converted value
		 */
		protected function convertBool($value): bool {
			$value = $this->convertLower(trim($value));

			return !in_array($value, [
				'false',
				'0',
				'',
			], true);
		}

		/**
		 * Converts the given value to a number
		 * @param string|null $value The value
		 * @param string|null $decimalSeparator The decimal separator to use for parsing (must be UTF-8 encoded). If empty, the default decimal separator is used
		 * @param string|null $thousandsSeparator The thousands separator to remove
		 * @return string|null The number as string (BCMath compatible) or null if value cannot be converted to a number
		 */
		protected function convertNumber(?string $value, string $decimalSeparator = null, string $thousandsSeparator = null): ?string {
			$value = trim($value);

			if ($thousandsSeparator)
				$value = str_replace($thousandsSeparator, '', $value);
			
			$ds        = trim($decimalSeparator) !== '' ? $decimalSeparator : $this->defaultDecimalSeparator;
			$dsEscaped = preg_quote($ds, '/');

			// check if is valid number
			if (!preg_match("/^\\-?[0-9]*({$dsEscaped}[0-9]+)?$/", $value))
				return null;

			if ($ds !== '.')
				$value = preg_replace("/{$dsEscaped}/", '.', $value, 1);

			return $value;
		}

		/**
		 * Converts the given value to a number and strips off any decimals
		 * @param string|null $value The value
		 * @param string|null $decimalSeparator The decimal separator to use for parsing (must be UTF-8 encoded). If empty, the default decimal separator is used
		 * @param string|null $thousandsSeparator The thousands separator to remove
		 * @return string|null The number as string (BCMath compatible) or null if value cannot be converted to a number
		 */
		protected function convertInt(?string $value, string $decimalSeparator = null, string $thousandsSeparator = null) {

			$value = $this->convertNumber($value, $decimalSeparator, $thousandsSeparator);

			if (mb_strpos($value, '.', 0, $this->outputEncoding) !== false)
				return null;

			return $value;
		}

		/**
		 * Converts the given value to a DateTime object
		 * @param string $value The value
		 * @param string|null $timezone The timezone to interpret dates without timezone information
		 * @return DateTime The date
		 * @throws Exception
		 */
		protected function convertDate(?string $value, string $timezone = null) {

			if (trim($value) === '')
				return null;

			if ($timezone)
				$timezone = new DateTimeZone($timezone);

			try {
				return new DateTime($value, $timezone);
			}
			catch (Throwable $exception) {
				return null;
			}
		}

		/**
		 * Splits the value by the given delimiter
		 * @param string|null $value The value
		 * @param string|null $delimiter The delimiter
		 * @return array The array
		 */
		protected function convertSplit($value, string $delimiter = '|') {
			if (trim($value) === '')
				return null;

			return explode($delimiter, "$value");
		}

		/**
		 * Parses the value as JSON
		 * @param string|null $value The value
		 * @param string $type 'array' or 'object'
		 * @return mixed|null The JSON data
		 * @throws \Safe\Exceptions\JsonException
		 */
		protected function convertJson($value, string $type = 'array') {

			if (trim($value) === '')
				return null;

			// JSON requires UTF-8
			if ($this->outputEncoding !== 'UTF-8')
				$value = mb_convert_encoding($value, 'UTF-8', $this->outputEncoding);

			try {
				return \Safe\json_decode($value, $type === 'array');
			}
			catch (JsonException $ex) {
				return null;
			}
		}

	}