<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Test\Unit\Plugin;

use MarkShust\PolyshellPatch\Plugin\ImageContentPolyglotValidator;
use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageContentValidator;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImageContentPolyglotValidatorTest extends TestCase
{
    private ImageContentPolyglotValidator $model;

    /** @var ImageContentValidator|MockObject */
    private $subjectMock;

    /** @var ImageContentInterface|MockObject */
    private $imageContentMock;

    protected function setUp(): void
    {
        $this->model = new ImageContentPolyglotValidator();
        $this->subjectMock = $this->createMock(ImageContentValidator::class);
        $this->imageContentMock = $this->createMock(ImageContentInterface::class);
    }

    public function testValidGifPasses(): void
    {
        $validGif = base64_encode('GIF89a' . str_repeat("\x00", 10));
        $this->imageContentMock->method('getBase64EncodedData')->willReturn($validGif);

        $result = $this->model->afterIsValid($this->subjectMock, true, $this->imageContentMock);
        $this->assertTrue($result);
    }

    public function testPhpContentMarkerIsRejected(): void
    {
        $payload = '<?php echo "hello";';
        $this->imageContentMock->method('getBase64EncodedData')->willReturn(base64_encode($payload));

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('The uploaded image content is not allowed.');

        $this->model->afterIsValid($this->subjectMock, true, $this->imageContentMock);
    }

    public function testGifPhpPolyglotIsRejected(): void
    {
        $payload = 'GIF89a;<?php eval(base64_decode("dGVzdA==")); ?>';
        $this->imageContentMock->method('getBase64EncodedData')->willReturn(base64_encode($payload));

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('The uploaded image content is not allowed.');

        $this->model->afterIsValid($this->subjectMock, true, $this->imageContentMock);
    }

    public function testEmptyDataPasses(): void
    {
        $this->imageContentMock->method('getBase64EncodedData')->willReturn('');

        $result = $this->model->afterIsValid($this->subjectMock, true, $this->imageContentMock);
        $this->assertTrue($result);
    }

    public function testFalseCoreValidationResultIsPreserved(): void
    {
        $this->imageContentMock->expects($this->never())->method('getBase64EncodedData');

        $result = $this->model->afterIsValid($this->subjectMock, false, $this->imageContentMock);
        $this->assertFalse($result);
    }

    public function testInvalidEncodingThrowsException(): void
    {
        // "!!!" is not valid base64 with strict check
        $this->imageContentMock->method('getBase64EncodedData')->willReturn('!!!');

        $this->expectException(InputException::class);
        $this->expectExceptionMessage('Invalid image payload encoding.');

        $this->model->afterIsValid($this->subjectMock, true, $this->imageContentMock);
    }
}
