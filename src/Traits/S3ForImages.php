<?php

namespace Fuzz\S3ForImages\Traits;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeExtensionGuesser;

/**
 * Use the S3ForImages trait when needing to deal with image uploading to S3.
 *
 * @package Tapwiser\Traits
 */
trait S3ForImages
{
	/**
	 * Checks if the base64 encoding is a valid image.
	 *
	 * @param $encoding
	 *
	 * @return bool
	 */
	public function isBase64Image($encoding)
	{
		$fileInfo = finfo_open();

		$mime_type = finfo_buffer($fileInfo, base64_decode($encoding), FILEINFO_MIME_TYPE);

		finfo_close($fileInfo);

		return (strpos($mime_type, 'image/') === 0);
	}

	/**
	 * Guesses the file extension from a stream.
	 *
	 * @param string $stream
	 *
	 * @return null|string
	 */
	public function guessFileExtensionFromStream($stream)
	{
		return (new MimeTypeExtensionGuesser())->guess($this->getMimeTypeFromStream($stream));
	}

	/**
	 * Get's the MIME type from a stream.
	 *
	 * @param string $stream
	 *
	 * @return string
	 */
	public function getMimeTypeFromStream($stream)
	{
		$fileInfo = finfo_open();

		$mime_type = finfo_buffer($fileInfo, $stream, FILEINFO_MIME_TYPE);

		finfo_close($fileInfo);

		return $mime_type;
	}

	/**
	 * Checks if the value is a file.
	 *
	 * @param mixed $file
	 *
	 * @return bool
	 */
	public function isFile($file)
	{
		return ($file instanceof File);
	}

	/**
	 * Checks if the file is an image by checking the MIME type.
	 *
	 * @param \Symfony\Component\HttpFoundation\File\File $file
	 *
	 * @return bool
	 */
	public function isImageFile(File $file)
	{
		return in_array(
			$file->guessExtension(), [
				'jpeg',
				'png',
				'gif',
				'bmp',
				'svg',
			]
		);
	}

	/**
	 * Generates a random key. Helpful when you want to ensure unique names in the filesystem.
	 *
	 * @param null|string $prepend   - a string that will be prepended to the generated key.
	 *                               Useful for when you need to set a path before the key.
	 *                               Example: images/
	 *
	 * @return string
	 */
	public function generateRandKey($prepend = null, $length = 20)
	{
		$key = str_random($length);

		return "{$prepend}$key";
	}

	/**
	 * Returns the key to set visibility to public.
	 *
	 * @return string
	 */
	protected function visibilityPublic()
	{
		return 'public';
	}

	/**
	 * Returns the key to set visibility to private.
	 *
	 * @return string
	 */
	protected function visibilityPrivate()
	{
		return 'private';
	}

	/**
	 * Decides how to send the image value depending on if it's a base64 encoding or a file.
	 *
	 * @param string                                             $key        - The key is where the file will be saved
	 *                                                                       within an s3 bucket.
	 * @param string|\Symfony\Component\HttpFoundation\File\File $image      - The image file in either base64
	 *                                                                       encoded, or File object.
	 * @param string                                             $visibility - Sets the visibility of the image. Can be
	 *                                                                       set to either 'public' or 'private'
	 *
	 * @return string - The url location for the image.
	 */
	public function pushImageToS3($key, $image, $visibility)
	{
		if (is_string($image)) {
			return $this->pushBase64ImageToS3($key, $image, $visibility);
		}

		return $this->pushFileToS3($key, $image, $visibility);
	}

	/**
	 * Checks the file is a valid image, and sends to S3.
	 *
	 * @param string                                             $key        - The key is where the file will be saved
	 *                                                                       within an s3 bucket.
	 * @param string|\Symfony\Component\HttpFoundation\File\File $image      - The image file in either base64
	 *                                                                       encoding, or File object.
	 * @param string                                             $visibility - Sets the visibility of the image. Can be
	 *                                                                       set to either 'public' or 'private'
	 *
	 * @throws \InvalidArgumentException
	 *
	 * @return string - The url location for the image.
	 */
	public function pushFileToS3($key, $image, $visibility)
	{
		// Validate $image is a file and that the file is a valid Image.
		if (! $this->isFile($image) || ! $this->isImageFile($image)) {
			throw new \InvalidArgumentException('The file could not be processed. It is not a valid image.');
		}

		$stream = file_get_contents($image);

		return $this->sendStreamToS3($key, $stream, $visibility);
	}

	/**
	 * Checks the base64 encoding is a valid image, and sends to S3.
	 *
	 * @param string                                             $key          - The key is where the file will be
	 *                                                                         saved within an s3 bucket.
	 * @param string|\Symfony\Component\HttpFoundation\File\File $image        - The image file in either base64
	 *                                                                         encoding, or File object.
	 * @param string                                             $visibility   - Sets the visibility of the image. Can
	 *                                                                         be set to either 'public' or 'private'
	 *
	 * @throws \InvalidArgumentException
	 *
	 * @return string - The url location for the image.
	 */
	public function pushBase64ImageToS3($key, $image, $visibility)
	{
		// Validate base64 is an actual image.
		if (! $this->isBase64Image($image)) {
			throw new \InvalidArgumentException('The base64 encoded string is not a valid image.');
		}

		$stream = base64_decode($image);

		return $this->sendStreamToS3($key, $stream, $visibility);
	}

	/**
	 * Sends the image file to S3. It will also append the key with a proper file extension.
	 *
	 * @param string $key        - The key is where the file will be saved within an s3 bucket.
	 * @param string $stream     - The image file in either base64 encoding, or File object.
	 * @param string $visibility - Sets the visibility of the image. Can be set to either 'public' or 'private'.
	 *
	 * @return string - The url location for the image.
	 */
	public function sendStreamToS3($key, $stream, $visibility)
	{
		// Append the $key with a file extension
		$key = "{$key}.{$this->guessFileExtensionFromStream($stream)}";

		Storage::disk('s3')->put($key, $stream, $visibility);

		return $this->getImageUrl($key);
	}

	/**
	 * Returns the full image url for a $key within the $bucket. If a cdn domain is
	 * set in config or passed into the method it will replace the default s3 domain.
	 *
	 * @param string        $key     - The identifier for the file.
	 * @param null | string $cdnBase - The cdn base domain. It will.
	 * @param null | string $bucket  - The bucket where to look for the key.
	 *
	 * @return string - The string will be a full URL.
	 */
	public function getImageUrl($key, $cdnBase = null, $bucket = null)
	{
		$url        = $this->getRawImageUrl($key, $bucket);
		$defaultCdn = config('services.default_cdn');
		$cdnBase    = $cdnBase ?: config("services.cdns.{$defaultCdn}.domain");

		$path     = parse_url($url, PHP_URL_PATH);
		$query    = parse_url($url, PHP_URL_QUERY);
		$fragment = parse_url($url, PHP_URL_FRAGMENT);

		return ($cdnBase) ? $cdnBase . $path . $query . $fragment : $url;
	}

	/**
	 * Returns the raw s3 URL for the object without any modifications.
	 *
	 * @param string        $key    - The identifier for the file.
	 * @param null | string $bucket - The bucket where to look for the key.
	 *
	 * @return string - The string will be a full URL.
	 */
	public function getRawImageUrl($key, $bucket = null)
	{
		$bucket = $bucket ?: config('filesystems.disks.s3.bucket'); // @TODO test this works as expected.

		return Storage::disk('s3')->getAdapter()->getClient()->getObjectUrl($bucket, $key);
	}
}
