<?php


	namespace MehrIt\Csv;


	use Closure;
	use Generator;
	use RuntimeException;
	use Safe\Exceptions\FilesystemException;

	class CsvReader
	{
		use ConvertsValues;

		const BOM_UTF_8 = "\xEF\xBB\xBF";
		const BOM_UTF_16_BE = "\xFE\xFF";
		const BOM_UTF_16_LE = "\xFF\xFE";
		const BOM_UTF_32_BE = "\x00\x00\xFE\xFF";
		const BOM_UTF_32_LE = "\xFF\xFE\x00\x00";

		protected $firstLine = false;


		/**
		 * @var resource
		 */
		protected $source;

		protected $delimiter = ',';

		protected $enclosure = '"';

		protected $escape = '"';

		/**
		 * @var string The encoding of the file (specified by user)
		 */
		protected $inputEncoding = 'UTF-8';

		/**
		 * @var string The internal encoding, which str_getcsv expects (set by constructor)
		 */
		protected $internalEncoding;

		/**
		 * @var string The output encoding for data arrays (default detected by constructor)
		 */
		protected $outputEncoding;

		/**
		 * @var null|string[]
		 */
		protected $columns = null;

		/**
		 * @var string[]|Closure[]|string[][]|Closure[][]
		 */
		protected $casts = [];

		/**
		 * @var array
		 */
		protected $emptyRow = [];

		/**
		 * CsvReader constructor.
		 */
		public function __construct() {

			// detect system encodings
			$internalEncoding       = mb_internal_encoding();
			$this->internalEncoding = $internalEncoding;
			$this->outputEncoding   = $internalEncoding;
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
		 * @return CsvReader
		 */
		public function setDelimiter(string $delimiter): CsvReader {

			if ($this->source)
				throw new RuntimeException('Delimiter must be set before opening CSV');

			$this->delimiter = $delimiter;

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
		 * @return CsvReader
		 */
		public function setEnclosure(?string $enclosure): CsvReader {

			if ($this->source)
				throw new RuntimeException('Enclosure must be set before opening CSV');

			if ($enclosure == '' || $enclosure === null)
				$enclosure = null;

			$this->enclosure = $enclosure;

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
		 * @return CsvReader
		 */
		public function setEscape(string $escape): CsvReader {

			if ($this->source)
				throw new RuntimeException('Escape must be set before opening CSV');

			$this->escape = $escape;

			return $this;
		}

		/**
		 * Gets the input encoding (the encoding of the file)
		 * @return string The input encoding (the encoding of the file)
		 */
		public function getInputEncoding(): string {
			return $this->inputEncoding;
		}

		/**
		 * Sets the input encoding (the encoding of the file)
		 * @param string $inputEncoding The input encoding (the encoding of the file)
		 * @return CsvReader
		 */
		public function setInputEncoding(string $inputEncoding): CsvReader {

			if ($this->source)
				throw new RuntimeException('InputEncoding must be set before opening CSV');

			$this->inputEncoding = $inputEncoding;

			return $this;
		}

		/**
		 * Gets the output encoding (the encoding for returned data)
		 * @return string The output encoding (the encoding for returned data)
		 */
		public function getOutputEncoding(): string {
			return $this->outputEncoding;
		}

		/**
		 * Sets the output encoding (the encoding for returned data)
		 * @param string $outputEncoding The output encoding (the encoding for returned data)
		 * @return CsvReader
		 */
		public function setOutputEncoding(string $outputEncoding): CsvReader {

			if ($this->source)
				throw new RuntimeException('OutputEncoding must be set before opening CSV');

			$this->outputEncoding = $outputEncoding;

			return $this;
		}

		/**
		 * Gets the CSV column names
		 * @return string[]|null The CSV column names or null if unspecified
		 */
		public function getColumns(): ?array {
			return $this->columns;
		}

		/**
		 * Checks if all given columns exist in the column list
		 * @param string|string[] $columns The column name(s)
		 * @return bool True if all given columns exist. Else false.
		 */
		public function columnsExist($columns) : bool {

			if ($this->columns === null)
				throw new RuntimeException('Columns must be read or set before using columnsExist()');

			if (!is_array($columns))
				$columns = [$columns];

			// check if all passed columns exist
			$existingColumnMap = array_fill_keys($this->columns, true);
			foreach($columns as $curr) {
				if (!($existingColumnMap[$curr] ?? false))
					return false;
			}

			return true;
		}

		/**
		 * Sets the CSV column names. NOTE: column names from first line can be read using readColumns() method
		 * @param string[]|null $columns The CSV column names
		 * @return CsvReader
		 */
		public function setColumns(?array $columns): CsvReader {
			$this->columns = $columns;

			// Prepare an empty row, which contains all columns. We use this array to create new data, so we do not have to build it again for each line
			$this->emptyRow = array_fill_keys($columns, null);

			return $this;
		}

		/**
		 * Gets the casts for the given columns
		 * @return Closure[]|Closure[][]|string[]|string[][] The casts for the given columns
		 */
		public function getCasts(): array {
			return $this->casts;
		}

		/**
		 * Sets the casts for the given columns
		 * @param Closure[]|Closure[][]|string[]|string[][] $casts The casts for the given columns
		 * @return CsvReader
		 */
		public function setCasts(array $casts) {
			$this->casts = $casts;

			return $this;
		}


		/**
		 * Opens CSV file for reading
		 * @param string|resource $source The resource or an URI
		 * @param bool $bomDetection Allows to deactivate BOM detection. This is useful when source does not contain BOM and does not support seeking.
		 * @return CsvReader
		 * @throws FilesystemException
		 */
		public function open($source, bool $bomDetection = true): CsvReader {

			if (!is_resource($source))
				$source = \Safe\fopen($source, 'r');

			$this->source = $source;

			$inputEncoding = $this->inputEncoding;
			$readEncoding = $inputEncoding;

			// remove byte order marks
			if ($bomDetection) {
				if ($inputEncoding === 'UTF-8') {
					if (\Safe\fread($this->source, 3) !== self::BOM_UTF_8)
						\Safe\rewind($this->source);
				}
				elseif (in_array($inputEncoding, ['UTF-16', 'UTF-16BE', 'UTF-16LE'])) {
					$start = \Safe\fread($this->source, 2);

					if ($start === self::BOM_UTF_16_BE) {
						// specify more exact charset for reading
						if ($readEncoding === 'UTF-16')
							$readEncoding = 'UTF-16BE';
					}
					elseif ($start === self::BOM_UTF_16_LE) {
						// specify more exact charset for reading
						if ($readEncoding === 'UTF-16')
							$readEncoding = 'UTF-16LE';
					}
					else {
						\Safe\rewind($this->source);
					}
				}
				elseif (in_array($inputEncoding, ['UTF-32', 'UTF-32BE', 'UTF-32LE'])) {
					$start = \Safe\fread($this->source, 4);

					if ($start === self::BOM_UTF_32_BE) {
						// specify more exact charset for reading
						if ($readEncoding === 'UTF-32')
							$readEncoding = 'UTF-32BE';
					}
					elseif ($start === self::BOM_UTF_32_LE) {
						// specify more exact charset for reading
						if ($readEncoding === 'UTF-32')
							$readEncoding = 'UTF-32LE';
					}
					else {
						\Safe\rewind($this->source);
					}
				}
			}

			if ($this->internalEncoding !== $readEncoding) {
				stream_filter_append($this->source, 'convert.iconv.' . strtolower($readEncoding) . '.' . strtolower($this->internalEncoding));
			}

			$this->firstLine = true;

			return $this;
		}

		/**
		 * Closes the currently opened source file
		 * @return $this
		 * @throws \Safe\Exceptions\FilesystemException
		 */
		public function close(): CsvReader {

			if ($this->source) {
				\Safe\fclose($this->source);

				$this->source = null;
			}

			return $this;
		}

		/**
		 * Cursor returning all lines
		 * @return Generator|string[] The generator for all lines
		 * @throws FilesystemException
		 */
		public function cursor() {
			while(($values = $this->readLine()) !== false) {
				yield $values;
			}
		}

		/**
		 * Cursor returning all data
		 * @return Generator The generator for all data lines
		 * @throws FilesystemException
		 */
		public function dataCursor() {
			while (($fields = $this->readData()) !== false) {
				yield $fields;
			}
		}

		/**
		 * Reads the next line as column headers
		 * @return CsvReader This instance
		 * @throws FilesystemException
		 */
		public function readColumns() : CsvReader {

			$this->setColumns($this->readLine() ?: []);

			return $this;
		}

		/**
		 * Reads the given data as associative array (column names as key, field value as array) from file
		 * @return array|bool The data or false on EOF. Column names as key, field value as array
		 * @throws FilesystemException
		 */
		public function readData() {

			if ($this->columns === null)
				throw new RuntimeException('Columns must be read or set before using readData()');

			// read values
			$values = $this->readLine();
			if ($values === false)
				return false;

			// build associative array with column names as key
			$ret     = $this->emptyRow; // use prepared empty row (this safes us building it each time again) to have all columns existing in returned array
			$columns = $this->columns;
			foreach ($values as $index => $value) {
				$ret[$columns[$index] ?? $index] = $value;
			}

			// cast values
			$casts = $this->casts;
			if ($casts) {
				foreach ($ret as $field => &$value) {
					$currCast = $casts[$field] ?? null;
					if ($currCast)
						$value = $this->convertValue($value, $currCast);
				}
			}


			return $ret;
		}

		/**
		 * Reads a line from the given file
		 * @return array|bool The values as array or false on EOF
		 * @throws FilesystemException
		 */
		public function readLine() {

			if (!is_resource($this->source))
				throw new RuntimeException('No source opened');

			$escape    = $this->escape;
			$enclosure = $this->enclosure;


			// parse CSV
			do {
				if ($enclosure === null) {
					$lineStr = fgets($this->source);
					if ($lineStr !== false)
						$fields = explode($this->delimiter, rtrim($lineStr, "\n\r"));
					else
						$fields = false;
				}
				else {
					$fields = fgetcsv($this->source, 0, $this->delimiter, $enclosure, $escape);
				}

				if ($fields === false) {
					// EOF or error?
					if (feof($this->source))
						return false;
					else
						throw new FilesystemException('Error reading CSV');
				}

			}
			while ($fields === [0 => null]); // skip empty lines

			// unescape escape chars (str_getcsv does this only if escape matches enclosure)
			if ($this->enclosure !== null && $this->escape != $this->enclosure) {

				$enclosureReg = preg_quote($this->enclosure, '/');
				$escape       = $this->escape;
				$escapeReg    = preg_quote($this->escape, '/');

				foreach ($fields as &$currField) {

					// unescape escaped enclosure and escape chars, when occurring within value
					$currField = preg_replace_callback("/$escapeReg($enclosureReg|$escapeReg)/", function ($matches) use ($escape) {
						return $matches[1];
					}, $currField);

				}

			}

			// convert to output encoding, if necessary
			if ($this->outputEncoding != $this->internalEncoding) {
				foreach($fields as &$currField) {
					$currField = mb_convert_encoding($currField, $this->outputEncoding, $this->internalEncoding);
				}
			}

			return $fields;
		}

	}