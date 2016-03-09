<?php

namespace Fuzz\S3ForImages\Tests;

use Aws\S3\S3Client;
use Fuzz\S3ForImages\Traits\S3ForImages;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AdapterInterface;
use Mockery;
use Symfony\Component\HttpFoundation\File\File;

class S3ForImagesTest extends TestCase
{
	protected function getEnvironmentSetUp($app)
	{
		parent::getEnvironmentSetUp($app);

		$app['config']->set('services.default_cdn', 'default_cdn1');
		$app['config']->set(
			'services.cdns', [
				'default_cdn1' => [
					'domain' => 'http://media.default_cdn1.com',
				],
			]
		);
		$app['config']->set(
			'filesystems.disks.s3', [
				'driver' => 's3',
				'key'    => 'aws_key',
				'secret' => 'aws_secret',
				'bucket' => 'aws_bucket',
				'region' => 'aws_region',
			]
		);
	}

	public function relTestPath($path)
	{
		return __DIR__ . '/' . $path;
	}

	public function getTestFileStream($path)
	{
		return file_get_contents($this->relTestPath($path));
	}

	public function testItCanCorrectlyDetermineB64Image()
	{
		$user           = new User;
		$garbage_string = 'garbage';
		$image_file     = $this->getTestFileStream('tools/fuzzpro.png');
		$b64_image      = base64_encode($this->getTestFileStream('tools/fuzzpro.png'));

		$this->assertFalse($user->isBase64Image($garbage_string));
		$this->assertFalse($user->isBase64Image($image_file));
		$this->assertTrue($user->isBase64Image($b64_image));
	}

	public function testItCanGuessFileTypeFromStream()
	{
		$user       = new User;
		$image_file = $this->getTestFileStream('tools/fuzzpro.png');

		$one = $user->guessFileExtensionFromStream($image_file);
		$two = $user->guessFileExtensionFromStream(__FILE__);

		$this->assertEquals('png', $user->guessFileExtensionFromStream($image_file));
		$this->assertEquals('txt', $user->guessFileExtensionFromStream(__FILE__));
	}

	public function testItCanGetMimeTypeFromString()
	{
		$user       = new User;
		$image_file = $this->getTestFileStream('tools/fuzzpro.png');

		$one = $user->guessFileExtensionFromStream($image_file);
		$two = $user->guessFileExtensionFromStream(__FILE__);

		$this->assertEquals('image/png', $user->getMimeTypeFromStream($image_file));
		$this->assertEquals('text/plain', $user->getMimeTypeFromStream(__FILE__));
	}

	public function testItCanDetermineIsFile()
	{
		$user           = new User;
		$garbage_string = 'garbage';
		$image_file     = new File($this->relTestPath('tools/fuzzpro.png'));
		$b64_image      = base64_encode($this->getTestFileStream('tools/fuzzpro.png'));

		$this->assertFalse($user->isFile($garbage_string));
		$this->assertFalse($user->isFile($b64_image));
		$this->assertTrue($user->isFile($image_file));
	}

	public function testItCanDetermineIfFileIsImage()
	{
		$user       = new User;
		$image_file = new File($this->relTestPath('tools/fuzzpro.png'));
		$text_file  = new File(__FILE__);

		$this->assertFalse($user->isImageFile($text_file));
		$this->assertTrue($user->isImageFile($image_file));
	}

	public function testItCanGenerateRandomKeyForFile()
	{
		$user = new User;

		// @todo how to test random string?
		$key = $user->generateRandKey('images/profile_pictures');
		$this->assertTrue(is_string($key));
		$this->assertTrue(strpos($key, 'images/profile_pictures') !== false);
	}

	public function testItReturnsCorrectAWSVisinilities()
	{
		$user = new User;

		$this->assertEquals('public', $user->getVisibilityPublic());
		$this->assertEquals('private', $user->getVisibilityPrivate());
	}

	public function testItCanGetRawImageURL()
	{
		$user = new User;

		$file = $this->getTestFileStream('tools/fuzzpro.png');

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', $file, 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('aws_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->once()->with('s3')->andReturn($filesystem_mock);

		$url = $user->getRawImageUrl('file_key.png', 'aws_bucket');

		$this->assertEquals('http://s3.com/file_key.png', $url);
	}

	public function testItCanGetRawImageURLFromCustomBucket()
	{
		$user = new User;

		$file = $this->getTestFileStream('tools/fuzzpro.png');

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', $file, 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('not_config_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->once()->with('s3')->andReturn($filesystem_mock);

		$url = $user->getRawImageUrl('file_key.png', 'not_config_bucket');

		$this->assertEquals('http://s3.com/file_key.png', $url);
	}

