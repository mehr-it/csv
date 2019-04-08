<?php


	namespace MehrIt\Csv;


	use php_user_filter;

	class CsvWriteStreamFilter extends php_user_filter
	{
		const FILTER_NAME = 'mehr-it.csv_write_filter';

		const DEFAULT_LINEBREAK = "\n";

		public static function append($stream, $linebreak = "\n", $inputEncoding = null, $outputEncoding = null) {

			$inputEncoding = $inputEncoding ?: mb_internal_encoding();

			$ret = stream_filter_append($stream, self::FILTER_NAME, STREAM_FILTER_WRITE, [
				'linebreak'      => $linebreak,
				'inputEncoding'  => $inputEncoding,
				'outputEncoding' => $outputEncoding,
			]);

			if ($ret === false)
				throw new \RuntimeException('Failed to attach ' . CsvWriteStreamFilter::class . ' to CSV stream');

			return $ret;
		}

		public static function remove($filter) {
			if (!stream_filter_remove($filter))
				throw new \RuntimeException('Failed to remove ' . CsvWriteStreamFilter::class . ' from CSV stream');
		}

		public function filter($in, $out, &$consumed, $closing) {
			while ($bucket = stream_bucket_make_writeable($in)) {

				$search = [chr(0)];
				$replace = [''];

				// replace linebreak
				if (($eol = $this->params['linebreak']) !== self::DEFAULT_LINEBREAK) {
					$search[] = self::DEFAULT_LINEBREAK;
					$replace[] = $eol;
				}

				$bucket->data = str_replace($search, $replace, $bucket->data);


				// convert encoding
				$inputEnc = $this->params['inputEncoding'];
				$outputEnc = $this->params['outputEncoding'];
				if ($inputEnc !== $outputEnc)
					$bucket->data = mb_convert_encoding($bucket->data, $outputEnc, $inputEnc);


				$consumed     += $bucket->datalen;
				stream_bucket_append($out, $bucket);
			}

			return PSFS_PASS_ON;
		}
	}

	// register the stream filter
	stream_filter_register(CsvWriteStreamFilter::FILTER_NAME, CsvWriteStreamFilter::class);

