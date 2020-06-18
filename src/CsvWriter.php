<?php


	namespace MehrIt\Csv;


	use InvalidArgumentException;
	use RuntimeException;

	class CsvWriter
	{
		const BOM_UTF_8 = "\xEF\xBB\xBF";
		const BOM_UTF_16_BE = "\xFE\xFF";
		const BOM_UTF_16_LE = "\xFF\xFE";
		const BOM_UTF_32_BE = "\x00\x00\xFE\xFF";
		const BOM_UTF_32_LE = "\xFF\xFE\x00\x00";


		/**
		 * @var resource
		 */
		protected $target;

		/**
		 * @var resource
		 */
		protected $filter;

		protected $delimiter = ',';

		protected $linebreak = "\n";

		protected $enclosure = '"';

		protected $escape = '"';

		protected $alwaysQuote = false;

		protected $nullEnclosure;

		protected $inputEncoding;

		protected $outputEncoding = 'UTF-8';

		protected $anyDataWritten = false;

		protected $_replaceReg;

		protected $_specialChars;

		protected $illegalCharReplace = '';

		/**
		 * @var string[]|bool
		 */
		protected $columns = false;

		/**
		 * Creates a new instance
		 */
		public function __construct() {
			$this->inputEncoding = mb_internal_encoding();
			$this->nullEnclosure = chr(0);
		}

		/**
		 * Gets the CSV delimiter
		 * @return string The CSV delimiter
		 */
		public function getDelimiter(): string {
			return $this->delimiter;
		}

		/**
		 * Sets the CSV delimiter
		 * @param string $delimiter The CSV delimiter
		 * @return $this
		 */
		public function setDelimiter(string $delimiter): CsvWriter {

			if ($this->target)
				throw new RuntimeException('Delimiter must be set before opening CSV');

			$this->delimiter = $delimiter;

			return $this;
		}

		/**
		 * Gets the CSV linebreak
		 * @return string The CSV linebreak
		 */
		public function getLinebreak(): string {
			return $this->linebreak;
		}

		/**
		 * Sets the CSV linebreak
		 * @param string $linebreak
		 * @return $this
		 */
		public function setLinebreak(string $linebreak): CsvWriter {

			if ($this->target)
				throw new RuntimeException('Linebreak must be set before opening CSV');

			$this->linebreak = $linebreak;

			return $this;
		}

		/**
		 * Gets the enclosure char
		 * @return string|null The enclosure char
		 */
		public function getEnclosure(): ?string {
			return $this->enclosure;
		}

		/**
		 * Sets the enclosure char
		 * @param string|null $enclosure The enclosure char
		 * @return $this
		 */
		public function setEnclosure(?string $enclosure): CsvWriter {

			if ($this->target)
				throw new RuntimeException('Enclosure must be set before opening CSV');

			if ($enclosure === '')
				$enclosure = null;

			$this->enclosure = $enclosure;
			$this->enclosureReg = null;

			return $this;
		}

		/**
		 * Returns if fields are always quoted
		 * @return bool True if always quoted. Else false.
		 */
		public function getAlwaysQuote(): bool {
			return $this->alwaysQuote;
		}

		/**
		 * Sets if fields should always be quoted
		 * @param bool $alwaysQuote True if always quoted. Else false.
		 * @return $this
		 */
		public function setAlwaysQuote(bool $alwaysQuote): CsvWriter {

			if ($this->target)
				throw new RuntimeException('AlwaysQuote must be set before opening CSV');

			$this->alwaysQuote = $alwaysQuote;

			return $this;
		}

		/**
		 * Gets the replace for illegal characters which have to be removed. This might be necessary if not using an enclosure or an escape.
		 * @return string The replace for illegal characters which have to be removed. This might be necessary if not using an enclosure or an escape.
		 */
		public function getIllegalCharReplace(): string {
			return $this->illegalCharReplace;
		}

		/**
		 * Sets the replace for illegal characters which have to be removed. This might be necessary if not using an enclosure or an escape.
		 * @param string $illegalCharReplace The replace for illegal characters which have to be removed. This might be necessary if not using an enclosure or an escape.
		 * @return CsvWriter
		 */
		public function setIllegalCharReplace(string $illegalCharReplace): CsvWriter {
			$this->illegalCharReplace = $illegalCharReplace;

			return $this;
		}





		/**
		 * Gets the escape char for the enclosure
		 * @return string The escape char for the enclosure
		 */
		public function getEscape(): string {
			return $this->escape;
		}

		/**
		 * Sets the escape char for the enclosure
		 * @param string $escape The escape char for the enclosure
		 * @return $this
		 */
		public function setEscape(string $escape): CsvWriter {

			if ($this->target)
				throw new RuntimeException('Escape must be set before opening CSV');

			$this->escape    = $escape;
			$this->escapeReg = null;

			return $this;
		}

		/**
		 * Gets the input encoding
		 * @return string The input encoding
		 */
		public function getInputEncoding() : string{
			return $this->inputEncoding;
		}

		/**
		 * Sets the input encoding
		 * @param bool|string $inputEncoding The input encoding
		 * @return $this
		 */
		public function setInputEncoding($inputEncoding): CsvWriter{

			if ($this->target)
				throw new RuntimeException('InputEncoding must be set before opening CSV');

			$this->inputEncoding = $inputEncoding;

			return $this;
		}

		/**
		 * Gets the output encoding
		 * @return string The output encoding
		 */
		public function getOutputEncoding(): string {
			return $this->outputEncoding;
		}

		/**
		 * Sets the output encoding
		 * @param string $outputEncoding The output encoding
		 * @return $this
		 */
		public function setOutputEncoding(string $outputEncoding): CsvWriter {

			if ($this->target)
				throw new RuntimeException('OutputEncoding must be set before opening CSV');

			$this->outputEncoding = $outputEncoding;

			return $this;
		}


		/**
		 * Opens a new CSV file
		 * @param string|resource $target The resource or an URI. If a string is passed, a new resource will be created using fopen()
		 * @return $this
		 * @throws \Safe\Exceptions\FilesystemException
		 */
		public function open($target) : CsvWriter {

			if (is_string($target))
				$target = \Safe\fopen($target, 'w');
			elseif (!is_resource($target))
				throw new InvalidArgumentException('Expected an URI or an open resource, got ' . (($type = gettype($target)) == 'object' ? get_class($target) : $type));

			$this->target         = $target;
			$this->anyDataWritten = false;

			$this->resetCache();

			return $this;
		}

		/**
		 * Detaches the CSV writer from given stream
		 * @return $this
		 * @throws \Safe\Exceptions\FilesystemException
		 */
		public function detach() : CsvWriter {

			$this->target = null;

			return $this;
		}

		/**
		 * Closes the currently opened target file
		 * @return $this
		 * @throws \Safe\Exceptions\FilesystemException
		 */
		public function close() : CsvWriter {

			if ($this->target) {
				\Safe\fclose($this->target);

				$this->target = null;
			}

			return $this;
		}


		/**
		 * Sets the CSV columns. The column key must be passed as array key and is expected for writeData(). The column header must be passed as array value
		 * @param string[] $columns The columns. Header as value. Column key as key
		 * @param bool $output True if to output the column headers
		 * @return $this
		 * @throws \Safe\Exceptions\FilesystemException
		 * @throws \Safe\Exceptions\StreamException
		 */
		public function columns(array $columns, $output = true) : CsvWriter {

			$cols = [];
			foreach($columns as $key => $value) {
				$cols[is_int($key) ? $value : $key] = $value;
			}

			$this->columns = $cols;

			// output column headers
			if ($output)
				$this->writeLine($cols);

			return $this;
		}

		/**
		 * Writes the BOM (byte order mark) to the output
		 * @return CsvWriter
		 * @throws \Safe\Exceptions\FilesystemException
		 */
		public function writeByteOrderMark() {

			if (!is_resource($this->target))
				throw new RuntimeException('No target opened');
			if ($this->anyDataWritten)
				throw new RuntimeException('BOM must be written before any other data');


			switch($this->outputEncoding) {
				case 'UTF-8';
					\Safe\fwrite($this->target, self::BOM_UTF_8);
					break;
				case 'UTF-16BE';
					\Safe\fwrite($this->target, self::BOM_UTF_16_BE);
					break;
				case 'UTF-16LE';
					\Safe\fwrite($this->target, self::BOM_UTF_16_LE);
					break;
				case 'UTF-32BE';
					\Safe\fwrite($this->target, self::BOM_UTF_32_BE);
					break;
				case 'UTF-32LE';
					\Safe\fwrite($this->target, self::BOM_UTF_32_LE);
					break;
				default:
					throw new RuntimeException("Charset \"{$this->outputEncoding}\" does not have a BOM or it is not supported by this library");
			}

			$this->anyDataWritten = true;

			return $this;
		}


		/**
		 * Writes the given data to the CSV. Array keys must be a subset of the column keys passed to columns()
		 * @param array $columnValues The column values
		 * @return $this
		 * @throws \Safe\Exceptions\FilesystemException
		 * @throws \Safe\Exceptions\StreamException
		 */
		public function writeData(array $columnValues) : CsvWriter {

			$columns = $this->columns;

			if (!$columns)
				throw new RuntimeException('Columns must be set before using writeData()');

			if ($notExisting = array_diff_key($columnValues, $columns))
				throw new InvalidArgumentException('Unknown column(s): ' . implode(', ', array_keys($notExisting)));

			$fields = [];
			foreach($columns as $key => $header) {

				$fields[] = $columnValues[$key] ?? '';
			}

			return $this->writeLine($fields);
		}

		/**
		 * Writes a new line using the given field values
		 * @param array $fields The field values
		 * @return $this
		 * @throws \Safe\Exceptions\FilesystemException
		 * @throws \Safe\Exceptions\StreamException
		 */
		public function writeLine(array $fields) : CsvWriter {

			if (!is_resource($this->target))
				throw new RuntimeException('No target opened');

			// import class variables
			$enclosure   = $this->enclosure;
			$alwaysQuote = $this->alwaysQuote;
			$escape      = $this->escape;
			$delimiter   = $this->delimiter;


			$replaceReg = null;
			if ($enclosure !== null) {
				$enclosureReg = preg_quote($enclosure, '/');
				$escapeReg = preg_quote($escape, '/');

				$replaceReg = ($this->_replaceReg ?: $this->_replaceReg = $enclosureReg . ($escapeReg ? "|$escapeReg" : ''));

				$specialChars = ($this->_specialChars ?: $this->_specialChars = array_filter([$enclosure, $enclosure !== null ? $escape : null, $delimiter, ' ', "\r", "\n"], function ($v) {
					return $v !== '' && $v !== null;
				}));
			}
			else {
				$specialChars = ($this->_specialChars ?: $this->_specialChars = [$delimiter, "\r", "\n"]);
			}


			$line = implode($delimiter, array_map(function($value) use ($specialChars, $enclosure, $escape, $alwaysQuote, $replaceReg) {

				if ($enclosure !== null) {

					// check if enclosure is needed
					$needsEnclosure = $alwaysQuote;
					if (!$alwaysQuote) {
						foreach ($specialChars as $currChar) {
							if (strpos($value, $currChar) !== false) {
								$needsEnclosure = true;
								break;
							}
						}
					}

					if ($needsEnclosure) {
						// escape the enclosure and the escape char, when occurring within value
						$value = preg_replace_callback("/($replaceReg)/", function ($matches) use ($escape) {
							return $escape !== '' ? $escape . $matches[0] : $this->illegalCharReplace;
						}, $value);

						return "$enclosure$value$enclosure";
					}

				}
				else {
					$value = str_replace($specialChars, $this->illegalCharReplace, $value);
				}

				return $value;

			}, $fields)) . $this->linebreak;



			// convert encoding
			if ($this->inputEncoding !== $this->outputEncoding)
				$line = mb_convert_encoding($line, $this->outputEncoding, $this->inputEncoding);

			\Safe\fwrite($this->target, $line);

			$this->anyDataWritten = true;

			return $this;
		}

		/**
		 * Resets any cached values
		 */
		protected function resetCache() {
			$this->_replaceReg   = null;
			$this->_specialChars = null;
		}

	}