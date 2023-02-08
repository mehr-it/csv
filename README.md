# Advanced CSV reader and writer for PHP
This library aims to offer advanced support for CSV in PHP. This includes charset conversions,
proper handling of escape sequences and byte order marks (for UTF-8, UTF-16 and UTF-32). 

The CSV writer also supports "always quoting" and custom line-breaks (EOL).

## Writer usage

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
		
		
		
## Reader usage

    $reader = new CsvReader();
    $reader
        ->setDelimiter(';')
        ->setEnclosure("'")
        ->setEscape('\\')
        ->open($resource);
        
    while (($line = $reader->readLine()) !== false) {
        var_dump($line);
    }
    
    $reader->close();    
    
    
To receive associative arrays with column keys, columns can be read from first line and use the `readData()`
method:

    $reader = new CsvReader();
    $row = $reader
        ->open($resource)
        ->readColumns()
        ->readData();
        
If columns are not present in file, they can be set manually:

    $reader = new CsvReader();
    $row = $reader
        ->open($resource)
        ->setColumns(['a', 'b'])
        ->readData();
    
    
### Casting values
Following example demonstrates the usage of casts, for easy reading data types:

     $reader = new CsvReader();
     $row = $reader
         ->open($resource)
         ->setColumns(['a', 'b', 'c', 'd', 'e'])
         ->setCasts(
            'a' => 'number',
            'b' => 'json',
            'c' => 'split:|'
            'd' => function($v) {
                return 'x-' . $v;
            },
            'e' => 'number:,:.',
         );
         ->readData();
         
If a value cannot be casted, `null` will be returned for the field.