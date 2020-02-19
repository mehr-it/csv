<?php


	namespace MehrItCsvTest\Cases\Unit;


	use InvalidArgumentException;
	use MehrIt\Csv\CsvReader;

	class CsvReaderTest extends TestCase
	{
		public function testOpen_closedResource() {

			$res = fopen('php://memory', 'w+');
			fclose($res);

			$this->expectException(InvalidArgumentException::class);

			(new CsvReader())
				->open($res);

		}

		public function testOpen_stdClass() {

			$res = new \stdClass();

			$this->expectException(InvalidArgumentException::class);

			(new CsvReader())
				->open($res);

		}

		public function testOpen_null() {

			$res = null;

			$this->expectException(InvalidArgumentException::class);

			(new CsvReader())
				->open($res);

		}

		public function testOpen_integer() {

			$res = 2;

			$this->expectException(InvalidArgumentException::class);

			(new CsvReader())
				->open($res);

		}

		public function testReadLine_openedFromString() {

			$rdr = new CsvReader();
			$rdr->openString("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"\n5\"\n");

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', "v\"\"\n5"], $rdr->readLine());

			$rdr->close();
		}

		public function testReadLine_openedFromString_withBOM() {

			$rdr = new CsvReader();
			$rdr->openString("\xEF\xBB\xBF\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n");

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', "v\"\"5"], $rdr->readLine());

			$rdr->close();
		}

		public function testReadLine_openedFromString_withBOMDisabled() {

			$rdr = new CsvReader();
			$rdr->openString("\xEF\xBB\xBFv1,\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", false);

			$this->assertSame(["\xEF\xBB\xBFv1", 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', "v\"\"5"], $rdr->readLine());

			$rdr->close();
		}

		public function testReadLine() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"\n5\"\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', "v\"\"\n5"], $rdr->readLine());

			fclose($res);

		}

		public function testReadLine_eof() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "a,b\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame(['a', 'b'], $rdr->readLine());
			$this->assertSame(false, $rdr->readLine());

			fclose($res);

		}

		public function testReadLine_eofWithoutLinebreak() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "a,b");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame(['a', 'b'], $rdr->readLine());
			$this->assertSame(false, $rdr->readLine());

			fclose($res);

		}

		public function testReadLine_eofAfterEmptyLine() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "a,b\n\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame(['a', 'b'], $rdr->readLine());
			$this->assertSame(false, $rdr->readLine());

			fclose($res);

		}

		public function testReadLine_emptyLineInBetween() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "a,b\n\nc,d\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame(['a', 'b'], $rdr->readLine());
			$this->assertSame(['c', 'd'], $rdr->readLine());
			$this->assertSame(false, $rdr->readLine());

			fclose($res);

		}

		public function testReadLine_otherDelimiter() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\"v1\"\"\";v,2;v3;\"v 4\"\n");
			rewind($res);

			$rdr = new CsvReader();
			$this->assertSame($rdr, $rdr->setDelimiter(';'));
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4'], $rdr->readLine());

			fclose($res);

		}

		public function testReadLine_otherLinebreak() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\"v1\"\"\",\"v,2\",v3,\"v 4\"\r\nw1,w2,w3,\"w 4\"\r\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4'], $rdr->readLine());
			$this->assertSame(['w1', 'w2', 'w3', 'w 4'], $rdr->readLine());

			fclose($res);

		}


		public function testReadLine_otherEscape() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\"v1\\\"\",\"v,2\",v3,\"v 4\",v\\\"5,\"v\"6\"\n");
			rewind($res);

			$rdr = new CsvReader();
			$this->assertSame($rdr, $rdr->setEscape('\\'));
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5', 'v6"'], $rdr->readLine());

			fclose($res);

		}

		public function testReadLine_otherEnclosure() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "'v1\"\"','v,2',v3,'v 4'\n");
			rewind($res);

			$rdr = new CsvReader();
			$this->assertSame($rdr, $rdr->setEnclosure('\''));
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4'], $rdr->readLine());

			fclose($res);

		}

		public function testReadLine_withoutEnclosure() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "v1\"\",'v,2',v3,\"v 4\n a,b, c, d, e");
			rewind($res);

			$rdr = new CsvReader();
			$this->assertSame($rdr, $rdr->setEnclosure(''));
			$this->assertSame($rdr, $rdr->setEscape(''));
			$rdr->open($res);

			$this->assertSame(['v1""', '\'v', '2\'', 'v3', '"v 4'], $rdr->readLine());
			$this->assertSame([' a', 'b', ' c', ' d', ' e'], $rdr->readLine());
			$this->assertSame(false, $rdr->readLine());

			fclose($res);

		}

		public function testReadLine_inputEncoding() {

			$res = fopen('php://memory', 'w');

			fwrite($res, utf8_decode("äöü,xyz\n"));
			rewind($res);

			$rdr = new CsvReader();
			$this->assertSame($rdr, $rdr->setInputEncoding('ISO-8859-1'));
			$rdr->open($res);

			$this->assertSame(['äöü', 'xyz'], $rdr->readLine());

			fclose($res);
		}

		public function testReadLineBOM_disabled() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\xEF\xBB\xBFv1,\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res, false);

			$this->assertSame(["\xEF\xBB\xBFv1", 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $rdr->readLine());

			fclose($res);

		}

		public function testReadLineBOM_utf8() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\xEF\xBB\xBF\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $rdr->readLine());

			fclose($res);

		}

		public function testReadLineBOM_utf16_le() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\xFF\xFE" . mb_convert_encoding("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", 'UTF-16LE','UTF-8'));
			rewind($res);

			$rdr = new CsvReader();
			$rdr->setInputEncoding('UTF-16LE');
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$l = $rdr->readLine();
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $l);

			fclose($res);

		}

		public function testReadLineBOM_utf16_le_inputUtf16() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\xFF\xFE" . mb_convert_encoding("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", 'UTF-16LE', 'UTF-8'));
			rewind($res);

			$rdr = new CsvReader();
			$rdr->setInputEncoding('UTF-16');
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$l = $rdr->readLine();
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $l);

			fclose($res);

		}

		public function testReadLineBOM_utf16_be() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\xFE\xFF" . mb_convert_encoding("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", 'UTF-16BE','UTF-8'));
			rewind($res);

			$rdr = new CsvReader();
			$rdr->setInputEncoding('UTF-16BE');
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$l = $rdr->readLine();
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $l);

			fclose($res);

		}

		public function testReadLineBOM_utf16_be_inputUtf16() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\xFE\xFF" . mb_convert_encoding("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", 'UTF-16BE','UTF-8'));
			rewind($res);

			$rdr = new CsvReader();
			$rdr->setInputEncoding('UTF-16');
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$l = $rdr->readLine();
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $l);

			fclose($res);

		}

		public function testReadLineBOM_utf32_le() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\xFF\xFE\x00\x00" . mb_convert_encoding("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", 'UTF-32LE','UTF-8'));
			rewind($res);

			$rdr = new CsvReader();
			$rdr->setInputEncoding('UTF-32LE');
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$l = $rdr->readLine();
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $l);

			fclose($res);

		}

		public function testReadLineBOM_utf32_le_inputUtf32() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\xFF\xFE\x00\x00" . mb_convert_encoding("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", 'UTF-32LE', 'UTF-8'));
			rewind($res);

			$rdr = new CsvReader();
			$rdr->setInputEncoding('UTF-32');
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$l = $rdr->readLine();
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $l);

			fclose($res);

		}

		public function testReadLineBOM_utf32_be() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\x00\x00\xFE\xFF" . mb_convert_encoding("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", 'UTF-32BE','UTF-8'));
			rewind($res);

			$rdr = new CsvReader();
			$rdr->setInputEncoding('UTF-32BE');
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$l = $rdr->readLine();
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $l);

			fclose($res);

		}

		public function testReadLineBOM_utf32_be_inputUtf32() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\x00\x00\xFE\xFF" . mb_convert_encoding("\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n", 'UTF-32BE','UTF-8'));
			rewind($res);

			$rdr = new CsvReader();
			$rdr->setInputEncoding('UTF-32');
			$rdr->open($res);

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $rdr->readLine());
			$l = $rdr->readLine();
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $l);

			fclose($res);

		}

		public function testReadLine_outputEncoding() {


			$res = fopen('php://memory', 'w');

			fwrite($res, "äöü,xyz\n");
			rewind($res);

			$rdr = new CsvReader();
			$this->assertSame($rdr, $rdr->setOutputEncoding('ISO-8859-1'));
			$rdr->open($res);

			$this->assertSame([utf8_decode('äöü'), 'xyz'], $rdr->readLine());

			fclose($res);

		}


		public function testReadColumns() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\"a1\",b,c\nv1,v2,v3\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->readColumns());

			$this->assertSame(['a1', 'b', 'c'], $rdr->getColumns());

			$this->assertSame(['v1', 'v2', 'v3'], $rdr->readLine());

			fclose($res);
		}

		public function testColumnsExist_single_existing() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\"a1\",b,c\nv1,v2,v3\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->readColumns());

			$this->assertSame(true, $rdr->columnsExist('b'));

			fclose($res);
		}

		public function testColumnsExist_single_notExisting() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\"a1\",b,c\nv1,v2,v3\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->readColumns());

			$this->assertSame(false, $rdr->columnsExist('b2'));

			fclose($res);
		}

		public function testColumnsExist_multiple_existing() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\"a1\",b,c\nv1,v2,v3\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->readColumns());

			$this->assertSame(true, $rdr->columnsExist(['a1', 'b', 'c']));

			fclose($res);
		}

		public function testColumnsExist_multiple_notExisting() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\"a1\",b,c\nv1,v2,v3\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->readColumns());

			$this->assertSame(false, $rdr->columnsExist(['a1', 'b2', 'c']));

			fclose($res);
		}

		public function testReadData_columnsRead() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\"a1\",b,c\nv1,v2,v3\nv4,v5,v6\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->readColumns());

			$this->assertSame(['a1', 'b', 'c'], $rdr->getColumns());

			$this->assertSame(['a1' => 'v1', 'b' => 'v2', 'c'=> 'v3'], $rdr->readData());
			$this->assertSame(['a1' => 'v4', 'b' => 'v5', 'c'=> 'v6'], $rdr->readData());

			fclose($res);
		}

		public function testReadData_columnsSet() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "v1,v2,v3\nv4,v5,v6\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a1', 'b', 'c']));

			$this->assertSame(['a1', 'b', 'c'], $rdr->getColumns());

			$this->assertSame(['a1' => 'v1', 'b' => 'v2', 'c'=> 'v3'], $rdr->readData());
			$this->assertSame(['a1' => 'v4', 'b' => 'v5', 'c'=> 'v6'], $rdr->readData());

			fclose($res);
		}

		public function testReadData_columnMissing() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "v1,v2,v3\nv4,v5\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a1', 'b', 'c']));

			$this->assertSame(['a1', 'b', 'c'], $rdr->getColumns());

			$this->assertSame(['a1' => 'v1', 'b' => 'v2', 'c'=> 'v3'], $rdr->readData());
			$this->assertSame(['a1' => 'v4', 'b' => 'v5', 'c'=> null], $rdr->readData());

			fclose($res);
		}

		public function testReadData_tooManyColumns() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "v1,v2,v3,vv\nv4,v5,v6\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a1', 'b', 'c']));

			$this->assertSame(['a1', 'b', 'c'], $rdr->getColumns());

			$this->assertSame(['a1' => 'v1', 'b' => 'v2', 'c'=> 'v3', 3 => 'vv'], $rdr->readData());
			$this->assertSame(['a1' => 'v4', 'b' => 'v5', 'c'=> 'v6'], $rdr->readData());

			fclose($res);
		}

		public function testReadData_castDefaultNull() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\" \",1\n0,\" \"");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'defaultNull']));


			$this->assertSame(['a' => null, 'b' => '1'], $rdr->readData());
			$this->assertSame(['a' => '0', 'b' => ' '], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castDefault() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\" \",1\n0,\" \"");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'default:bbb']));


			$this->assertSame(['a' => 'bbb', 'b' => '1'], $rdr->readData());
			$this->assertSame(['a' => '0', 'b' => ' '], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castTrim() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\" 15 \",1\n0,\" 18 \"");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'trim']));


			$this->assertSame(['a' => '15', 'b' => '1'], $rdr->readData());
			$this->assertSame(['a' => '0', 'b' => ' 18 '], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castUpper() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\" ab \",1\n0,\"cd \"");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'upper']));


			$this->assertSame(['a' => ' AB ', 'b' => '1'], $rdr->readData());
			$this->assertSame(['a' => '0', 'b' => 'cd '], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castLower() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\" AB \",1\n0,\"CD \"");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'lower']));


			$this->assertSame(['a' => ' ab ', 'b' => '1'], $rdr->readData());
			$this->assertSame(['a' => '0', 'b' => 'CD '], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castBool() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\" false \",1\n\" \",\"true\"\nFALSE,5\n0,6\n1,8\n,10");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'bool']));


			$this->assertSame(['a' => false, 'b' => '1'], $rdr->readData());
			$this->assertSame(['a' => false, 'b' => 'true'], $rdr->readData());
			$this->assertSame(['a' => false, 'b' => '5'], $rdr->readData());
			$this->assertSame(['a' => false, 'b' => '6'], $rdr->readData());
			$this->assertSame(['a' => true, 'b' => '8'], $rdr->readData());
			$this->assertSame(['a' => false, 'b' => '10'], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castNumber() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\" 45.8 \",ab\ncd,\"EF \"\n12,b\n-1.789,c\n.0,d");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'number']));


			$this->assertSame(['a' => '45.8', 'b' => 'ab'], $rdr->readData());
			$this->assertSame(['a' => null, 'b' => 'EF '], $rdr->readData());
			$this->assertSame(['a' => '12', 'b' => 'b'], $rdr->readData());
			$this->assertSame(['a' => '-1.789', 'b' => 'c'], $rdr->readData());
			$this->assertSame(['a' => '.0', 'b' => 'd'], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castNumber_otherSeparator() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\" 45,8 \";ab\ncd;\"EF \"\n12;b\n-1,789;c\n,0;d");
			rewind($res);

			$rdr = new CsvReader();
			$this->assertSame($rdr, $rdr->setDelimiter(';'));
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setDefaultDecimalSeparator(','));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'number']));


			$this->assertSame(['a' => '45.8', 'b' => 'ab'], $rdr->readData());
			$this->assertSame(['a' => null, 'b' => 'EF '], $rdr->readData());
			$this->assertSame(['a' => '12', 'b' => 'b'], $rdr->readData());
			$this->assertSame(['a' => '-1.789', 'b' => 'c'], $rdr->readData());
			$this->assertSame(['a' => '.0', 'b' => 'd'], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castInt() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\" 45 \",ab\ncd,\"EF \"\n12,b\n-1.789,c\n.0,d\n-4,e");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'int']));


			$this->assertSame(['a' => '45', 'b' => 'ab'], $rdr->readData());
			$this->assertSame(['a' => null, 'b' => 'EF '], $rdr->readData());
			$this->assertSame(['a' => '12', 'b' => 'b'], $rdr->readData());
			$this->assertSame(['a' => null, 'b' => 'c'], $rdr->readData());
			$this->assertSame(['a' => null, 'b' => 'd'], $rdr->readData());
			$this->assertSame(['a' => '-4', 'b' => 'e'], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castDate() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "\"2019-08-24 11:44:01\",b\n,c\nxasdas,d\n\"2019-08-24\",e");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'date']));


			$this->assertEquals(['a' => new \DateTime('2019-08-24 11:44:01'), 'b' => 'b'], $rdr->readData());
			$this->assertEquals(['a' => null, 'b' => 'c'], $rdr->readData());
			$this->assertEquals(['a' => null, 'b' => 'd'], $rdr->readData());
			$this->assertEquals(['a' => new \DateTime('2019-08-24 00:00:00'), 'b' => 'e'], $rdr->readData());



			fclose($res);
		}

		public function testReadData_castSplit() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "1|2|3|X,b\n|,c\n1,d\n,e");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'split']));


			$this->assertSame(['a' => ['1', '2', '3', 'X'], 'b' => 'b'], $rdr->readData());
			$this->assertSame(['a' => ['', ''], 'b' => 'c'], $rdr->readData());
			$this->assertSame(['a' => ['1'], 'b' => 'd'], $rdr->readData());
			$this->assertSame(['a' => null, 'b' => 'e'], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castSplit_otherDelimiter() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "1;2;3;X,b\n;,c\n1,d\n,e");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'split:;']));


			$this->assertSame(['a' => ['1', '2', '3', 'X'], 'b' => 'b'], $rdr->readData());
			$this->assertSame(['a' => ['', ''], 'b' => 'c'], $rdr->readData());
			$this->assertSame(['a' => ['1'], 'b' => 'd'], $rdr->readData());
			$this->assertSame(['a' => null, 'b' => 'e'], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castJson() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "null,b\n\"\"\"str\"\"\",c\n\"[5,8]\",d\n\"{\"\"b\"\":9}\",e\n{,f");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'json']));


			$this->assertSame(['a' => null, 'b' => 'b'], $rdr->readData());
			$this->assertSame(['a' => 'str', 'b' => 'c'], $rdr->readData());
			$this->assertSame(['a' => [5,8], 'b' => 'd'], $rdr->readData());
			$this->assertSame(['a' => ['b' => 9], 'b' => 'e'], $rdr->readData());
			$this->assertSame(['a' => null, 'b' => 'f'], $rdr->readData());


			fclose($res);
		}

		public function testReadData_castJsonObject() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "null,b\n\"\"\"str\"\"\",c\n\"[5,8]\",d\n\"{\"\"b\"\":9}\",e\n{,f");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a', 'b']));
			$this->assertSame($rdr, $rdr->setCasts(['a' => 'json:object']));

			$obj = new \stdClass();
			$obj->b = 9;

			$this->assertSame(['a' => null, 'b' => 'b'], $rdr->readData());
			$this->assertSame(['a' => 'str', 'b' => 'c'], $rdr->readData());
			$this->assertSame(['a' => [5,8], 'b' => 'd'], $rdr->readData());
			$this->assertEquals(['a' => $obj, 'b' => 'e'], $rdr->readData());
			$this->assertSame(['a' => null, 'b' => 'f'], $rdr->readData());


			fclose($res);
		}

		public function testCursor() {

			$res = fopen('php://memory', 'w');

			fwrite($res, "\"v1\"\"\",\"v,2\",v3,\"v 4\",\"v\"\"5\"\nw1,w2,w3,\"w 4\",\"v\"\"\"\"5\"\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$ret = [];

			foreach($rdr->cursor() as $row) {
				$ret[] = $row;
			}

			$this->assertSame(['v1"', 'v,2', 'v3', 'v 4', 'v"5'], $ret[0]);
			$this->assertSame(['w1', 'w2', 'w3', 'w 4', 'v""5'], $ret[1]);

			fclose($res);

		}

		public function testDataCursor() {
			$res = fopen('php://memory', 'w');

			fwrite($res, "v1,v2,v3\nv4,v5,v6\n");
			rewind($res);

			$rdr = new CsvReader();
			$rdr->open($res);

			$this->assertSame($rdr, $rdr->setColumns(['a1', 'b', 'c']));

			$ret = [];

			foreach ($rdr->dataCursor() as $row) {
				$ret[] = $row;
			}

			$this->assertSame(['a1' => 'v1', 'b' => 'v2', 'c' => 'v3'], $ret[0]);
			$this->assertSame(['a1' => 'v4', 'b' => 'v5', 'c' => 'v6'], $ret[1]);

			fclose($res);
		}

	}