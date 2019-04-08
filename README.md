# Advanced CSV
CSV writer supporting automatic encoding, always-quoting and custom line-breaks (EOL)


## Usage

Following example is quite self-explaining:

	$writer = new CsvWriter();
	$writer
		->setDelimiter(';')
		->setEnclosure("'")
		->setEscape('\\')
		->setAlwaysQuote(true)
		->open($resource)
		->writeLine(['a', 'b', 'c'])
		->close();
		
You may also pass your data as associative array with column keys if you define columns first.
This allows to omit empty columns:

	$writer = new CsvWriter();
	$writer
		->open($resource)
		->columns(['a' => 'Col A', 'b' => 'Col B', 'c' => 'Col C'])
		->writeData(['a' => 15, 'b' => 16, 'c' => 17])
		->writeData(['a' => 15, 'b' => 16])
		->close();