	public function testItCanGetImageURL()
	{
		$user = new User;

		$file = $this->getTestFileStream('tools/fuzzpro.png');

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', $file, 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('aws_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->once()->with('s3')->andReturn($filesystem_mock);

		$url = $user->getImageUrl('file_key.png');

		// should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}

	public function testItCanGetImageURLWithNoCDN()
	{
		$user = new User;

		$file = $this->getTestFileStream('tools/fuzzpro.png');

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', $file, 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('aws_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->once()->with('s3')->andReturn($filesystem_mock);

		config(['services.default_cdn' => 'doesnt_exist']);

		$url = $user->getImageUrl('file_key.png');

		// should return with default cdn url
		$this->assertEquals('http://s3.com/file_key.png', $url);
	}

	public function testItCanGetImageURLWithCustomCDN()
	{
		$user = new User;

		$file = $this->getTestFileStream('tools/fuzzpro.png');

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', $file, 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('aws_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->once()->with('s3')->andReturn($filesystem_mock);

		$url = $user->getImageUrl('file_key.png', 'http://customcdn.com');

		// should return with default cdn url
		$this->assertEquals('http://customcdn.com/file_key.png', $url);
	}

	public function testItCanGetImageURLWithCustomBucket()
	{
		$user = new User;

		$file = $this->getTestFileStream('tools/fuzzpro.png');

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', $file, 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('custom_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->once()->with('s3')->andReturn($filesystem_mock);

		$url = $user->getImageUrl('file_key.png', null, 'custom_bucket');

		// should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}

	public function testItCanSendB64ImageToS3()
	{
		$user = new User;

		$file       = $this->getTestFileStream('tools/fuzzpro.png');
		$b64_string = base64_encode($file);

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', $file, 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('aws_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->once()->with('s3')->andReturn($filesystem_mock);

		$url = $user->pushBase64ImageToS3('file_key', $b64_string, 'public');

		// should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}

	public function testItThrowsExceptionOnInvalidB64String()
	{
		$user = new User;

		$this->setExpectedException(\InvalidArgumentException::class, 'The base64 encoded string is not a valid image.');
		$url = $user->pushBase64ImageToS3('file_key', 'garbage', 'public');

		// should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}

	public function testItCanPushFileToS3()
	{
		$user = new User;

		$file = new File($this->relTestPath('tools/fuzzpro.png'));

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', file_get_contents($file), 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('aws_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->twice()->with('s3')->andReturn($filesystem_mock);

		$url = $user->pushFileToS3('file_key', $file, 'public');

		// should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}

	public function testItThrowsErrorIfNotSymfonyFile()
	{
		$user = new User;

		$file = new File(__FILE__);

		$this->setExpectedException(\InvalidArgumentException::class, 'The file could not be processed. It is not a valid image.');
		$url = $user->pushFileToS3('file_key', $file, 'public');

		// should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}

	public function testItThrowsErrorIfNotImageFile()
	{
		$user = new User;

		$file = $this->getTestFileStream('tools/fuzzpro.png');

		$this->setExpectedException(\InvalidArgumentException::class, 'The file could not be processed. It is not a valid image.');
		$url = $user->pushFileToS3('file_key', $file, 'public');

		// should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}

	public function testItCanPushImageFileToS3()
	{
		$user = new User;

		$file = new File($this->relTestPath('tools/fuzzpro.png'));

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', file_get_contents($file), 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('aws_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->twice()->with('s3')->andReturn($filesystem_mock);

		$url = $user->pushImageToS3('file_key', $file, 'public');

		// should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}

	public function testItCanPushB64ImageToS3()
	{
		$user = new User;

		$file       = $this->getTestFileStream('tools/fuzzpro.png');
		$b64_string = base64_encode($file);

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', $file, 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('aws_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->twice()->with('s3')->andReturn($filesystem_mock);

		$url = $user->pushImageToS3('file_key', $b64_string, 'public');

		// should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}

	public function testItCanSendStreamToS3()
	{
		$user = new User;

		$file = $this->getTestFileStream('tools/fuzzpro.png');

		$filesystem_mock        = Mockery::mock(Filesystem::class);
		$adapter_interface_mock = Mockery::mock(AdapterInterface::class);
		$s3_client_class        = Mockery::mock(S3Client::class);

		$filesystem_mock->shouldReceive('put')->once()->with('file_key.png', $file, 'public');
		$filesystem_mock->shouldReceive('getAdapter')->once()->andReturn($adapter_interface_mock);

		$adapter_interface_mock->shouldReceive('getClient')->once()->andReturn($s3_client_class);

		$s3_client_class->shouldReceive('getObjectUrl')->once()->with('aws_bucket', 'file_key.png')
			->andReturn('http://s3.com/file_key.png');

		Storage::shouldReceive('disk')->twice()->with('s3')
			->andReturn($filesystem_mock); // once for put, once for get URL

		$url = $user->sendStreamToS3('file_key', $file, 'public');

		// Should return with default cdn url
		$this->assertEquals('http://media.default_cdn1.com/file_key.png', $url);
	}
}

class User extends Model
{
	use S3ForImages;

	/**
	 * Upload the image and set the attribute.
	 *
	 * @param string | \Symfony\Component\HttpFoundation\File\File $value
	 */
	public function setImageAttribute($value)
	{
		// Need to create the index or at least check for existence before trying to make comparisons
		if (! isset($this->attributes['image_url'])) {
			$this->attributes['image_url'] = null;
		}

		$this->attributes['image_url'] = ($value === $this->attributes['image_url'] || empty($value)) ? $value :
			$this->pushImageToS3($this->generateRandKey("images/profile_pictures/"), $value, $this->visibilityPublic());
	}

	/**
	 * Returns the key to set visibility to public.
	 *
	 * @return string
	 */
	public function getVisibilityPublic()
	{
		return $this->visibilityPublic();
	}

	/**
	 * Returns the key to set visibility to private.
	 *
	 * @return string
	 */
	public function getVisibilityPrivate()
	{
		return $this->visibilityPrivate();
	}
}
