<?php

namespace Fuzz\S3ForImages\Traits;

use Symfony\Component\HttpFoundation\File\File;

trait HasImageAttributes
{
	/**
	 * Validate that this file is valid and is an image.
	 *
	 * @param \Symfony\Component\HttpFoundation\File\File $file
	 *
	 * @return bool
	 *
	 * @throws \InvalidArgumentException
	 */
	public function validateIsImage($file)
	{
		if (is_string($file) && $this->isBase64Encoded($file)) {
			$file = $this->validateIsBase64Image($file);
		} else {
			$this->validateIsFile($file);

			if (! (strpos($file->getMimeType(), 'image/') === 0)) {
				throw new \InvalidArgumentException('The uploaded file is not an image');
			}
		}

		return $file;
	}

	/**
	 * Validate that this is a valid file
	 *
	 * @param \Symfony\Component\HttpFoundation\File\File $file
	 *
	 * @throws \InvalidArgumentException
	 */
	public function validateIsFile($file)
	{
		if (! is_a($file, File::class)) {
			throw new \InvalidArgumentException('Image attribute is not a file.');
		}

		if (! $file->getSize()) {
			throw new \InvalidArgumentException('The file could not be processed.', ['Possible issues' => ['The file is too large.', 'The file was not found.']]);
		}
	}

	/**
	 * @param $string
	 *
	 * @return bool
	 */
	public function isBase64Encoded($string)
	{
		return (base64_decode($string, true) !== false && base64_encode(base64_decode($string)) === $string);
	}

	/**
	 * @param $encoded
	 *
	 * @return \Symfony\Component\HttpFoundation\File\File
	 *
	 * @throws \InvalidArgumentException
	 */
	public function validateIsBase64Image($encoded)
	{
		$fileInfo = finfo_open();

		$mime_type = finfo_buffer($fileInfo, base64_decode($encoded), FILEINFO_MIME_TYPE);

		if (! (strpos($mime_type, 'image/') === 0)) {
			throw new \InvalidArgumentException('The uploaded file is not an image');
		}

		$tempFile = tempnam(sys_get_temp_dir(), 'fuzz-file-');

		file_put_contents($tempFile, base64_decode($encoded));

		return new File($tempFile);
	}

	/**
	 * Return a URL to the image resizer
	 *
	 * @return string
	 */
	public function getResizerUrlAttribute()
	{
		$resizer_base_url = config('assets.resizer_url');

		$options = [
			'source' => $this->image,
			'height' => '{height}',
			'width' => '{width}',
		];

		$query = urldecode(http_build_query($options));

		return "$resizer_base_url/?$query";
	}
}
