<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageContentValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File as IoFile;

/**
 * Validate that the uploaded filename has a safe image extension.
 *
 * The core ImageContentValidator checks MIME type and forbidden characters
 * but never validates the file extension — a polyglot file can pass MIME
 * validation while carrying a .php (or .phtml, .phar, etc.) extension.
 */
class ImageContentValidatorExtension
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'gif', 'png'];

    /**
     * @var IoFile
     */
    private IoFile $ioFile;

    /**
     * @param IoFile $ioFile
     */
    public function __construct(IoFile $ioFile)
    {
        $this->ioFile = $ioFile;
    }

    /**
     * After core validation passes, additionally reject dangerous file extensions.
     *
     * @param ImageContentValidator $subject
     * @param bool $result
     * @param ImageContentInterface $imageContent
     * @return bool
     * @throws LocalizedException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsValid(
        ImageContentValidator $subject,
        bool $result,
        ImageContentInterface $imageContent
    ): bool {
        $fileName = $imageContent->getName();
        $pathInfo = $this->ioFile->getPathInfo($fileName);
        $extension = strtolower($pathInfo['extension'] ?? '');

        if ($extension && !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new LocalizedException(
                __('The image file extension "%1" is not allowed.', $extension)
            );
        }

        return $result;
    }
}
