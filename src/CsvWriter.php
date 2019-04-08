<?php


	namespace MehrIt\Csv;


	use InvalidArgumentException;
	use RuntimeException;

	class CsvWriter
	{


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


		protected $inputEncoding = 'UTF-8';

		protected $outputEncoding = 'UTF-8';

		/**
		 * @var string[]|bool
		 */
		protected $columns = false;

		/**
		 * Creates a new instance
		 */
		public function __construct() {
			$this->inputEncoding = mb_internal_encoding();
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
		 * @return string The enclosure char
		 */
		public function getEnclosure(): string {
			return $this->enclosure;
		}

		/**
		 * Sets the enclosure char
		 * @param string $enclosure The enclosure char
		 * @return $this
		 */
		public function setEnclosure(string $enclosure): CsvWriter {

			if ($this->target)
				throw new RuntimeException('Enclosure must be set before opening CSV');

			$this->enclosure = $enclosure;

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

			$this->escape = $escape;

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
		 */
		public function open($target) : CsvWriter {

			if (!is_resource($target)) {
				$target = fopen($target, 'w');

				if (!$target)
					throw new RuntimeException("Could not open target \"$target\"");
			}

			$this->target = $target;

			$this->filter = CsvWriteStreamFilter::append($target, $this->linebreak, $this->inputEncoding, $this->outputEncoding);

			return $this;
		}

		/**
		 * Detaches the CSV writer from given stream
		 * @return $this
		 */
		public function detach() : CsvWriter {

			if ($this->filter) {
				CsvWriteStreamFilter::remove($this->filter);
				$this->filter = null;
			}

			$this->target = null;

			return $this;
		}

		/**
		 * Closes the currently opened target file
		 * @return $this
		 */
		public function close() : CsvWriter {

			if ($this->target) {
				if (!fclose($this->target))
					throw new RuntimeException('Could not close target file');

				$this->target = null;
			}

			return $this;
		}

		/**
		 * Sets the CSV columns. The column key must be passed as array key and is expected for writeData(). The column header must be passed as array value
		 * @param array $columns The columns. Header as value. Column key as key
		 * @param bool $output True if to output the column headers
		 * @return $this
		 */
		public function columns(array $columns, $output = true) : CsvWriter {

			$this->columns = $columns;

			// output column headers
			if ($output)
				$this->writeLine($columns);

			return $this;
		}


		/**
		 * Writes the given data to the CSV. Array keys must be a subset of the column keys passed to columns()
		 * @param array $columnValues The column values
		 * @return $this
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
		 */
		public function writeLine(array $fields) : CsvWriter {

			if (!is_resource($this->target))
				throw new RuntimeException('No target opened');

			$enclosure   = $this->enclosure;
			$escape      = $this->escape;
			$alwaysQuote = $this->alwaysQuote;


			// prepare field values
			$fields = array_map(function($value) use ($enclosure, $escape, $alwaysQuote) {

				$value = str_replace($enclosure, "$escape$enclosure", $value);

				if ($alwaysQuote)
					$value = "$enclosure$value$enclosure";

				return $value;
			}, $fields);


			if (!fputcsv($this->target, $fields, $this->delimiter, $alwaysQuote ? chr(0) : $enclosure , $escape))
				throw new RuntimeException("Could not write to target CSV file");

			return $this;
		}

	}