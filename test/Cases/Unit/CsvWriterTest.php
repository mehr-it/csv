<?php


	namespace MehrItCsvTest\Cases\Unit;


	use InvalidArgumentException;
	use MehrIt\Csv\CsvWriter;

	class CsvWriterTest extends TestCase
	{

		public function testWriteLine() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();
			$wrt->open($res);


			$wrt->writeLine(['v1"', 'v,2', 'v3', 'v 4', 'v"5']);
			$wrt->writeLine(['w1', 'w2', 'w3', 'w 4', 'v""5']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", $ret);

		}

		public function testWriteLine_otherDelimiter() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setDelimiter(";"));


			$wrt->open($res);


			$wrt->writeLine(['v1"', 'v,2', 'v3', 'v 4']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\"v1\"\"\";v,2;v3;\"v 4\"\n", $ret);

		}

		public function testWriteLine_otherLinebreak() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setLinebreak("\r\n"));


			$wrt->open($res);


			$wrt->writeLine(['v1"', 'v,2', 'v3', 'v 4']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\"v1\"\"\",\"v,2\",v3,\"v 4\"\r\n", $ret);

		}

		public function testWriteLine_otherEscape() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setEscape("\\"));


			$wrt->open($res);


			$wrt->writeLine(['v1"', 'v,2', 'v3', 'v 4', 'v""5']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\"v1\\\"\",\"v,2\",v3,\"v 4\",\"v\\\"\\\"5\"\n", $ret);

		}

		public function testWriteLine_otherEnclosure() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setEnclosure("'"));


			$wrt->open($res);


			$wrt->writeLine(['v1"', 'v,2', 'v3', 'v 4']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("'v1\"\"','v,2',v3,'v 4'\n", $ret);

		}

		public function testWriteLine_alwaysQuote() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setAlwaysQuote(true));


			$wrt->open($res);


			$wrt->writeLine(['v1"', 'v,2', 'v3', 'v 4']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\"v1\"\"\",\"v,2\",\"v3\",\"v 4\"\n", $ret);

		}

		public function testWriteLine_inputEncoding() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setInputEncoding('ISO-8859-1'));


			$wrt->open($res);


			$wrt->writeLine([utf8_decode('äöü'), 'xyz']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("äöü,xyz\n", $ret);

		}

		public function testWriteLine_outputEncoding() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setOutputEncoding('ISO-8859-1'));


			$wrt->open($res);


			$wrt->writeLine(['äöü', 'xyz']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame(utf8_decode("äöü,xyz\n"), $ret);

		}

		public function testWriteByteOrderMark_utf8() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setOutputEncoding('UTF-8'));


			$wrt->open($res);


			$this->assertSame($wrt, $wrt->writeByteOrderMark());
			$wrt->writeLine(['äöü', 'xyz']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\xEF\xBB\xBF" . "äöü,xyz\n", $ret);

		}

		public function testWriteByteOrderMark_utf16le() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setOutputEncoding('UTF-16LE'));


			$wrt->open($res);


			$this->assertSame($wrt, $wrt->writeByteOrderMark());
			$wrt->writeLine(['äöü', 'xyz']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\xFF\xFE" . mb_convert_encoding("äöü,xyz\n", 'UTF-16LE', 'UTF-8' ), $ret);

		}

		public function testWriteByteOrderMark_utf16be() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setOutputEncoding('UTF-16BE'));


			$wrt->open($res);


			$this->assertSame($wrt, $wrt->writeByteOrderMark());
			$wrt->writeLine(['äöü', 'xyz']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\xFE\xFF" . mb_convert_encoding("äöü,xyz\n", 'UTF-16BE', 'UTF-8' ), $ret);

		}

		public function testWriteByteOrderMark_utf32le() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setOutputEncoding('UTF-32LE'));


			$wrt->open($res);


			$this->assertSame($wrt, $wrt->writeByteOrderMark());
			$wrt->writeLine(['äöü', 'xyz']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\xFF\xFE\x00\x00" . mb_convert_encoding("äöü,xyz\n", 'UTF-32LE', 'UTF-8'), $ret);

		}

		public function testWriteByteOrderMark_utf32be() {

			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();

			$this->assertSame($wrt, $wrt->setOutputEncoding('UTF-32BE'));


			$wrt->open($res);


			$this->assertSame($wrt, $wrt->writeByteOrderMark());
			$wrt->writeLine(['äöü', 'xyz']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("\x00\x00\xFE\xFF" . mb_convert_encoding("äöü,xyz\n", 'UTF-32BE', 'UTF-8'), $ret);

		}


		public function testColumns_write() {
			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();
			$wrt->open($res);

			$this->assertSame($wrt, $wrt->columns(['a' => 'Col1', 'b' => 'Col2'], true));


			$wrt->writeLine(['2', '3']);

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("Col1,Col2\n2,3\n", $ret);
		}

		public function testWriteData() {
			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();
			$wrt->open($res);

			$wrt->columns(['a' => 'Col1', 'b' => 'Col2'], true);


			$this->assertSame($wrt, $wrt->writeData([
				'a' => 2,
				'b' => 3,
			]));

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("Col1,Col2\n2,3\n", $ret);
		}

		public function testWriteData_withoutColumnHeaders() {
			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();
			$wrt->open($res);

			$wrt->columns(['a' => 'Col1', 'b' => 'Col2'], false);


			$this->assertSame($wrt, $wrt->writeData([
				'a' => 2,
				'b' => 3,
			]));

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("2,3\n", $ret);
		}

		public function testWriteData_notAllFieldsFilled() {
			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();
			$wrt->open($res);

			$wrt->columns(['a' => 'Col1', 'b' => 'Col2', 'c' => 'Col3'], false);


			$this->assertSame($wrt, $wrt->writeData([
				'a' => 12,
				'b' => 14,
			]));
			$this->assertSame($wrt, $wrt->writeData([
				'a' => 22,
				'c' => 26,
			]));

			$wrt->detach();

			fseek($res, 0);
			$ret = stream_get_contents($res);

			$this->assertSame("12,14,\n22,,26\n", $ret);
		}

		public function testWriteData_unknownColumns() {
			$res = fopen('php://memory', 'w');

			$wrt = new CsvWriter();
			$wrt->open($res);

			$this->assertSame($wrt, $wrt->columns(['a' => 'Col1', 'b' => 'Col2', 'c' => 'Col3'], false));

			$this->expectException(InvalidArgumentException::class);

			$wrt->writeData([
				'a' => 12,
				'b' => 14,
				'colz' => 19
			]);

			$writer = new CsvWriter();
			$writer
				->setDelimiter(';')
				->setEnclosure("'")
				->setEscape('\\')
				->setAlwaysQuote(true)
				->open($resource)
				->writeLine(['a', 'b', 'c'])
				->close();


		}

	}