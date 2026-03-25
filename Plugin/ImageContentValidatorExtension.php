<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageContentValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Phrase;
use MarkShust\PolyshellPatch\Utility\Configurations;

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
     * @var Configurations
     */
    private Configurations $configurations;

    /**
     * @param IoFile $ioFile
     */
    public function __construct(
        IoFile $ioFile,
        Configurations $configurations
    ) {
        $this->ioFile = $ioFile;
        $this->configurations = $configurations;
    }

    /**
     * After core validation passes, additionally reject dangerous file extensions.
     *
     * @param ImageContentValidator $subject
     * @param bool $result
     * @param ImageContentInterface $imageContent
     *
     * @return bool
     * @throws InputException
     * *@throws NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterIsValid(
        ImageContentValidator $subject,
        bool $result,
        ImageContentInterface $imageContent
    ): bool {
        if (!$this->configurations->isModuleEnabled()) {
            return $result;
        }

        $fileName = $imageContent->getName();
        $pathInfo = $this->ioFile->getPathInfo($fileName);
        $extension = strtolower($pathInfo['extension'] ?? '');

        if ($extension && !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InputException(
                new Phrase('The image file extension "%1" is not allowed.', [$extension])
            );
        }

        return $result;
    }
}
